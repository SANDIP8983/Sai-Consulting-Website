<?php

namespace App\Services;

use App\Exceptions\CleanupRefused;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CleanupSafety
{
    public const JOURNAL = 'maintenance/test-data-cleanup-all-v2.json';

    public const VERSION = 2;

    public function hasSelectiveJournal(): bool
    {
        return collect(Storage::disk('local')->allFiles('maintenance'))
            ->contains(fn ($path) => str_starts_with($path, 'maintenance/selective-cleanup-v1-'));
    }

    public function deleteFile(string $path): bool
    {
        return Storage::disk('local')->delete($path);
    }

    public function assertDatabaseSafety(array $absentQueueTables = [], bool $inspectBatches = false): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            if ((int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys !== 1) {
                throw new CleanupRefused('Foreign key enforcement must be enabled.');
            }

            return;
        }
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new CleanupRefused('Database driver has not been validated for this cleanup.');
        }
        if ((int) DB::selectOne('SELECT @@foreign_key_checks AS enabled')->enabled !== 1) {
            throw new CleanupRefused('Foreign key enforcement must be enabled.');
        }
        $tables = [...self::TABLES, ...CleanupQueueInspector::TABLES, 'file_number_sequences'];
        if ($inspectBatches) {
            $tables[] = 'job_batches';
        }
        $required = array_diff($tables, array_intersect($absentQueueTables, CleanupQueueInspector::TABLES));
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->whereIn('TABLE_NAME', $required)->pluck('ENGINE');
        if ($engines->count() !== count($required) || $engines->contains(fn ($engine) => strtolower($engine ?? '') !== 'innodb')) {
            throw new CleanupRefused('All cleanup tables must exist and use transactional InnoDB storage.');
        }
        if (DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::connection()->getDatabaseName())->whereIn('EVENT_OBJECT_TABLE', $tables)->exists()) {
            throw new CleanupRefused('Database triggers affect cleanup tables; explicit review is required.');
        }
        if (DB::table('information_schema.KEY_COLUMN_USAGE')->where('REFERENCED_TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_SCHEMA', '<>', DB::connection()->getDatabaseName())->whereIn('REFERENCED_TABLE_NAME', $tables)->exists()) {
            throw new CleanupRefused('Cross-database dependencies exist; full-scope cleanup requires explicit review.');
        }
    }

    /** Order is child before parent, with restrictive dependencies removed explicitly. */
    public const TABLES = [
        'request_final_document_delivery_items', 'request_final_document_deliveries', 'request_final_documents',
        'customer_notification_deliveries', 'customer_notification_events',
        'request_assignment_histories', 'request_contact_change_histories',
        'request_revenue_entry_documents', 'request_revenue_entry_histories', 'request_revenue_entries',
        'request_dispatch_proofs', 'request_dispatch_histories', 'request_dispatches',
        'request_service_work_scope_histories', 'request_service_work_scopes', 'request_service_approval_histories',
        'request_billing_government_charges', 'request_billing_histories', 'request_billings',
        'request_processing_details', 'request_processing_histories', 'request_documents',
        'request_payment_submissions', 'request_payments', 'request_status_histories', 'request_case_action_histories',
        'request_services', 'requests',
    ];

    private const INDIRECT = [
        'request_service_work_scopes' => ['request_services', 'request_service_id'],
        'request_billing_government_charges' => ['request_billings', 'request_billing_id'],
        'request_dispatch_proofs' => ['request_dispatches', 'request_dispatch_id'],
        'request_revenue_entry_documents' => ['request_revenue_entries', 'request_revenue_entry_id'],
        'customer_notification_deliveries' => ['customer_notification_events', 'notification_event_id'],
        'request_final_document_delivery_items' => ['request_final_document_deliveries', 'delivery_id'],
    ];

    public function selectedRows(string $table, string $column, array $ids): array
    {
        $rows = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            foreach (DB::table($table)->whereIn($column, $chunk)->orderBy('id')->get() as $row) {
                $rows[$row->id] = (array) $row;
            }
        }
        ksort($rows, SORT_NUMERIC);

        return $rows;
    }

    public function rows(array $requestIds, array &$blockers = [], array &$schema = []): array
    {
        $rows = [];
        foreach (self::TABLES as $table) {
            if (isset(self::INDIRECT[$table])) {
                continue;
            }
            try {
                $rows[$table] = $this->selectedRows($table, $table === 'requests' ? 'id' : 'request_id', $requestIds);
            } catch (\Throwable) {
                $rows[$table] = [];
                $blockers[] = "Cannot inventory table: $table.";
            }
        }
        foreach (self::INDIRECT as $table => [$parent, $column]) {
            try {
                $rows[$table] = $this->selectedRows($table, $column, array_keys($rows[$parent]));
            } catch (\Throwable) {
                $rows[$table] = [];
                $blockers[] = "Cannot inventory table: $table.";
            }
        }

        $localSchema = DB::connection()->getDriverName() === 'sqlite' ? 'main' : DB::connection()->getDatabaseName();
        foreach (Schema::getTables($localSchema) as $tableInfo) {
            $table = $tableInfo['name'];
            $columns = Schema::getColumnListing($table);
            sort($columns);
            $foreignKeys = Schema::getForeignKeys($table);
            usort($foreignKeys, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
            $schema[$table] = ['columns' => $columns, 'foreign_keys' => $foreignKeys];
            if ($table !== 'requests' && (in_array('file_number', $columns, true)
                || ($table !== 'appointments' && in_array('reference_no', $columns, true)))) {
                $blockers[] = "Unreviewed business numbering storage: $table.";
            }
            if (! in_array($table, self::TABLES, true) && collect($columns)->contains(fn ($column) => preg_match('/^(?:customer_)?request(?:_.*)?_id$/', $column))) {
                $blockers[] = "Unexpected request-linked table: $table.";
            }
            foreach ($columns as $column) {
                if (str_ends_with($column, '_type') && in_array(substr($column, 0, -5).'_id', $columns, true)
                    && ! ($table === 'customer_notification_events' && $column === 'source_type')) {
                    $blockers[] = "Unreviewed polymorphic relation: $table.$column.";
                }
            }
            foreach ($foreignKeys as $fk) {
                $parent = $fk['foreign_table'];
                if (! in_array($parent, [...self::TABLES, 'file_number_sequences', 'jobs', 'failed_jobs'], true)) {
                    continue;
                }
                if (! empty($fk['foreign_schema']) && $fk['foreign_schema'] !== $localSchema) {
                    $blockers[] = "Cross-database foreign key: $table.";

                    continue;
                }
                if (! in_array($table, self::TABLES, true) || count($fk['columns']) !== 1 || $fk['foreign_columns'] !== ['id'] || ! in_array($parent, self::TABLES, true)) {
                    $blockers[] = "Unexpected dependency: $table -> $parent.";

                    continue;
                }
                $column = $fk['columns'][0];
                foreach (DB::table($table)->whereNotNull($column)->cursor() as $child) {
                    $childOwned = isset($rows[$table][$child->id]);
                    $parentOwned = isset($rows[$parent][$child->$column]);
                    if ($childOwned !== $parentOwned) {
                        $blockers[] = "Cross-request dependency: $table ID $child->id.";
                    } elseif ($childOwned && $this->owner($table, (array) $child, $rows) !== $this->owner($parent, $rows[$parent][$child->$column], $rows)) {
                        $blockers[] = "Cross-request ownership mismatch: $table ID $child->id.";
                    }
                }
                $orphans = DB::table("$table as child")->leftJoin("$parent as parent", "child.$column", '=', 'parent.id')
                    ->whereNotNull("child.$column")->whereNull('parent.id')->count();
                if ($orphans) {
                    $blockers[] = "Orphan database links: $table.$column ($orphans).";
                }
            }
        }
        ksort($schema);
        foreach (self::TABLES as $table) {
            if ($table === 'requests') {
                continue;
            }
            [$parent, $column] = self::INDIRECT[$table] ?? ['requests', 'request_id'];
            if (! collect($schema[$table]['foreign_keys'] ?? [])->contains(fn ($fk) => $fk['foreign_table'] === $parent && $fk['columns'] === [$column] && $fk['foreign_columns'] === ['id'])) {
                $blockers[] = "Missing ownership foreign key: $table.$column.";
            }
        }
        $this->checkNotificationSources($rows, $blockers);

        return $rows;
    }

    private function owner(string $table, array $row, array $rows): int
    {
        if ($table === 'requests') {
            return (int) $row['id'];
        }
        if (isset(self::INDIRECT[$table])) {
            [$parent, $column] = self::INDIRECT[$table];

            return $this->owner($parent, $rows[$parent][$row[$column]], $rows);
        }

        return (int) $row['request_id'];
    }

    public const FILE_COLUMNS = ['request_documents' => 'file_path', 'request_dispatch_proofs' => 'file_path',
        'request_payment_submissions' => 'proof_path', 'request_final_documents' => 'storage_path',
        'request_revenue_entry_documents' => 'file_path'];

    public function files(array $rows, array &$blockers = [], array $recoveryFiles = []): array
    {
        $candidates = [];
        foreach ($recoveryFiles as $file) {
            $candidates[$file['path']] = ['path' => $file['path'], 'prefixes' => $file['prefixes'], 'orphan' => $file['orphan'], 'owners' => []];
        }
        $directories = [];
        foreach (array_keys($rows['requests']) as $id) {
            $directories[] = "customer-requests/$id/";
            $directories[] = "payment-proofs/$id/";
        }
        foreach (array_keys($rows['request_dispatches']) as $id) {
            $directories[] = "request-dispatch-proofs/$id/";
        }
        foreach (self::FILE_COLUMNS as $table => $column) {
            foreach ($rows[$table] as $row) {
                if (! $path = $row[$column]) {
                    continue;
                }
                $owner = $this->owner($table, $row, $rows);
                $prefixes = ["customer-requests/$owner/", "payment-proofs/$owner/"];
                if ($table === 'request_dispatch_proofs') {
                    $prefixes[] = 'request-dispatch-proofs/'.$row['request_dispatch_id'].'/';
                }
                $candidates[$path] ??= ['path' => $path, 'prefixes' => $prefixes, 'orphan' => false];
                $candidates[$path]['owners'][$owner] = true;
            }
        }
        foreach (['customer-requests/', 'payment-proofs/', 'request-dispatch-proofs/'] as $root) {
            try {
                $this->assertPath($root.'cleanup-path-probe', [$root]);
                foreach (Storage::disk('local')->allFiles($root) as $path) {
                    if (isset($candidates[$path])) {
                        continue;
                    }
                    $prefix = collect($directories)->first(fn ($dir) => str_starts_with($path, $dir));
                    $candidates[$path] = ['path' => $path, 'prefixes' => $prefix ? [$prefix] : [], 'orphan' => true, 'owners' => []];
                }
            } catch (\Throwable) {
                $blockers[] = "Could not safely enumerate private directory: $root";
            }
        }
        ksort($candidates);
        $files = [];
        foreach ($candidates as $file) {
            $path = $file['path'];
            $file += ['hash' => null, 'bytes' => 0, 'exists' => false, 'shared' => false, 'safe' => false];
            $file['shared'] = count($file['owners']) > 1;
            unset($file['owners']);
            try {
                if ($file['prefixes'] === []) {
                    throw new CleanupRefused('Orphan file has no inventoried request owner.');
                }
                $this->assertPath($path, $file['prefixes']);
                $file['safe'] = true;
                foreach (self::FILE_COLUMNS as $table => $column) {
                    foreach (DB::table($table)->where($column, $path)->pluck('id') as $id) {
                        if (! isset($rows[$table][$id])) {
                            $file['shared'] = true;
                        }
                    }
                }
                $file['shared'] = $file['shared'] || $this->referencedBySettings($path);
                $file['exists'] = Storage::disk('local')->exists($path);
                if ($file['exists']) {
                    $file['bytes'] = Storage::disk('local')->size($path);
                    $file['hash'] = hash_file('sha256', Storage::disk('local')->path($path));
                    if (! is_string($file['hash'])) {
                        throw new CleanupRefused('File checksum unavailable.');
                    }
                }
                if ($file['shared']) {
                    throw new CleanupRefused('Shared file requires manual review.');
                }
            } catch (\Throwable $exception) {
                $blockers[] = ($exception instanceof CleanupRefused ? $exception->getMessage() : 'File inspection failed.').' Path fingerprint: '.hash('sha256', $path);
            }
            $files[] = $file;
        }

        return $files;
    }

    public function referencedBySettings(string $path): bool
    {
        foreach (DB::table('settings')->pluck('setting_value') as $value) {
            if (str_contains(str_replace('\\/', '/', (string) $value), $path)) {
                return true;
            }
        }

        return false;
    }

    private function checkNotificationSources(array $rows, array &$blockers): void
    {
        $sources = ['request' => 'requests', 'request_status' => 'requests', 'billing' => 'request_billings',
            'payment' => 'request_payments', 'processing_stage' => 'request_processing_details', 'revenue_entry' => 'request_revenue_entries'];
        foreach (DB::table('customer_notification_events')->orderBy('id')->cursor() as $event) {
            $owned = isset($rows['customer_notification_events'][$event->id]);
            if ($owned && $event->appointment_id !== null) {
                $blockers[] = "Notification event $event->id is shared with a preserved appointment.";

                continue;
            }
            if ($event->source_type === null && $event->source_id === null) {
                if (! $owned && $event->appointment_id === null) {
                    $blockers[] = "Orphan notification event $event->id.";
                }

                continue;
            }
            if ($event->source_type === 'appointment' && ! $owned && $event->appointment_id !== null
                && (int) $event->source_id === (int) $event->appointment_id) {
                continue;
            }
            $parent = $sources[$event->source_type] ?? null;
            if (! $parent || ! isset($rows[$parent][$event->source_id]) || ! $owned
                || $this->owner($parent, $rows[$parent][$event->source_id], $rows) !== (int) $event->request_id) {
                $blockers[] = "Unknown, orphan or cross-owner notification source: event $event->id.";
            }
        }
    }

    public function assertPath(string $path, array $prefixes): void
    {
        if ($prefixes === [] || ! collect($prefixes)->contains(fn ($prefix) => str_starts_with($path, $prefix))) {
            throw new CleanupRefused('File is outside the approved request directories.');
        }
        if (preg_match('/[\\\\\x00-\x1f:]/', $path) || preg_match('~(^|/)\.{1,2}(/|$)~', $path) || str_contains($path, '//')) {
            throw new CleanupRefused('Unsafe private file path.');
        }
        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        if ($root === false) {
            throw new CleanupRefused('Private storage root does not exist.');
        }
        $candidate = $root;
        foreach (explode('/', $path) as $part) {
            $candidate .= DIRECTORY_SEPARATOR.$part;
            if (is_link($candidate)) {
                throw new CleanupRefused('Symlinks are not allowed in cleanup paths.');
            }
        }
        if (file_exists($candidate) && (! is_file($candidate) || ! is_readable($candidate))) {
            throw new CleanupRefused('Cleanup path is not a readable regular file.');
        }
        if (file_exists($candidate)) {
            $resolved = str_replace('\\', '/', realpath($candidate));
            $boundary = rtrim(str_replace('\\', '/', $root), '/').'/';
            if (PHP_OS_FAMILY === 'Windows') {
                $resolved = strtolower($resolved);
                $boundary = strtolower($boundary);
            }
            if (! str_starts_with($resolved, $boundary)) {
                throw new CleanupRefused('Cleanup file resolves outside private storage.');
            }
        }
    }

    public function readJournal(): ?array
    {
        if ($this->hasSelectiveJournal()) {
            throw new CleanupRefused('Selective cleanup history exists; all-scope cleanup is refused.');
        }
        if (Storage::disk('local')->exists('maintenance/test-data-cleanup-2026.json')) {
            throw new CleanupRefused('Legacy cleanup journal exists; reconcile it before using full-scope cleanup.');
        }
        $this->assertPath(self::JOURNAL, ['maintenance/']);
        if (! Storage::disk('local')->exists(self::JOURNAL)) {
            return null;
        }
        $data = json_decode(Storage::disk('local')->get(self::JOURNAL), true);
        if (! is_array($data) || ! isset($data['payload'], $data['mac']) || ! hash_equals($this->sign($data['payload']), $data['mac'])) {
            throw new CleanupRefused('Cleanup recovery journal is invalid; manual review required.');
        }
        $journal = json_decode($data['payload'], true, flags: JSON_THROW_ON_ERROR);
        if (($journal['version'] ?? null) !== self::VERSION) {
            throw new CleanupRefused('Unsupported cleanup journal version.');
        }
        if ($journal['database'] !== $this->databaseIdentity()) {
            throw new CleanupRefused('Recovery journal belongs to another database.');
        }

        return $journal;
    }

    public function writeJournal(array $journal): void
    {
        $this->assertPath(self::JOURNAL, ['maintenance/']);
        $this->assertPath(self::JOURNAL.'.tmp', ['maintenance/']);
        $payload = json_encode($journal, JSON_THROW_ON_ERROR);
        $disk = Storage::disk('local');
        if (! $disk->put(self::JOURNAL.'.tmp', json_encode(['payload' => $payload, 'mac' => $this->sign($payload)], JSON_THROW_ON_ERROR))
            || ! $disk->move(self::JOURNAL.'.tmp', self::JOURNAL)) {
            throw new CleanupRefused('Could not persist recovery journal; keep maintenance enabled.');
        }
    }

    private function sign(string $payload): string
    {
        if (! config('app.key')) {
            throw new CleanupRefused('Application signing key is required.');
        }

        return hash_hmac('sha256', $payload, config('app.key'));
    }

    public function databaseIdentity(): string
    {
        return hash('sha256', json_encode([DB::connection()->getDriverName(), DB::connection()->getConfig('host'), DB::connection()->getDatabaseName()]));
    }
}
