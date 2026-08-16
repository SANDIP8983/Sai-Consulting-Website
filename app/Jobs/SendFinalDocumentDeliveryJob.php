<?php

namespace App\Jobs;

use App\Mail\CustomerFinalDocumentsMail;
use App\Models\RequestFinalDocumentDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SendFinalDocumentDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [60, 300, 1200, 3600];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = RequestFinalDocumentDelivery::query()->with(['customerRequest', 'documents'])->findOrFail($this->deliveryId);
        if ($delivery->status === 'sent') {
            return;
        }
        if ($delivery->channel !== 'email') {
            $delivery->update(['status' => 'skipped', 'failure_category' => 'channel_not_available']);

            return;
        }

        $request = $delivery->customerRequest;
        $recipient = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? strtolower($request->email) : null;
        if (! $recipient) {
            $delivery->update(['status' => 'skipped', 'failure_category' => 'missing_or_invalid_recipient']);

            return;
        }
        if (! hash_equals($delivery->recipient_hash, hash('sha256', strtolower($recipient)))) {
            $delivery->update(['status' => 'skipped', 'failure_category' => 'recipient_changed_before_delivery']);

            return;
        }

        $delivery->increment('attempt_count');
        $delivery->update(['last_attempt_at' => now(), 'failure_category' => null, 'failure_message' => null, 'failed_at' => null]);
        $expiresAt = now()->addDays((int) config('final-documents.signed_link_expiration_days'));
        $links = $delivery->documents->map(fn ($document): array => [
            'name' => $document->original_name,
            'url' => URL::temporarySignedRoute('request.final-documents.signed', $expiresAt, [$request, $document]),
        ])->all();

        try {
            Mail::to($recipient)->send(new CustomerFinalDocumentsMail($request, $links));
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'failure_category' => 'transport_failure',
                'failure_message' => Str::limit($exception->getMessage(), 500),
                'failed_at' => now(),
            ]);
            throw $exception;
        }
    }
}
