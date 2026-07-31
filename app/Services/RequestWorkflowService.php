<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RequestWorkflowService
{
    public const STATUSES = ['received', 'under_review', 'need_documents', 'approved', 'rejected', 'payment_pending', 'payment_received', 'draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration', 'dispatched', 'completed', 'archived'];

    private const TRANSITIONS = ['received' => ['under_review'], 'under_review' => ['need_documents', 'approved', 'rejected'], 'need_documents' => ['under_review'], 'approved' => ['payment_pending'], 'rejected' => ['archived'], 'payment_pending' => ['payment_received'], 'payment_received' => ['draft_in_progress'], 'draft_in_progress' => ['ready_for_verification'], 'ready_for_verification' => ['customer_approved', 'ready_for_registration'], 'customer_approved' => ['ready_for_registration'], 'ready_for_registration' => ['dispatched', 'completed'], 'dispatched' => ['completed'], 'completed' => ['archived'], 'archived' => []];

    public function __construct(
        private readonly ReferenceNumberService $referenceNumbers,
        private readonly FileNumberService $fileNumbers,
    ) {}

    public function transitions(CustomerRequest $request): array
    {
        return self::TRANSITIONS[$request->status] ?? [];
    }

    /** @param array<string,mixed> $attributes @param array<int,UploadedFile> $files */
    public function submit(array $attributes, array $files): CustomerRequest
    {
        return Cache::lock('requests:reference-number', 10)->block(5, function () use ($attributes, $files): CustomerRequest {
            $storedPaths = [];

            try {
                return DB::transaction(function () use ($attributes, $files, &$storedPaths): CustomerRequest {
                    $service = Service::query()->findOrFail($attributes['service_id']);
                    $request = CustomerRequest::query()->create([
                        ...Arr::only($attributes, ['service_id', 'name', 'mobile', 'email', 'address', 'survey_numbers', 'khata_number', 'details']),
                        'reference_no' => $this->referenceNumbers->generate(),
                        'request_origin' => 'online',
                        'status' => 'received',
                        'amount_due' => $service->service_fee ?? 0,
                        'estimated_completion_date' => $service->estimated_days ? now()->addDays($service->estimated_days)->toDateString() : null,
                        'last_status_changed_at' => now(),
                    ]);

                    $this->history($request, null, 'received', 'Your request has been received.', true, null);

                    foreach ($files as $file) {
                        $path = $file->store("customer-requests/{$request->id}", 'local');

                        if ($path === false) {
                            throw new \RuntimeException('A document could not be stored.');
                        }

                        $storedPaths[] = $path;
                        $request->documents()->create([
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => $path,
                            'file_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(),
                            'source' => 'customer',
                        ]);
                    }

                    return $request->load('service');
                });
            } catch (\Throwable $exception) {
                Storage::disk('local')->delete($storedPaths);

                throw $exception;
            }
        });
    }

    /** @param array<string,mixed> $attributes */
    public function transition(CustomerRequest $request, array $attributes, User $user): void
    {
        $to = $attributes['status'];
        $from = $request->status;
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'This status transition is not allowed.']);
        }
        if ($to === 'dispatched' && ($request->payment_status !== 'received' || ! $request->dispatches()->where('dispatch_status', 'dispatched')->exists())) {
            throw ValidationException::withMessages([
                'status' => 'Use Dispatch Management to record dispatch details before changing this status.',
            ]);
        }
        DB::transaction(function () use ($request, $attributes, $user, $from, $to): void {
            $changes = ['status' => $to, 'last_status_changed_at' => now()];
            if ($to === 'approved' && ! $request->file_number) {
                $this->fileNumbers->assign($request);
            }
            if ($to === 'payment_pending') {
                $changes['payment_status'] = 'pending';
            }
            $request->update($changes);
            $this->history($request, $from, $to, $attributes['remarks'] ?? null, (bool) ($attributes['is_visible_to_customer'] ?? false), $user->id);
        });
    }

    public function updateEstimate(CustomerRequest $request, ?string $date): void
    {
        $request->update(['estimated_completion_date' => $date]);
    }

    public function addRemark(CustomerRequest $request, string $remarks, bool $visible, User $user): void
    {
        DB::transaction(fn () => $this->history($request, $request->status, $request->status, $remarks, $visible, $user->id));
    }

    /** @param array<string,mixed> $attributes */
    public function recordPayment(CustomerRequest $request, array $attributes, User $user): void
    {
        DB::transaction(function () use ($request, $attributes, $user): void {
            $lockedRequest = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $eligibleStatuses = ['approved', 'payment_pending', 'payment_received', 'draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration', 'dispatched', 'completed', 'archived'];
            if (! $lockedRequest->file_number || ! in_array($lockedRequest->status, $eligibleStatuses, true)) {
                throw ValidationException::withMessages(['payment' => 'Payment can only be recorded after approval and file-number assignment.']);
            }

            if (! in_array($attributes['payment_status'], ['pending', 'received', 'failed', 'refunded'], true)) {
                throw ValidationException::withMessages(['payment_status' => 'The selected payment status is invalid.']);
            }

            $allowedMethods = $lockedRequest->isOffline() ? ['upi', 'bank_transfer', 'cheque', 'cash', 'other'] : ['upi', 'bank_transfer', 'other'];
            if (! in_array($attributes['payment_method'], $allowedMethods, true)) {
                throw ValidationException::withMessages(['payment_method' => 'This payment method is not allowed for an online request.']);
            }

            $paymentStatus = $attributes['payment_status'];
            $lockedRequest->payments()->create([...$attributes, 'received_by' => $user->id]);
            $received = (float) $lockedRequest->payments()->where('payment_status', 'received')->sum('amount');
            $refunded = (float) $lockedRequest->payments()->where('payment_status', 'refunded')->sum('amount');
            $changes = ['amount_paid' => max(0, $received - $refunded), 'payment_status' => $paymentStatus];

            if ($paymentStatus === 'pending' && $lockedRequest->status === 'approved') {
                $changes += ['status' => 'payment_pending', 'last_status_changed_at' => now()];
                $this->history($lockedRequest, 'approved', 'payment_pending', null, false, $user->id);
            }
            if ($paymentStatus === 'received' && in_array($lockedRequest->status, ['approved', 'payment_pending'], true)) {
                if ($lockedRequest->status === 'approved') {
                    $this->history($lockedRequest, 'approved', 'payment_pending', null, false, $user->id);
                }
                $changes += ['status' => 'payment_received', 'last_status_changed_at' => now()];
                $this->history($lockedRequest, 'payment_pending', 'payment_received', 'Payment received.', true, $user->id);
            }

            $lockedRequest->update($changes);
            $request->setRawAttributes($lockedRequest->getAttributes(), true);
        });
    }

    private function history(CustomerRequest $request, ?string $from, string $to, ?string $remarks, bool $visible, ?int $userId): void
    {
        $request->statusHistory()->create(['from_status' => $from, 'to_status' => $to, 'remarks' => $remarks, 'is_visible_to_customer' => $visible, 'changed_by' => $userId]);
    }
}
