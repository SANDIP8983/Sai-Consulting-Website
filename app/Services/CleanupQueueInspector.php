<?php

namespace App\Services;

use App\Exceptions\CleanupRefused;
use App\Jobs\SendCustomerNotificationJob;
use App\Jobs\SendFinalDocumentDeliveryJob;
use App\Mail\AdminNewCustomerRequestMail;
use App\Models\CustomerRequest;
use Illuminate\Console\Application;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\DB;

/** Inspect serialized metadata without instantiating jobs or restoring models. */
class CleanupQueueInspector
{
    public const TABLES = ['jobs', 'failed_jobs'];

    public function inspect(array $requestIds, array $deliveryIds, array $finalDeliveryIds, array &$blockers = [], bool $allowSync = false, array $absentTables = []): array
    {
        $connection = config('queue.default');
        $driver = config("queue.connections.$connection.driver");
        // Sync runs inline, but explicit database dispatches and older jobs can still persist.
        $sync = $driver === 'sync' && $allowSync;
        $persistent = $sync ? 'database' : $connection;
        $defaultDatabase = config('database.default');
        $jobsDatabase = config("queue.connections.$persistent.connection");
        // Full cleanup with inherited sync queue storage requires drained persistent tables.
        $requireEmpty = $sync && $allowSync && $jobsDatabase === null;
        if (! ($driver === 'database' || $sync)
            || config("queue.connections.$persistent.driver") !== 'database'
            || ($jobsDatabase ?? $defaultDatabase) !== $defaultDatabase
            || config("queue.connections.$persistent.table") !== 'jobs'
            || ! in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)
            || config('queue.failed.database') !== $defaultDatabase
            || config('queue.failed.table') !== 'failed_jobs') {
            throw new CleanupRefused('Cleanup requires database jobs and failed_jobs on the default database; other backends need a reviewed adapter.');
        }

