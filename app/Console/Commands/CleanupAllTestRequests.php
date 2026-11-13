<?php

namespace App\Console\Commands;

use App\Exceptions\CleanupRefused;
use App\Services\CleanupInventory;
use App\Services\CleanupSafety;
use App\Services\ReferenceNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Formatter\OutputFormatter;

class CleanupAllTestRequests extends Command
{
    protected $signature = 'sai:cleanup-all-test-requests
        {--execute : Delete only the exact inventoried snapshot}
        {--confirm= : DELETE-ALL-INVENTORIED-TEST-REQUESTS}
        {--approve= : Exact fingerprint from a blocker-free dry run}
        {--workers-stopped : Attest every queue worker and other writer is stopped}
        {--scheduler-stopped : Attest scheduling and automated retries are stopped on all nodes}';

    protected $description = 'Inventory every existing test request across years; execute only its approved snapshot';

    public function handle(CleanupInventory $inventory, CleanupSafety $safety): int
    {
        try {
            $audit = $inventory->inspect();
            $this->report($audit);
            $this->assertClear($audit);
            if (! $this->option('execute')) {
                $this->info('DRY-RUN MODE: no database, file, lock or journal writes.');

                return self::SUCCESS;
            }
            $this->assertExecutionGuards();
            if ($this->option('confirm') !== 'DELETE-ALL-INVENTORIED-TEST-REQUESTS'
                || ! hash_equals($audit['fingerprint'], (string) $this->option('approve'))) {
                throw new CleanupRefused('Exact full-scope confirmation and current approval fingerprint are required.');
            }
            $years = array_unique([2026, ...array_column(array_filter($audit['sequences'], fn ($sequence) => $sequence['reset']), 'year')]);
            sort($years);
            $keys = ['cleanup:all-test-requests:v2', 'requests:reference-number', ...array_map(fn ($year) => "requests:file-number:$year", $years)];
            $this->withLocks($keys, function () use ($audit, $inventory, $safety): void {
                $this->assertExecutionGuards();
                $current = $inventory->inspect();
                $this->assertSameInventory($audit, $current);
                $journal = $current['journal'];
                if ($journal === null) {
                    $journal = ['version' => CleanupSafety::VERSION, 'database' => $safety->databaseIdentity(),
                        'fingerprint' => $current['fingerprint'], 'ids' => $current['ids'], 'files' => $current['files'],
                        'schema_hash' => hash('sha256', json_encode($current['schema'], JSON_THROW_ON_ERROR)),
                        'preserved_sequences' => array_values(array_filter($current['sequences'], fn ($sequence) => ! $sequence['reset'])),
                        'completed' => false];
                    $safety->writeJournal($journal);
                }
                if (! $current['resume']) {
                    // Explicit serializable range locks prevent new request inserts during MySQL deletion.
                    if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                        DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
                    }
                    DB::transaction(function () use ($inventory, $current): void {
                        // Full ranges also stop queue inserts without request foreign keys.
                        // Preserved rows are locked briefly, never selected for deletion.
                        foreach (array_unique(['requests', 'file_number_sequences', 'jobs', 'failed_jobs', 'settings', ...CleanupSafety::TABLES]) as $table) {
                            DB::table($table)->where('id', '>=', 0)->orderBy('id')->lockForUpdate()->get(['id']);
                        }
                        $this->assertSameInventory($current, $inventory->inspect());
                        foreach (['jobs', 'failed_jobs', ...CleanupSafety::TABLES, 'file_number_sequences'] as $table) {
                            foreach (array_chunk($current['ids'][$table], 400) as $chunk) {
                                $deleted = DB::table($table)->whereIn('id', $chunk)->delete();
                                if ($deleted !== count($chunk)) {
                                    throw new CleanupRefused('Deletion count changed; transaction rolled back.');
                                }
                            }
                        }
                        if (DB::table('requests')->exists() || app(ReferenceNumberService::class)->generate() !== 'SC/2026/000001') {
                            throw new CleanupRefused('Numbering postcondition failed; transaction rolled back.');
                        }
                    });
                }
                // From here the database may be committed; file deletion is not transactional.
                $recovery = $inventory->inspect();
                $this->assertSameInventory($current, $recovery);
                if (! $recovery['resume']) {
                    throw new CleanupRefused('Database cleanup state is incomplete; files were not deleted.');
                }
                $failed = 0;
                foreach ($journal['files'] as $file) {
                    try {
                        $safety->assertPath($file['path'], $file['prefixes']);
                        if (! Storage::disk('local')->exists($file['path'])) {
                            continue;
                        }
                        if ($file['hash'] === null || ! hash_equals($file['hash'], hash_file('sha256', Storage::disk('local')->path($file['path'])))) {
                            throw new CleanupRefused('File content changed.');
                        }
                        if (! $safety->deleteFile($file['path']) || Storage::disk('local')->exists($file['path'])) {
                            throw new CleanupRefused('File deletion failed.');
                        }
                    } catch (\Throwable) {
                        $failed++;
                        $this->error('File not removed; path fingerprint: '.hash('sha256', $file['path']));
                    }
                }
                if ($failed) {
                    throw new CleanupRefused('Database cleanup committed; private files remain. Keep maintenance enabled and resume using dry-run and the signed journal.');
                }
                $this->assertSameInventory($current, $inventory->inspect());
                $journal['completed'] = true;
                $safety->writeJournal($journal);
            });
            $this->info('Cleanup complete. Next request: SC/2026/000001; next file: SC/2026/F000001 during 2026. Primary-key counters preserved.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception instanceof CleanupRefused ? $exception->getMessage() : 'Cleanup failed; raw exception suppressed.');
            if ($this->option('execute')) {
                $this->warn('Keep maintenance enabled. If database commit already occurred, files cannot be rolled back; review the signed journal before retrying.');
            }

            return self::FAILURE;
        }
    }

