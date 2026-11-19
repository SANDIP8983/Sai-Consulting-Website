<?php

namespace App\Services;

use App\Exceptions\CleanupRefused;
use Illuminate\Support\Facades\DB;

/** Read-only inventory. No locks, journal writes, messages, or model events. */
class CleanupInventory
{
    public function __construct(private readonly CleanupSafety $safety, private readonly CleanupQueueInspector $queues) {}

    public function inspect(): array
    {
        $blockers = [];
        $schema = [];
        $rows = array_fill_keys([...CleanupSafety::TABLES, 'jobs', 'failed_jobs', 'file_number_sequences'], []);
        $targets = $files = $sequences = [];
        $journal = null;
        $resume = false;
        $nextReference = 'unavailable';
        $queueRuntime = null;
        $queueTables = CleanupQueueInspector::TABLES;
        $inspectBatches = false;
        $this->capture($blockers, 'Reference preview', function () use (&$nextReference): void {
            $nextReference = app(ReferenceNumberService::class)->generate();
        });
        if ((int) now()->year !== 2026) {
            $blockers[] = 'This cleanup can reset numbering only during calendar year 2026.';
        }
        if (config('filesystems.disks.local.driver') !== 'local') {
            $blockers[] = 'Cleanup requires the local private filesystem adapter.';
        }
        $this->capture($blockers, 'Request inventory', function () use (&$targets): void {
            $targets = DB::table('requests')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        });
        $requestIds = array_map('intval', array_column($targets, 'id'));
        $rows['requests'] = array_column($targets, null, 'id');
        $this->capture($blockers, 'Dependent row inventory', function () use (&$rows, &$blockers, &$schema, $requestIds): void {
            $rows = array_replace($rows, $this->safety->rows($requestIds, $blockers, $schema));
        });
        $sync = config('queue.connections.'.config('queue.default').'.driver') === 'sync';
        $absentQueueTables = $sync ? array_values(array_diff($queueTables, array_keys($schema))) : [];
        $inspectBatches = $sync && isset($schema['job_batches']);
        $queueTables = array_values(array_diff($queueTables, $absentQueueTables));
        $this->capture($blockers, 'Database safety preflight', fn () => $this->safety->assertDatabaseSafety($absentQueueTables, $inspectBatches));
        $this->capture($blockers, 'Queue inventory', function () use (&$rows, &$blockers, $requestIds, $absentQueueTables): void {
            $queueIds = $this->queues->inspect($requestIds, array_keys($rows['customer_notification_deliveries']),
                array_keys($rows['request_final_document_deliveries']), $blockers, allowSync: true, absentTables: $absentQueueTables);
            foreach ($queueIds as $table => $ids) {
                $rows[$table] = $this->safety->selectedRows($table, 'id', $ids);
            }
        });
        if ($sync) {
            $this->capture($blockers, 'Sync runtime', function () use (&$queueRuntime, $schema, $absentQueueTables): void {
                $queueRuntime = $this->queues->inspectSyncRuntime($schema, $absentQueueTables);
            });
        }
        $this->capture($blockers, 'Sequence inventory', function () use (&$sequences, &$rows, &$blockers, $targets): void {
            $maximums = [];
            foreach ($targets as $target) {
                if ($target['file_number'] === null) {
                    continue;
                }
                if (! preg_match('~^SC/(\d{4})/F(\d{6})$~', $target['file_number'], $match) || (int) $match[2] < 1) {
                    $blockers[] = 'Unrecognized file-number convention on request ID '.$target['id'];

                    continue;
                }
                $maximums[(int) $match[1]] = max($maximums[(int) $match[1]] ?? 0, (int) $match[2]);
            }
            $sequences = DB::table('file_number_sequences')->orderBy('year')->get()->map(fn ($row) => (array) $row)->all();
            foreach ($sequences as &$sequence) {
                if ((int) $sequence['last_number'] < ($maximums[(int) $sequence['year']] ?? 0)) {
                    $blockers[] = 'File counter is below an assigned number for year '.$sequence['year'];
                }
                unset($maximums[(int) $sequence['year']]);
                // Only the requested 2026 business counter is reset.
                $sequence['reset'] = (int) $sequence['year'] === 2026;
                if ($sequence['reset']) {
                    $row = $sequence;
                    unset($row['reset']);
                    $rows['file_number_sequences'][$row['id']] = $row;
                }
            }
            foreach (array_keys($maximums) as $year) {
                $blockers[] = 'Missing file counter for assigned year '.$year;
            }
        });
        $this->capture($blockers, 'Recovery journal', function () use (&$journal): void {
            $journal = $this->safety->readJournal();
        });
        if ($journal !== null) {
            if (($journal['schema_hash'] ?? null) !== hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR))) {
                $blockers[] = 'Schema differs from the signed recovery journal; manual review required.';
            }
            if (($journal['queue_runtime'] ?? null) !== $queueRuntime) {
                $blockers[] = 'Queue runtime differs from the signed recovery journal; manual review required.';
            }
            $this->capture($blockers, 'Recovery state', function () use (&$resume, &$blockers, $journal, $targets): void {
                $remaining = false;
                foreach ($journal['ids'] as $table => $ids) {
                    if ($this->safety->selectedRows($table, 'id', $ids) !== []) {
                        $remaining = true;
                    }
                }
                $resume = $targets === [] && ! $remaining;
                if ($journal['completed'] && ! $resume) {
                    $blockers[] = 'Cleanup is already completed; newly created requests or reused IDs cannot be approved again.';
                }
            });
        }
        $this->capture($blockers, 'Private file inventory', function () use (&$files, &$blockers, $rows, $resume, $journal): void {
            $files = $this->safety->files($rows, $blockers, $resume ? $journal['files'] : []);
        });
        // Only stable database state, schema and file content enter the fingerprint.
        // Customer values and queue payloads are never printed or persisted in the journal.
        ksort($rows);
        $snapshot = [CleanupSafety::VERSION, $this->safety->databaseIdentity(), $rows, $files, $schema, $sequences];
        if ($queueRuntime !== null) {
            $snapshot[] = $queueRuntime;
        }
        $fingerprint = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        if ($resume) {
            $expected = array_column($journal['files'], null, 'path');
            foreach ($files as $file) {
                if (! isset($expected[$file['path']])) {
                    $blockers[] = 'New file appeared during recovery; it is not approved.';
                } elseif ($file['exists'] && $file['hash'] !== $expected[$file['path']]['hash']) {
                    $blockers[] = 'Recovery file changed: '.hash('sha256', $file['path']);
                }
            }
            if ($rows['file_number_sequences'] !== []) {
                $blockers[] = 'New request numbering state appeared during recovery.';
            }
            $preserved = array_values(array_filter($sequences, fn ($sequence) => ! $sequence['reset']));
            if (($journal['preserved_sequences'] ?? null) !== $preserved) {
                $blockers[] = 'Preserved numbering state changed during recovery.';
            }
            $fingerprint = $journal['fingerprint'];
        } elseif ($journal !== null && ! hash_equals($journal['fingerprint'], $fingerprint)) {
            $blockers[] = 'Inventory differs from the existing recovery journal; manual reconciliation required.';
        }
        $ids = array_map('array_keys', $rows);
        $counts = array_map('count', $rows);
        foreach ($counts as $table => &$count) {
            if (in_array("Cannot inventory table: $table.", $blockers, true)) {
                $count = 'unavailable';
            }
            if ($table !== 'requests' && in_array($table, CleanupSafety::TABLES, true)
                && collect($blockers)->contains(fn ($blocker) => str_starts_with($blocker, 'Dependent row inventory:'))) {
                $count = 'unavailable';
            }
            if (in_array($table, ['jobs', 'failed_jobs'], true)
                && collect($blockers)->contains(fn ($blocker) => str_starts_with($blocker, 'Queue inventory:'))) {
                $count = 'unavailable';
            }
        }
        unset($count);
        $blockers = array_values(array_unique($blockers));

        return compact('targets', 'ids', 'counts', 'files', 'sequences', 'schema', 'fingerprint', 'blockers', 'resume', 'journal', 'nextReference',
            'queueTables', 'queueRuntime', 'inspectBatches');
    }

    private function capture(array &$blockers, string $stage, callable $operation): void
    {
        try {
            $operation();
        } catch (\Throwable $exception) {
            $blockers[] = $stage.': '.($exception instanceof CleanupRefused ? $exception->getMessage() : 'Inspection failed; raw exception suppressed.');
        }
    }
}