        $result = array_fill_keys(self::TABLES, []);
        foreach (array_keys($result) as $table) {
            if ($sync && $allowSync && in_array($table, $absentTables, true)) {
                $this->assertTableAbsent($table);

                continue;
            }
            foreach (DB::table($table)->useWritePdo()->orderBy('id')->cursor() as $row) {
                try {
                    if ($table === 'jobs' && $row->reserved_at !== null) {
                        throw new CleanupRefused('Reserved jobs exist. Stop workers and resolve in-flight jobs before cleanup.');
                    }
                    $payload = json_decode($row->payload, true);
                    $serialized = $payload['data']['command'] ?? null;
                    if (! is_array($payload) || ! is_string($serialized)) {
                        throw new CleanupRefused('Uninspectable queue payload found; cleanup refused.');
                    }
                    // allowed_classes=false prevents __wakeup, __unserialize and model restoration.
                    $object = @unserialize($serialized, ['allowed_classes' => false]);
                    if (! $object instanceof \__PHP_Incomplete_Class) {
                        throw new CleanupRefused('Encrypted or invalid queue command found; cleanup refused.');
                    }
                    $fields = $this->fields($object);
                    $this->assertNoChains($object);
                    if ($sync) {
                        if (! empty($fields['batchId'])) {
                            throw new CleanupRefused('Batched queue work requires explicit review; cleanup refused.');
                        }
                        $allowed = [$connection, $persistent];
                        if (($table === 'failed_jobs' && ! in_array($row->connection, $allowed, true))
                            || (! empty($fields['connection']) && ! in_array($fields['connection'], $allowed, true))) {
                            throw new CleanupRefused('Unreviewed queue connection; cleanup refused.');
                        }
                    }
                    $class = $fields['__PHP_Incomplete_Class_Name'] ?? '';
                    $owned = false;
                    if (in_array($class, [SendCustomerNotificationJob::class, SendFinalDocumentDeliveryJob::class], true)) {
                        if (! is_int($fields['deliveryId'] ?? null)) {
                            throw new CleanupRefused('Unexpected notification job structure.');
                        }
                        $owned = in_array($fields['deliveryId'], $class === SendCustomerNotificationJob::class ? $deliveryIds : $finalDeliveryIds, true);
                        $deliveryTable = $class === SendCustomerNotificationJob::class ? 'customer_notification_deliveries' : 'request_final_document_deliveries';
                        if (! $owned && ! DB::table($deliveryTable)->where('id', $fields['deliveryId'])->exists()) {
                            throw new CleanupRefused('Orphan notification job; delivery no longer exists.');
                        }
                    } elseif ($class === SendQueuedMailable::class) {
                        $mail = $this->fields($fields['mailable'] ?? null);
                        if (($mail['__PHP_Incomplete_Class_Name'] ?? '') === AdminNewCustomerRequestMail::class) {
                            $model = $this->fields($mail['customerRequest'] ?? null);
                            if (($model['class'] ?? '') !== CustomerRequest::class || ! is_int($model['id'] ?? null)
                                || ($model['connection'] ?? config('database.default')) !== config('database.default')) {
                                throw new CleanupRefused('Unexpected admin notification model identifier.');
                            }
                            $owned = in_array($model['id'], $requestIds, true);
                            if (! $owned && ! DB::table('requests')->where('id', $model['id'])->exists()) {
                                throw new CleanupRefused('Orphan admin email job; request no longer exists.');
                            }
                        } elseif ($this->containsRequest($fields)) {
                            throw new CleanupRefused('Unrecognized request-related mail found; cleanup refused.');
                        }
                    } elseif ($this->containsRequest($fields)) {
                        throw new CleanupRefused('Unrecognized request-related job found; cleanup refused.');
                    }
                    if ($owned && ! empty($fields['batchId'])) {
                        throw new CleanupRefused('Target job belongs to a batch; review batch dependencies before cleanup.');
                    }
                    if ($requireEmpty) {
                        throw new CleanupRefused('Sync cleanup with the default queue database requires empty jobs and failed_jobs tables.');
                    }
                    if ($owned) {
                        $result[$table][] = $row->id;
                    }
                } catch (\Throwable $exception) {
                    $blockers[] = $table.' ID '.$row->id.': '.($exception instanceof CleanupRefused ? $exception->getMessage() : 'Queue inspection failed; raw payload suppressed.');
                }
            }
        }