    private function assertExecutionGuards(): void
    {
        $isolatedTest = defined('PHPUNIT_COMPOSER_INSTALL') && DB::connection()->getDriverName() === 'sqlite'
            && DB::connection()->getDatabaseName() === ':memory:';
        if (! $isolatedTest && (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            || DB::connection()->transactionLevel() !== 0)) {
            throw new CleanupRefused('Execution requires a dedicated MySQL/InnoDB connection without an existing transaction.');
        }
        if (! app()->environment('production') || ! app()->isDownForMaintenance()
            || ! $this->option('workers-stopped') || ! $this->option('scheduler-stopped')) {
            throw new CleanupRefused('Execution requires production, maintenance mode, --workers-stopped and --scheduler-stopped.');
        }
    }

    private function assertClear(array $audit): void
    {
        if ($audit['blockers'] !== []) {
            throw new CleanupRefused('Cleanup blocked. Resolve every reported blocker and repeat dry-run.');
        }
    }

    private function assertSameInventory(array $expected, array $actual): void
    {
        $this->assertClear($actual);
        if (! hash_equals($expected['fingerprint'], $actual['fingerprint'])) {
            throw new CleanupRefused('Inventory changed after approval; no new requests or records will be silently included.');
        }
    }

    private function withLocks(array $keys, callable $operation): void
    {
        if ($keys === []) {
            $operation();

            return;
        }
        $key = array_shift($keys);
        Cache::lock($key, 3600)->block(5, fn () => $this->withLocks($keys, $operation));
    }

    private function report(array $audit): void
    {
        $this->line('Scope: ALL explicitly inventoried existing customer requests, across every year.');
        $this->line('Target requests: '.count($audit['targets']));
        $this->line('Current next reference: '.$this->safeText($audit['nextReference']));
        $this->table(['ID', 'Reference', 'Created year', 'File number'], array_map(fn ($target) => [
            $target['id'], $this->safeText($target['reference_no']), substr($target['created_at'] ?? '', 0, 4) ?: 'unknown', $this->safeText($target['file_number'] ?? '')], $audit['targets']));
        $this->table(['Table', 'Affected rows'], collect($audit['counts'])->map(fn ($count, $table) => [$table, $count])->values()->all());
        $this->table(['Sequence year', 'Current last number', 'Action'], array_map(fn ($sequence) => [$sequence['year'], $sequence['last_number'], $sequence['reset'] ? 'reset request file sequence' : 'preserve other year'], $audit['sequences']));
        $this->line('Private files recorded: '.count($audit['files']));
        $this->line('Private files currently present: '.collect($audit['files'])->where('exists', true)->count());
        $this->line('Private bytes: '.collect($audit['files'])->sum('bytes'));
        $this->table(['Private file / unsafe-path hash', 'State', 'Bytes', 'Orphan', 'Shared'], array_map(fn ($file) => [
            'sha256:'.hash('sha256', $file['path']),
            ! $file['safe'] ? 'unsafe/unowned' : ($file['exists'] ? 'present' : 'missing'), $file['bytes'], $file['orphan'] ? 'yes' : 'no', $file['shared'] ? 'yes' : 'no'], $audit['files']));
        $this->line('Discovered schema tables: '.count($audit['schema']));
        $this->line('Numbering preview on success in 2026: SC/2026/000001 and SC/2026/F000001.');
        $this->line('Approval fingerprint: '.$audit['fingerprint']);
        foreach ($audit['blockers'] as $blocker) {
            $this->error($this->safeText($blocker));
        }
        $this->line('Blockers: '.count($audit['blockers']));
        if ($audit['resume']) {
            $this->line('Recovery mode: only previously inventoried files may be removed.');
        }
    }

    private function safeText(string $value): string
    {
        return OutputFormatter::escape(preg_replace('/[\x00-\x1f\x7f]/', '', $value));
    }
}
