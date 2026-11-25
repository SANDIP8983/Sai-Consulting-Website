<?php

namespace App\Services;

use App\Exceptions\CleanupRefused;
use App\Jobs\SendCustomerNotificationJob;
use App\Jobs\SendFinalDocumentDeliveryJob;
use App\Mail\AdminNewCustomerRequestMail;
use App\Models\CustomerRequest;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\DB;

/** Inspect serialized metadata without instantiating jobs or restoring models. */
class CleanupQueueInspector
{
    public function inspect(array $requestIds, array $deliveryIds, array $finalDeliveryIds, array &$blockers = []): array
    {
        $connection = config('queue.default');
        if (config("queue.connections.$connection.driver") !== 'database'
            || (config("queue.connections.$connection.connection") ?: config('database.default')) !== config('database.default')
            || config("queue.connections.$connection.table") !== 'jobs'
            || ! in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)
            || config('queue.failed.database') !== config('database.default')
            || config('queue.failed.table') !== 'failed_jobs') {
            throw new CleanupRefused('Cleanup requires database jobs and failed_jobs on the default database; other backends need a reviewed adapter.');
        }

        $result = ['jobs' => [], 'failed_jobs' => []];
        foreach (array_keys($result) as $table) {
            foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
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
                    if ($owned) {
                        if (! empty($fields['batchId'])) {
                            throw new CleanupRefused('Target job belongs to a batch; review batch dependencies before cleanup.');
                        }
                        $result[$table][] = $row->id;
                    }
                } catch (\Throwable $exception) {
                    $blockers[] = $table.' ID '.$row->id.': '.($exception instanceof CleanupRefused ? $exception->getMessage() : 'Queue inspection failed; raw payload suppressed.');
                }
            }
        }

        return $result;
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