        return $result;
    }

    public function inspectSyncRuntime(array $schema, array $absentTables): string
    {
        if (DB::connection()->getTablePrefix() !== ''
            || config('queue.batching.driver', 'database') !== 'database'
            || config('queue.batching.database') !== config('database.default')
            || config('queue.batching.table') !== 'job_batches') {
            throw new CleanupRefused('Sync cleanup requires reviewed local queue and batch storage.');
        }
        if (! isset($schema['job_batches'])) {
            $this->assertTableAbsent('job_batches');
            $absentTables[] = 'job_batches';
        }

        if (isset($schema['job_batches']) && DB::table('job_batches')->useWritePdo()
            ->where(fn ($query) => $query->whereNull('pending_jobs')->orWhere('pending_jobs', '<>', 0))->exists()) {
            throw new CleanupRefused('Pending batched work exists; cleanup refused.');
        }
        // Inline and after-commit work can leave a pending delivery without a queue row.
        foreach (['customer_notification_deliveries', 'request_final_document_deliveries'] as $table) {
            if (DB::table($table)->useWritePdo()->where(fn ($query) => $query->whereNull('status')
                ->orWhereNotIn('status', ['sent', 'skipped', 'failed']))->exists()) {
                throw new CleanupRefused('Pending or unreviewed notification delivery exists in '.$table.'; verify and reconcile delivery state before cleanup.');
            }
        }

        $schedule = [];
        $allowed = array_map(Application::formatCommandString(...), ['notifications:prune', 'appointments:send-reminders']);
        foreach (app(Schedule::class)->events() as $event) {
            $state = (array) $event;
            if (get_class($event) !== Event::class || ! in_array($event->command, $allowed, true)
                || $event->runsInMaintenanceMode() || $event->runInBackground || $event->repeatSeconds !== null
                || $event->user !== null || $event->mutexNameResolver !== null || $state["\0*\0beforeCallbacks"] !== []
                || $state["\0*\0afterCallbacks"] !== [] || $state["\0*\0filters"] !== []
                || ! $this->hasOnlyOverlapRejects($state["\0*\0rejects"])) {
                throw new CleanupRefused('Unreviewed scheduled work exists; cleanup refused.');
            }
            $schedule[] = [$event->command, $event->expression, $event->timezone, $event->environments,
                $event->withoutOverlapping, $event->onOneServer, $event->expiresAt];
        }
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            && DB::table('information_schema.EVENTS')->useWritePdo()->where('EVENT_SCHEMA', DB::connection()->getDatabaseName())
                ->where('STATUS', 'ENABLED')->exists()) {
            throw new CleanupRefused('Enabled database scheduler events exist; explicit review is required.');
        }

        return hash('sha256', json_encode([config('queue.default'), config('queue.connections'), config('queue.failed'),
            config('queue.batching'), $schedule, $absentTables], JSON_THROW_ON_ERROR));
    }

    private function hasOnlyOverlapRejects(array $rejects): bool
    {
        $overlap = new \ReflectionMethod(Event::class, 'withoutOverlapping');
        foreach ($rejects as $reject) {
            if (! $reject instanceof \Closure) {
                return false;
            }
            $callback = new \ReflectionFunction($reject);
            if ($callback->getFileName() !== $overlap->getFileName()
                || $callback->getStartLine() < $overlap->getStartLine() || $callback->getEndLine() > $overlap->getEndLine()) {
                return false;
            }
        }

        return true;
    }

    private function assertTableAbsent(string $table): void
    {
        // A metadata entry hidden by permissions is not proof of absence.
        try {
            DB::table($table)->useWritePdo()->limit(0)->get(['id']);
        } catch (QueryException $exception) {
            $driver = DB::connection()->getDriverName();
            if ((in_array($driver, ['mysql', 'mariadb'], true) && ($exception->errorInfo[0] ?? null) === '42S02'
                    && ($exception->errorInfo[1] ?? null) === 1146)
                || ($driver === 'sqlite' && ($exception->errorInfo[2] ?? null) === 'no such table: '.$table)) {
                return;
            }

            throw new CleanupRefused('Could not verify absence of queue storage: '.$table.'.');
        }

        throw new CleanupRefused('Unreviewed queue storage exists: '.$table.'.');
    }

    private function fields(mixed $object): array
    {
        $fields = [];
        foreach ((array) $object as $key => $value) {
            $parts = explode("\0", (string) $key);
            $fields[end($parts)] = $value;
        }

        return $fields;
    }

    private function assertNoChains(mixed $value, int $depth = 0): void
    {
        if ($depth > 30) {
            throw new CleanupRefused('Queue metadata nesting exceeds the audit limit.');
        }
        if (! is_array($value) && ! is_object($value)) {
            return;
        }
        foreach ($this->fields($value) as $key => $child) {
            if (in_array($key, ['chained', 'chainCatchCallbacks'], true) && ! empty($child)) {
                throw new CleanupRefused('Chained queue work requires explicit review; cleanup refused.');
            }
            $this->assertNoChains($child, $depth + 1);
        }
    }

    private function containsRequest(mixed $value, int $depth = 0): bool
    {
        if ($depth > 30) {
            throw new CleanupRefused('Queue metadata nesting exceeds the audit limit.');
        }
        if (is_string($value)) {
            return str_starts_with($value, 'App\\Models\\Request') || $value === CustomerRequest::class;
        }
        if (is_array($value) || is_object($value)) {
            foreach ((array) $value as $key => $child) {
                if (preg_match('/(?:^|\x00)(?:requestId|request_id|customerRequestId|customer_request_id|deliveryId)$/', (string) $key)) {
                    return true;
                }
                if ($this->containsRequest($child, $depth + 1)) {
                    return true;
                }
            }
        }

        return false;
    }
}
