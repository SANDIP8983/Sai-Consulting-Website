<?php

namespace App\Jobs;

use App\Contracts\WhatsAppChannelInterface;
use App\Mail\CustomerMilestoneMail;
use App\Models\CustomerNotificationDelivery;
use App\Services\Notifications\CustomerMessageFactory;
use App\Services\Notifications\CustomerNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendCustomerNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [60, 300, 1200, 3600];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(CustomerNotificationService $notifications, CustomerMessageFactory $messages, WhatsAppChannelInterface $whatsApp): void
    {
        $delivery = CustomerNotificationDelivery::query()->with('event.customerRequest')->findOrFail($this->deliveryId);
        if (in_array($delivery->status, ['sent', 'skipped'], true)) {
            return;
        }
        $delivery->increment('attempt_count');
        $delivery->update(['last_attempt_at' => now(), 'failure_category' => null, 'failure_message' => null, 'failed_at' => null]);
        $request = $delivery->event->customerRequest;
        $recipient = $notifications->recipient($request, $delivery->channel);
        if (! $recipient) {
            $delivery->update(['status' => 'skipped', 'failure_category' => 'missing_or_invalid_recipient']);

            return;
        }
        if (! hash_equals((string) $delivery->recipient_hash, hash('sha256', strtolower($recipient)))) {
            $delivery->update(['status' => 'skipped', 'failure_category' => 'recipient_changed_before_delivery']);

            return;
        }
        $message = $messages->make($request, $delivery->event->milestone);
        try {
            if ($delivery->channel === 'email') {
                Mail::to($recipient)->send(new CustomerMilestoneMail($message));
                $delivery->update(['status' => 'sent', 'provider' => config('mail.default'), 'sent_at' => now()]);
            } else {
                $result = $whatsApp->send($delivery, $recipient, $message);
                $delivery->update(['status' => $result['status'], 'provider' => $result['provider'], 'provider_message_id' => $result['provider_message_id'], 'failure_category' => $result['failure_category'], 'sent_at' => $result['status'] === 'sent' ? now() : null]);
            }
        } catch (\Throwable $exception) {
            $delivery->update(['status' => 'failed', 'failure_category' => 'transport_failure', 'failure_message' => Str::limit($exception->getMessage(), 500), 'failed_at' => now()]);
            throw $exception;
        }
    }
}
