<?php

namespace App\Console\Commands;

use App\Models\CustomerRequest;
use App\Models\FileNumberSequence;
use App\Services\ReferenceNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CleanupProductionTestData extends Command
{
    private const YEAR = 2026;

    private const REFERENCES = [
        'SC/2026/000001',
        'SC/2026/000002',
        'SC/2026/000003',
    ];

    private const CONFIRMATION = 'DELETE-SC-2026-000001-000003';

    private const DIRECT_REQUEST_TABLES = [
        'request_documents',
        'request_payments',
        'request_status_histories',
        'request_dispatches',
        'request_dispatch_histories',
        'request_billings',
        'request_billing_histories',
        'request_service_approval_histories',
        'request_services',
        'request_service_work_scope_histories',
        'request_case_action_histories',
        'request_processing_details',
        'request_processing_histories',
        'request_assignment_histories',
        'request_contact_change_histories',
        'customer_notification_events',
    ];

    protected $signature = 'sai:cleanup-production-test-data
        {--execute : Permanently delete the audited test data instead of performing a dry run}
        {--confirm= : Required execution token}';

    protected $description = 'Dry-run and safely remove the three known pre-launch production test requests';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $this->components->info($execute ? 'EXECUTION MODE' : 'DRY-RUN MODE — no changes will be made');

        $audit = $this->audit();
        $this->renderAudit($audit);

        if ($audit['errors'] !== []) {
            $this->components->error('Safety checks failed. No data or files were changed.');
            foreach ($audit['errors'] as $error) {
                $this->line(' - '.$error);
            }

            return self::FAILURE;
        }

        if (! $execute) {
            $this->newLine();
            $this->components->info('Dry run completed successfully. No data or files were changed.');
            $this->line('Execute only after reviewing this report:');
            $this->line('php artisan sai:cleanup-production-test-data --execute --confirm='.self::CONFIRMATION);

            return self::SUCCESS;
        }

        if (! app()->environment('production')) {
            $this->components->error('Execution is permitted only when APP_ENV is production. No changes were made.');

            return self::FAILURE;
        }
        if (! hash_equals(self::CONFIRMATION, (string) $this->option('confirm'))) {
            $this->components->error('The exact confirmation token is required. No changes were made.');

            return self::FAILURE;
        }

        $deletedFiles = [];
        $failedFiles = [];

        Cache::lock('requests:reference-number', 30)->block(10, function () use (&$deletedFiles, &$failedFiles): void {
            Cache::lock('requests:file-number:'.self::YEAR, 30)->block(10, function () use (&$deletedFiles, &$failedFiles): void {
                $lockedAudit = DB::transaction(function (): array {
                    CustomerRequest::query()
                        ->whereIn('reference_no', self::REFERENCES)
                        ->lockForUpdate()
                        ->get();
                    FileNumberSequence::query()->where('year', self::YEAR)->lockForUpdate()->get();

                    $audit = $this->audit();
                    if ($audit['errors'] !== []) {
                        throw new \RuntimeException('Safety state changed after the dry run: '.implode(' ', $audit['errors']));
                    }

                    $this->deleteDatabaseRows($audit);

                    return $audit;
                });

                foreach ($lockedAudit['files'] as $file) {
                    if (! $file['exists']) {
                        continue;
                    }
                    if (Storage::disk('local')->delete($file['path'])) {
                        $deletedFiles[] = $file['path'];
                    } else {
                        $failedFiles[] = $file['path'];
                    }
                }
            });
        });

        $this->newLine();
        $this->components->info('Database cleanup completed for exactly the three audited test requests.');
        $this->line('Private files deleted: '.count($deletedFiles));
        if ($failedFiles !== []) {
            $this->components->warn('Database cleanup succeeded, but these now-orphaned private files could not be removed:');
            foreach ($failedFiles as $path) {
                $this->line(' - '.$path);
            }

            return self::FAILURE;
        }
        $this->line('Next reference number: SC/2026/000001');
        $this->line('Next file number: SC/2026/F000001');

        return self::SUCCESS;
    }

    private function audit(): array
    {
        $targets = CustomerRequest::query()
            ->whereIn('reference_no', self::REFERENCES)
            ->orderBy('reference_no')
            ->get(['id', 'reference_no', 'file_number', 'created_at']);
        $requestIds = $targets->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $errors = [];

        $found = $targets->pluck('reference_no')->all();
        $missing = array_values(array_diff(self::REFERENCES, $found));
        if ($missing !== []) {
            $errors[] = 'All three exact target references must exist. Missing: '.implode(', ', $missing).'.';
        }

        if ($targets->contains(fn (CustomerRequest $request): bool => (int) $request->created_at->format('Y') !== self::YEAR)) {
            $errors[] = 'Every target must have been created in 2026.';
        }
        if ((int) now()->format('Y') !== self::YEAR) {
            $errors[] = 'This one-time cleanup is valid only during calendar year 2026.';
        }

        $otherYearRequests = CustomerRequest::query()
            ->whereNotIn('id', $requestIds ?: [0])
            ->where(function ($query): void {
                $query->whereYear('created_at', self::YEAR)
                    ->orWhere('reference_no', 'like', 'SC/2026/%')
                    ->orWhere('file_number', 'like', 'SC/2026/F%');
            })
            ->pluck('reference_no')
            ->all();
        if ($otherYearRequests !== []) {
            $errors[] = 'Other 2026 request/reference/file-number rows exist and must be preserved: '.implode(', ', $otherYearRequests).'.';
        }

        $unexpectedForeignTables = array_values(array_diff($this->directForeignKeyTables(), self::DIRECT_REQUEST_TABLES));
        if ($unexpectedForeignTables !== []) {
            $errors[] = 'Unrecognized tables reference requests.id: '.implode(', ', $unexpectedForeignTables).'. Update and retest the cleanup tool first.';
        }

        $sequence = FileNumberSequence::query()->where('year', self::YEAR)->first();
        $lastFileNumber = (int) ($sequence?->last_number ?? 0);
        $lastReference = CustomerRequest::query()->whereYear('created_at', self::YEAR)->latest('id')->value('reference_no');
        $nextReferenceBeforeCleanup = app(ReferenceNumberService::class)->generate();
        $assignedNumbers = [];
        foreach ($targets as $target) {
            if ($target->file_number === null) {
                continue;
            }
            if (! preg_match('/^SC\/2026\/F(\d{6})$/', $target->file_number, $matches)) {
                $errors[] = "Target {$target->reference_no} has an unexpected file number: {$target->file_number}.";

                continue;
            }
            $assignedNumbers[] = (int) $matches[1];
        }
        sort($assignedNumbers);
        $expectedAssigned = $lastFileNumber > 0 ? range(1, $lastFileNumber) : [];
        if ($assignedNumbers !== $expectedAssigned) {
            $errors[] = 'The 2026 file-number sequence is not fully accounted for by contiguous target file numbers.';
        }

        $ids = $this->ownedIds($requestIds);
        $counts = $this->ownedCounts($requestIds, $ids);
        $files = $this->ownedFiles($requestIds, $ids['dispatches']);
        foreach ($files as $file) {
            if (! $file['safe']) {
                $errors[] = 'Unsafe private file path found: '.$file['path'].'.';
            }
            if ($file['shared']) {
                $errors[] = 'Private file path is also referenced by unrelated data: '.$file['path'].'.';
            }
        }

        if ($requestIds !== [] && DB::table('customer_notification_events')->whereIn('request_id', $requestIds)->whereNotNull('appointment_id')->exists()) {
            $errors[] = 'A target request notification event is also linked to an appointment; automatic deletion is refused.';
        }

        return compact('targets', 'requestIds', 'ids', 'counts', 'files', 'sequence', 'lastFileNumber', 'lastReference', 'nextReferenceBeforeCleanup', 'errors');
    }

    private function ownedIds(array $requestIds): array
    {
        $serviceIds = DB::table('request_services')->whereIn('request_id', $requestIds)->pluck('id')->all();
        $billingIds = DB::table('request_billings')->whereIn('request_id', $requestIds)->pluck('id')->all();
        $dispatchIds = DB::table('request_dispatches')->whereIn('request_id', $requestIds)->pluck('id')->all();
        $notificationIds = DB::table('customer_notification_events')->whereIn('request_id', $requestIds)->pluck('id')->all();
        $deliveryIds = DB::table('customer_notification_deliveries')->whereIn('notification_event_id', $notificationIds)->pluck('id')->all();

        return [
            'services' => $serviceIds,
            'billings' => $billingIds,
            'dispatches' => $dispatchIds,
            'notifications' => $notificationIds,
            'deliveries' => $deliveryIds,
            'scopes' => DB::table('request_service_work_scopes')->whereIn('request_service_id', $serviceIds)->pluck('id')->all(),
        ];
    }

    private function ownedCounts(array $requestIds, array $ids): array
    {
        $counts = ['requests' => count($requestIds)];
        foreach (self::DIRECT_REQUEST_TABLES as $table) {
            $counts[$table] = DB::table($table)->whereIn('request_id', $requestIds)->count();
        }
        $counts['request_service_work_scopes'] = count($ids['scopes']);
        $counts['request_billing_government_charges'] = DB::table('request_billing_government_charges')->whereIn('request_billing_id', $ids['billings'])->count();
        $counts['request_dispatch_proofs'] = DB::table('request_dispatch_proofs')->whereIn('request_dispatch_id', $ids['dispatches'])->count();
        $counts['customer_notification_deliveries'] = count($ids['deliveries']);
        $counts['queued_notification_jobs'] = $this->notificationJobIds('jobs', $ids['deliveries'])->count();
        $counts['failed_notification_jobs'] = $this->notificationJobIds('failed_jobs', $ids['deliveries'])->count();

        return $counts;
    }

    private function ownedFiles(array $requestIds, array $dispatchIds): array
    {
        $documentPaths = DB::table('request_documents')->whereIn('request_id', $requestIds)->pluck('file_path');
        $proofPaths = DB::table('request_dispatch_proofs')->whereIn('request_dispatch_id', $dispatchIds)->pluck('file_path');

        return $documentPaths->merge($proofPaths)->filter()->unique()->sort()->values()->map(function (string $path) use ($requestIds, $dispatchIds): array {
            $safe = $this->isSafeRelativePath($path);
            $sharedDocument = DB::table('request_documents')->where('file_path', $path)->whereNotIn('request_id', $requestIds)->exists();
            $sharedProof = DB::table('request_dispatch_proofs')->where('file_path', $path)->whereNotIn('request_dispatch_id', $dispatchIds)->exists();

            return [
                'path' => $path,
                'safe' => $safe,
                'shared' => $sharedDocument || $sharedProof,
                'exists' => $safe && Storage::disk('local')->exists($path),
            ];
        })->all();
    }

    private function deleteDatabaseRows(array $audit): void
    {
        $requestIds = $audit['requestIds'];
        $ids = $audit['ids'];

        foreach (['jobs', 'failed_jobs'] as $table) {
            $jobIds = $this->notificationJobIds($table, $ids['deliveries']);
            if ($jobIds->isNotEmpty()) {
                DB::table($table)->whereIn('id', $jobIds)->delete();
            }
        }
        DB::table('customer_notification_deliveries')->whereIn('notification_event_id', $ids['notifications'])->delete();
        DB::table('customer_notification_events')->whereIn('request_id', $requestIds)->delete();
        DB::table('request_assignment_histories')->whereIn('request_id', $requestIds)->delete();
        DB::table('request_contact_change_histories')->whereIn('request_id', $requestIds)->delete();
        CustomerRequest::query()->whereIn('id', $requestIds)->delete();
        FileNumberSequence::query()->where('year', self::YEAR)->delete();
    }

    private function notificationJobIds(string $table, array $deliveryIds): Collection
    {
        if ($deliveryIds === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'payload')) {
            return collect();
        }

        return DB::table($table)
            ->where('payload', 'like', '%SendCustomerNotificationJob%')
            ->where(function ($query) use ($deliveryIds): void {
                foreach ($deliveryIds as $deliveryId) {
                    $query->orWhere('payload', 'like', '%deliveryId";i:'.(int) $deliveryId.';%');
                }
            })
            ->pluck('id');
    }

    private function directForeignKeyTables(): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->filter(fn (string $table): bool => collect(Schema::getForeignKeys($table))->contains(
                fn (array $foreign): bool => ($foreign['foreign_table'] ?? null) === 'requests'
            ))
            ->sort()
            ->values()
            ->all();
    }

    private function isSafeRelativePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', trim($path));

        return $normalized !== ''
            && ! str_starts_with($normalized, '/')
            && preg_match('/^[A-Za-z]:\//', $normalized) !== 1
            && preg_match('/(^|\/)\.\.($|\/)/', $normalized) !== 1;
    }

    private function renderAudit(array $audit): void
    {
        $this->newLine();
        $this->line('Target references:');
        foreach (self::REFERENCES as $reference) {
            $target = $audit['targets']->firstWhere('reference_no', $reference);
            $this->line(' - '.$reference.($target ? ' (request ID '.$target->id.', file '.($target->file_number ?: 'none').')' : ' (MISSING)'));
        }

        $this->newLine();
        $this->line('Owned database rows:');
        $this->table(['Table/category', 'Rows'], collect($audit['counts'])->map(fn ($count, $table): array => [$table, $count])->values()->all());

        $this->newLine();
        $this->line('Private files:');
        if ($audit['files'] === []) {
            $this->line(' - none');
        }
        foreach ($audit['files'] as $file) {
            $state = ! $file['safe'] ? 'UNSAFE' : ($file['shared'] ? 'SHARED' : ($file['exists'] ? 'exists' : 'missing'));
            $this->line(" - [{$state}] {$file['path']}");
        }

        $this->newLine();
        $this->line('Current latest 2026 reference: '.($audit['lastReference'] ?: 'none'));
        $this->line('Current next reference before cleanup: '.$audit['nextReferenceBeforeCleanup']);
        $this->line('Current 2026 file sequence: '.($audit['sequence'] ? $audit['lastFileNumber'] : 'no row'));
        $this->line('Expected after cleanup — next reference: SC/2026/000001');
        $this->line('Expected after cleanup — next file number: SC/2026/F000001');
    }
}
