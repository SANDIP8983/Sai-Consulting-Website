<?php

namespace App\Services\Notifications;

use App\Enums\NotificationMilestone;
use App\Jobs\SendCustomerNotificationJob;
use App\Models\CustomerNotificationDelivery;
use App\Models\CustomerNotificationEvent;
use App\Models\CustomerRequest;
use App\Models\Setting;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CustomerNotificationService
{
    public function afterCommit(CustomerRequest $request, NotificationMilestone $milestone, ?string $sourceType = null, ?int $sourceId = null, array $safeContext = []): void
    {
        DB::afterCommit(function () use ($request, $milestone, $sourceType, $sourceId, $safeContext): void {
            try {
                $this->record($request->fresh(), $milestone, $sourceType, $sourceId, $safeContext);
            } catch (\Throwable $exception) {
                Log::error('Customer notification event could not be recorded.', ['request_id' => $request->id, 'milestone' => $milestone->value, 'exception' => $exception::class]);
            }
        });
    }

    public function record(CustomerRequest $request, NotificationMilestone $milestone, ?string $sourceType = null, ?int $sourceId = null, array $safeContext = []): CustomerNotificationEvent
    {
        $eventKey = "request:{$request->id}:milestone:{$milestone->value}";
        try {
            $event = CustomerNotificationEvent::query()->create(['request_id' => $request->id, 'milestone' => $milestone, 'event_key' => $eventKey, 'source_type' => $sourceType, 'source_id' => $sourceId, 'safe_context' => $safeContext, 'occurred_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return CustomerNotificationEvent::query()->where('event_key', $eventKey)->sole();
        }

        foreach (['email', 'whatsapp'] as $channel) {
            $enabled = $this->enabled($milestone, $channel);
            $recipient = $this->recipient($request, $channel);
            $delivery = CustomerNotificationDelivery::query()->create([
                'notification_event_id' => $event->id, 'channel' => $channel,
                'status' => $enabled && $recipient ? 'pending' : 'skipped',
                'recipient_masked' => $recipient ? $this->mask($recipient, $channel) : null,
                'recipient_hash' => $recipient ? hash('sha256', strtolower($recipient)) : null,
                'template_key' => 'customer_'.$milestone->value,
                'failure_category' => ! $enabled ? 'channel_disabled' : ($recipient ? null : 'missing_or_invalid_recipient'),
                'queued_at' => $enabled && $recipient ? now() : null,
            ]);
            if ($delivery->status === 'pending') {
                SendCustomerNotificationJob::dispatch($delivery->id)->onQueue(config('customer-notifications.queue'));
            }
        }

        return $event->load('deliveries');
    }

    public function enabled(NotificationMilestone $milestone, string $channel): bool
    {
        $stored = Setting::query()->where('setting_key', "notifications.{$milestone->value}.{$channel}")->value('setting_value');

        return $stored === null ? $milestone->defaults()[$channel] : filter_var($stored, FILTER_VALIDATE_BOOL);
    }

    public function recipient(CustomerRequest $request, string $channel): ?string
    {
        if ($channel === 'email') {
            return filter_var($request->email, FILTER_VALIDATE_EMAIL) ? strtolower($request->email) : null;
        }
        $digits = preg_replace('/\D+/', '', (string) ($request->whatsapp ?: $request->mobile));
        if (strlen($digits) === 10 && preg_match('/^[6-9]/', $digits)) {
            return '91'.$digits;
        }

        return strlen($digits) === 12 && str_starts_with($digits, '91') && preg_match('/^91[6-9]/', $digits) ? $digits : null;
    }

    private function mask(string $recipient, string $channel): string
    {
        if ($channel === 'email') {
            [$local, $domain] = explode('@', $recipient, 2);

            return Str::substr($local, 0, 1).'***@'.$domain;
        }

        return '********'.substr($recipient, -4);
    }
}
