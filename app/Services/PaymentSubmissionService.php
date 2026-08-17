<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestPaymentSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentSubmissionService
{
    public const MAX_PROOF_KB = 5120;

    public function __construct(
        private readonly RequestBillingStateResolver $billingStateResolver,
        private readonly PaymentSettingsService $paymentSettings,
    ) {}

    /** @return array<string, mixed>|null */
    public function options(CustomerRequest $request): ?array
    {
        $settings = $this->paymentSettings->settings();
        $billing = $this->billingStateResolver->resolve($request);

        if (! $settings['enabled'] || ! $settings['upi_id'] || ! $settings['payee_name'] || ! $settings['qr_path']
            || ! Storage::disk('local')->exists($settings['qr_path'])
            || $request->status !== 'payment_pending' || ! $billing->pricingLocked || $billing->paymentStatus === 'paid'
            || $billing->grandTotal === null || $billing->grandTotal <= 0) {
            return null;
        }

        return [
            ...$settings,
            'grand_total' => $billing->grandTotal,
            'amount_to_pay' => max(0, round($billing->grandTotal - $billing->confirmedPaidAmount, 2)),
            'qr_url' => route('payments.upi-qr'),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function submit(CustomerRequest $request, array $attributes, ?UploadedFile $proof): RequestPaymentSubmission
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($request, $attributes, $proof, &$storedPath): RequestPaymentSubmission {
                $lockedRequest = CustomerRequest::query()->with(['billing', 'payments', 'paymentSubmission'])->lockForUpdate()->findOrFail($request->id);
                $options = $this->options($lockedRequest);
                if (! $options || $options['amount_to_pay'] <= 0) {
                    throw ValidationException::withMessages(['payment' => 'This request is not eligible for a UPI payment submission.']);
                }

                $existing = $lockedRequest->paymentSubmission;
                if ($existing && $existing->status !== 'rejected') {
                    throw ValidationException::withMessages(['payment' => 'Payment details have already been submitted for this request.']);
                }

                $proofData = [];
                if ($proof) {
                    $extension = strtolower($proof->guessExtension() ?: $proof->extension());
                    $storedPath = $proof->storeAs('payment-proofs/'.$lockedRequest->id, Str::uuid().'.'.$extension, 'local');
                    $proofData = [
                        'proof_path' => $storedPath,
                        'proof_original_name' => basename($proof->getClientOriginalName()),
                        'proof_mime_type' => $proof->getMimeType(),
                        'proof_file_size' => $proof->getSize(),
                    ];
                }

                $oldPath = $existing?->proof_path;
                $submission = $lockedRequest->paymentSubmission()->updateOrCreate([], [
                    'utr_reference' => trim($attributes['utr_reference']),
                    'amount' => $options['amount_to_pay'],
                    'status' => 'pending',
                    'submitted_at' => now(),
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                    'review_note' => null,
                    'payment_id' => null,
                    ...$proofData,
                ]);

                if ($oldPath && $storedPath && $oldPath !== $storedPath) {
                    Storage::disk('local')->delete($oldPath);
                }

                return $submission;
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }
    }

    public function reject(RequestPaymentSubmission $submission, string $note, User $user): void
    {
        if ($submission->status !== 'pending') {
            throw ValidationException::withMessages(['payment' => 'Only a pending payment submission can be rejected.']);
        }

        $submission->update(['status' => 'rejected', 'reviewed_at' => now(), 'reviewed_by' => $user->id, 'review_note' => $note]);
    }
}
