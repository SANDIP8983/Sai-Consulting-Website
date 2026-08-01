<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\RequestService;
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

    private const TRANSITIONS = ['received' => ['under_review'], 'under_review' => ['need_documents', 'approved', 'rejected'], 'need_documents' => ['under_review'], 'approved' => ['payment_pending', 'draft_in_progress', 'ready_for_registration', 'completed'], 'rejected' => ['archived'], 'payment_pending' => ['payment_received'], 'payment_received' => ['draft_in_progress', 'ready_for_registration'], 'draft_in_progress' => ['ready_for_verification'], 'ready_for_verification' => ['customer_approved', 'ready_for_registration'], 'customer_approved' => ['ready_for_registration'], 'ready_for_registration' => ['dispatched', 'completed'], 'dispatched' => ['completed'], 'completed' => ['archived'], 'archived' => []];

    public function __construct(
        private readonly ReferenceNumberService $referenceNumbers,
        private readonly FileNumberService $fileNumbers,
    ) {}

    public function transitions(CustomerRequest $request): array
    {
        $transitions = self::TRANSITIONS[$request->status] ?? [];
        if ($request->status === 'approved' && $request->service?->requires_payment_before_processing) {
            return array_values(array_intersect($transitions, ['payment_pending']));
        }
        if (! ($request->processing?->requires_dispatch ?? $request->service?->requires_dispatch ?? true)) {
            $transitions = array_values(array_diff($transitions, ['dispatched']));
        }

        return $transitions;
    }

    /** @param array<string,mixed> $attributes @param array<int,UploadedFile> $files */
    public function submit(array $attributes, array $files): CustomerRequest
    {
        return $this->createRequest($attributes, $files, 'online', 'customer', null);
    }

    /** @param array<string,mixed> $attributes @param array<int,UploadedFile> $files */
    public function submitOffline(array $attributes, array $files, User $user): CustomerRequest
    {
        return $this->createRequest($attributes, $files, 'offline', 'admin', $user);
    }

    /** @param array<string,mixed> $attributes @param array<int,UploadedFile> $files */
    private function createRequest(array $attributes, array $files, string $origin, string $documentSource, ?User $user): CustomerRequest
    {
        return Cache::lock('requests:reference-number', 10)->block(5, function () use ($attributes, $files, $origin, $documentSource, $user): CustomerRequest {
            $storedPaths = [];

            try {
                return DB::transaction(function () use ($attributes, $files, $origin, $documentSource, $user, &$storedPaths): CustomerRequest {
                    $serviceIds = array_values(array_unique(array_map('intval', $attributes['service_ids'] ?? [$attributes['service_id']])));
                    $services = Service::query()->with(['activeRequiredDocuments', 'activeGovernmentChargeItems'])->whereIn('id', $serviceIds)->get()->keyBy('id');
                    $availabilityColumn = $origin === 'offline' ? 'available_offline' : 'available_online';
                    if ($services->count() !== count($serviceIds) || collect($serviceIds)->contains(fn ($id) => ! $services[$id]->is_active || ! $services[$id]->{$availabilityColumn})) {
                        throw ValidationException::withMessages(['service_ids' => 'One or more selected services are not available for this request channel.']);
                    }
                    $orderedServices = collect($serviceIds)->map(fn ($id) => $services[$id]);
                    $service = $orderedServices->first();
                    $chargeItems = fn (Service $item) => $item->activeGovernmentChargeItems->isNotEmpty()
                        ? $item->activeGovernmentChargeItems
                        : collect((float) $item->government_charges > 0 ? [['name' => 'Government Charges', 'amount' => (float) $item->government_charges, 'description' => null]] : []);
                    $governmentTotal = fn (Service $item): float => (float) $chargeItems($item)->sum(fn ($charge) => (float) data_get($charge, 'amount'));
                    $amountDue = $orderedServices->sum(fn (Service $item) => (float) $item->service_fee + ((float) $item->service_fee * (float) $item->gst_rate / 100) + $governmentTotal($item));
                    $estimatedDays = $orderedServices->max('estimated_days');
                    $fingerprint = $origin === 'online' ? hash('sha256', implode('|', [strtolower(trim((string) ($attributes['name'] ?? ''))), $attributes['mobile'] ?? '', implode(',', $serviceIds), now()->format('Y-m-d-H-i')])) : null;
                    if ($fingerprint && CustomerRequest::query()->where('submission_fingerprint', $fingerprint)->exists()) {
                        throw ValidationException::withMessages(['request' => 'This request was already submitted. Please use the reference number from the success page.']);
                    }
                    $request = CustomerRequest::query()->create([
                        ...Arr::only($attributes, ['service_id', 'name', 'mobile', 'whatsapp', 'email', 'address', 'village', 'taluka', 'district', 'survey_numbers', 'khata_number', 'details']),
                        'service_id' => $service->id,
                        'reference_no' => $this->referenceNumbers->generate(),
                        'submission_fingerprint' => $fingerprint,
                        'address' => $attributes['address'] ?? collect([$attributes['village'] ?? null, $attributes['taluka'] ?? null, $attributes['district'] ?? null])->filter()->implode(', '),
                        'request_origin' => $origin,
                        'status' => 'received',
                        'amount_due' => $amountDue,
                        'estimated_completion_date' => $estimatedDays ? now()->addDays($estimatedDays)->toDateString() : null,
                        'last_status_changed_at' => now(),
                    ]);

                    foreach ($orderedServices as $selectedService) {
                        $selectedCharges = $chargeItems($selectedService)->map(fn ($charge) => ['name' => data_get($charge, 'name'), 'amount' => (float) data_get($charge, 'amount'), 'description' => data_get($charge, 'description')])->values()->all();
                        $request->requestServices()->create([
                            'service_id' => $selectedService->id,
                            'professional_fee' => $selectedService->service_fee ?? 0,
                            'gst_rate' => $selectedService->gst_rate ?? 0,
                            'government_charges' => $governmentTotal($selectedService),
                            'government_charges_snapshot' => $selectedCharges,
                            'estimated_days' => $selectedService->estimated_days,
                            'required_documents_snapshot' => $selectedService->activeRequiredDocuments->map->only(['id', 'name_en', 'name_gu', 'is_mandatory', 'sort_order'])->values()->all(),
                            'status' => 'received',
                        ]);
                    }

                    $this->history($request, null, 'received', 'Your request has been received.', true, $user?->id);

                    $uploadedHashes = [];
                    foreach ($files as $file) {
                        $hash = hash_file('sha256', $file->getRealPath());
                        if (in_array($hash, $uploadedHashes, true)) {
                            throw ValidationException::withMessages(['documents' => 'The same document cannot be uploaded more than once.']);
                        }
                        $uploadedHashes[] = $hash;
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
                            'source' => $documentSource,
                        ]);
                    }

                    return $request->load(['service', 'requestServices.service']);
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
        if (! in_array($to, $this->transitions($request), true)) {
            throw ValidationException::withMessages(['status' => 'This status transition is not allowed.']);
        }
        $requiresPayment = $request->processing?->requires_payment_before_processing ?? $request->service?->requires_payment_before_processing ?? true;
        if ($to === 'dispatched' && (($requiresPayment && $request->payment_status !== 'received') || ! $request->dispatches()->where('dispatch_status', 'dispatched')->exists())) {
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

    public function decideService(CustomerRequest $request, RequestService $requestService, array $attributes, User $user): void
    {
        if ($requestService->request_id !== $request->id) {
            abort(404);
        }
        DB::transaction(function () use ($requestService, $attributes, $user): void {
            $decision = $attributes['decision'];
            $requestService->update([
                'status' => $decision,
                'approved_at' => $decision === 'approved' ? now() : null,
                'rejected_at' => $decision === 'rejected' ? now() : null,
                'decision_notes' => $attributes['decision_notes'] ?? null,
                'decided_by' => $user->id,
            ]);
        });
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
