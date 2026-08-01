<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestBilling;
use App\Models\RequestService;
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
                        ...Arr::only($attributes, ['service_id', 'name', 'mobile', 'whatsapp', 'email', 'address', 'property_village', 'property_taluka', 'property_district', 'property_address_remarks', 'survey_numbers', 'khata_number', 'tp_number', 'final_plot_number', 'revenue_village', 'details']),
                        'service_id' => $service->id,
                        'reference_no' => $this->referenceNumbers->generate(),
                        'submission_fingerprint' => $fingerprint,
                        'address' => $attributes['address'] ?? null,
                        'request_origin' => $origin,
                        'status' => 'received',
                        'amount_due' => $amountDue,
                        'estimated_completion_date' => $estimatedDays ? now()->addDays($estimatedDays)->toDateString() : null,
                        'last_status_changed_at' => now(),
                    ]);

                    foreach ($orderedServices as $selectedService) {
                        $request->requestServices()->create([
                            'service_id' => $selectedService->id,
                            'service_name_en_snapshot' => $selectedService->name_en,
                            'service_name_gu_snapshot' => $selectedService->name_gu,
                            'professional_fee' => $selectedService->service_fee ?? 0,
                            'original_professional_fee' => $selectedService->service_fee ?? 0,
                            'gst_rate' => $selectedService->gst_rate ?? 0,
                            'government_charges' => 0,
                            'government_charges_snapshot' => [],
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
        DB::transaction(function () use ($request, $requestService, $attributes, $user): void {
            $decision = $attributes['decision'];
            $lockedRequest = CustomerRequest::query()->with('billing')->lockForUpdate()->findOrFail($request->id);
            if ($lockedRequest->billing?->isLocked()) {
                throw ValidationException::withMessages(['pricing' => 'Request pricing is frozen. Use the audited Unlock Pricing action before changing service decisions.']);
            }
            if (! $lockedRequest->billing && $lockedRequest->requestServices()->whereNotNull('pricing_locked_at')->exists()) {
                throw ValidationException::withMessages(['pricing' => 'This request uses preserved legacy frozen pricing and cannot be recalculated.']);
            }
            $lockedService = RequestService::query()->lockForUpdate()->findOrFail($requestService->id);
            $lockedService->update([
                'status' => $decision,
                'approved_at' => $decision === 'approved' ? now() : null,
                'rejected_at' => $decision === 'rejected' ? now() : null,
                'decision_notes' => $attributes['decision_notes'] ?? null,
                'decided_by' => $user->id,
            ]);

        });
    }

    public function finalizeRequestBilling(CustomerRequest $request, array $attributes, User $user): void
    {
        DB::transaction(function () use ($request, $attributes, $user): void {
            $lockedRequest = CustomerRequest::query()->with('billing')->lockForUpdate()->findOrFail($request->id);
            if (! $lockedRequest->billing && ($lockedRequest->payment_status === 'received' || $lockedRequest->requestServices()->whereNotNull('pricing_locked_at')->exists())) {
                throw ValidationException::withMessages(['pricing' => 'Historical paid or frozen pricing is preserved and cannot be converted automatically.']);
            }
            $services = $lockedRequest->requestServices()->where('status', 'approved')->lockForUpdate()->get();
            if ($services->isEmpty()) {
                throw ValidationException::withMessages(['pricing' => 'Approve at least one selected service before freezing billing.']);
            }
            if ($lockedRequest->case_planning_version > 0 && $lockedRequest->requestServices()->whereNull('decided_at')->exists()) {
                throw ValidationException::withMessages(['pricing' => 'Record a decision for every selected service before approving the case.']);
            }
            if ($lockedRequest->requestServices()->whereNotIn('status', ['approved', 'rejected', 'under_review'])->exists()) {
                throw ValidationException::withMessages(['pricing' => 'Decide every selected service before approving the case.']);
            }
            if ($lockedRequest->case_planning_version > 0 && $services->contains(fn ($service) => ! $service->workScopes()->exists())) {
                throw ValidationException::withMessages(['pricing' => 'Every accepted service requires at least one selected work-scope item.']);
            }
            if ($lockedRequest->billing?->isLocked()) {
                throw ValidationException::withMessages(['pricing' => 'Request pricing is locked. Use the audited Unlock Pricing action before changing it.']);
            }

            $original = round((float) $services->sum('professional_fee'), 2);
            $type = $attributes['discount_type'];
            $value = round((float) $attributes['discount_value'], 2);
            if ($type === 'percentage' && $value > 100) {
                throw ValidationException::withMessages(['discount_value' => 'Percentage discount must be between 0 and 100.']);
            }
            $discount = match ($type) {
                'fixed' => $value, 'percentage' => round($original * $value / 100, 2), default => 0.0
            };
            if ($discount > $original) {
                throw ValidationException::withMessages(['discount_value' => 'Discount cannot exceed the total original professional fee.']);
            }
            $net = round($original - $discount, 2);
            if ($net < 0) {
                throw ValidationException::withMessages(['discount_value' => 'Net professional fee cannot be negative.']);
            }
            $gstRate = round((float) $attributes['gst_rate'], 2);
            $gst = round($net * $gstRate / 100, 2);
            $charges = collect($attributes['government_charges'] ?? [])->map(fn ($charge, $index) => ['name' => $charge['name'], 'amount' => round((float) $charge['amount'], 2), 'note' => $charge['note'] ?? null, 'display_order' => (int) ($charge['display_order'] ?? $index)])->values();
            $government = round((float) $charges->sum('amount'), 2);
            $grandTotal = round($net + $gst + $government, 2);

            $billing = $lockedRequest->billing()->updateOrCreate([], [
                'total_original_professional_fee' => $original, 'discount_type' => $type, 'discount_value' => $type === 'none' ? 0 : $value,
                'discount_amount' => $discount, 'discount_reason' => $type === 'none' ? null : $attributes['discount_reason'], 'internal_note' => $attributes['internal_note'] ?? null,
                'net_professional_fee' => $net, 'gst_rate' => $gstRate, 'gst_amount' => $gst, 'government_charges_total' => $government,
                'grand_total' => $grandTotal, 'applied_by' => $user->id, 'applied_at' => now(), 'pricing_locked_at' => $lockedRequest->case_planning_version > 0 ? null : now(), 'pricing_unlocked_at' => null, 'pricing_unlocked_by' => null,
            ]);
            $billing->charges()->delete();
            $billing->charges()->createMany($charges->all());
            $billing->history()->create(['request_id' => $lockedRequest->id, 'changed_by' => $user->id, 'action' => $lockedRequest->case_planning_version > 0 ? 'saved' : 'frozen', 'pricing_snapshot' => $this->requestBillingSnapshot($billing->fresh('charges')), 'reason' => null]);
            $lockedRequest->update(['amount_due' => $grandTotal, 'fee_updated_by' => $user->id, 'fee_updated_at' => now()]);

            if ($lockedRequest->status === 'under_review') {
                if (! $lockedRequest->file_number) {
                    $this->fileNumbers->assign($lockedRequest);
                }
                $lockedRequest->update(['status' => 'approved', 'case_approved_at' => now(), 'case_approved_by' => $user->id, 'last_status_changed_at' => now()]);
                $this->history($lockedRequest, 'under_review', 'approved', 'Request pricing approved.', true, $user->id);
                $lockedRequest->update(['status' => 'payment_pending', 'payment_status' => 'pending', 'last_status_changed_at' => now()]);
                $this->history($lockedRequest, 'approved', 'payment_pending', 'Payment summary generated.', true, $user->id);
            }
        });
    }

    public function unlockRequestBilling(CustomerRequest $request, string $reason, User $user): void
    {
        DB::transaction(function () use ($request, $reason, $user): void {
            $billing = $request->billing()->with('charges')->lockForUpdate()->first();
            if (! $billing || ! $billing->isLocked()) {
                throw ValidationException::withMessages(['pricing' => 'Request pricing is not currently locked.']);
            }
            $billing->history()->create(['request_id' => $request->id, 'changed_by' => $user->id, 'action' => 'unlocked', 'pricing_snapshot' => $this->requestBillingSnapshot($billing), 'reason' => $reason]);
            $billing->update(['pricing_unlocked_at' => now(), 'pricing_unlocked_by' => $user->id]);
        });
    }

    private function requestBillingSnapshot(RequestBilling $billing): array
    {
        return [...$billing->only(['total_original_professional_fee', 'discount_type', 'discount_value', 'discount_amount', 'discount_reason', 'internal_note', 'net_professional_fee', 'gst_rate', 'gst_amount', 'government_charges_total', 'grand_total', 'applied_by', 'applied_at', 'pricing_locked_at']), 'government_charges' => $billing->charges->map->only(['name', 'amount', 'note', 'display_order'])->all()];
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
                $lockedRequest->requestServices()->where('status', 'approved')->update(['pricing_locked_at' => now(), 'pricing_unlocked_at' => null, 'pricing_unlocked_by' => null]);
                $lockedRequest->billing()->update(['pricing_locked_at' => now(), 'pricing_unlocked_at' => null, 'pricing_unlocked_by' => null]);
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
