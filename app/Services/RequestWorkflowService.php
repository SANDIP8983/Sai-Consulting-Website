<?php

namespace App\Services;

use App\Enums\NotificationMilestone;
use App\Models\CustomerRequest;
use App\Models\RequestBilling;
use App\Models\RequestService;
use App\Models\Service;
use App\Models\User;
use App\Services\Notifications\CustomerNotificationService;
use App\Support\PublicDocumentPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestWorkflowService
{
    public const STATUSES = ['received', 'under_review', 'need_documents', 'approved', 'rejected', 'payment_pending', 'payment_received', 'awaiting_staff_assignment', 'in_progress', 'draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration', 'completed', 'dispatched', 'delivered', 'closed', 'archived'];

    private const TRANSITIONS = ['received' => ['under_review'], 'under_review' => ['need_documents', 'approved', 'rejected'], 'need_documents' => ['under_review'], 'approved' => ['payment_pending', 'awaiting_staff_assignment', 'in_progress', 'draft_in_progress', 'ready_for_registration', 'completed'], 'rejected' => ['archived'], 'payment_pending' => ['payment_received'], 'payment_received' => ['awaiting_staff_assignment', 'in_progress', 'draft_in_progress', 'ready_for_registration'], 'awaiting_staff_assignment' => ['in_progress', 'draft_in_progress', 'ready_for_registration'], 'in_progress' => ['completed'], 'draft_in_progress' => ['ready_for_verification'], 'ready_for_verification' => ['customer_approved', 'ready_for_registration'], 'customer_approved' => ['ready_for_registration'], 'ready_for_registration' => ['dispatched', 'completed'], 'completed' => ['dispatched'], 'dispatched' => ['completed', 'delivered'], 'delivered' => ['closed'], 'closed' => [], 'archived' => []];

    public function __construct(
        private readonly ReferenceNumberService $referenceNumbers,
        private readonly FileNumberService $fileNumbers,
        private readonly RequestBillingCalculator $billingCalculator,
        private readonly RequestBillingStateResolver $billingStateResolver,
        private readonly RequestChecklistInitializer $checklistInitializer,
        private readonly RequestDecisionNormalizer $decisionNormalizer,
        private readonly CustomerNotificationService $notifications,
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
                        'case_planning_version' => CustomerRequest::CURRENT_CASE_PLANNING_VERSION,
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
                            'required_documents_snapshot' => $selectedService->activeRequiredDocuments->map->only(['id', 'name_en', 'name_gu', 'requirement_type', 'is_mandatory', 'sort_order'])->values()->all(),
                            'status' => 'received',
                        ]);
                    }

                    $this->history($request, null, 'received', 'Your request has been received.', true, $user?->id);
                    $this->notifications->afterCommit($request, NotificationMilestone::RequestReceived, 'request', $request->id);

                    $uploadedHashes = [];
                    $publicRestrictions = $origin === 'online'
                        ? PublicDocumentPolicy::restrictionsForServices($orderedServices)
                        : null;
                    foreach ($files as $file) {
                        $hash = hash_file('sha256', $file->getRealPath());
                        if (in_array($hash, $uploadedHashes, true)) {
                            throw ValidationException::withMessages(['documents' => 'The same document cannot be uploaded more than once.']);
                        }
                        $uploadedHashes[] = $hash;
                        if ($publicRestrictions) {
                            PublicDocumentPolicy::assertAcceptable($file, $publicRestrictions);
                            $storedName = Str::uuid().'.'.PublicDocumentPolicy::storageExtension($file);
                            $path = $file->storeAs("customer-requests/{$request->id}", $storedName, 'local');
                        } else {
                            $path = $file->store("customer-requests/{$request->id}", 'local');
                        }

                        if ($path === false) {
                            throw new \RuntimeException('A document could not be stored.');
                        }

                        $storedPaths[] = $path;
                        $request->documents()->create([
                            'file_name' => $publicRestrictions
                                ? PublicDocumentPolicy::safeDisplayName($file)
                                : $file->getClientOriginalName(),
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
        $billingState = $this->billingStateResolver->resolve($request);
        if ($to === 'dispatched' && (($requiresPayment && $billingState->paymentStatus !== 'paid') || ! $request->dispatches()->where('dispatch_status', 'dispatched')->exists())) {
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
            if (in_array($lockedRequest->status, ['closed', 'archived'], true)) {
                throw ValidationException::withMessages(['pricing' => 'Closed cases have read-only billing and service decisions.']);
            }
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
                'decided_at' => now(),
            ]);

            if ($decision === 'approved' && $lockedRequest->usesChecklistWorkflow()) {
                $this->checklistInitializer->snapshotConfiguredDefaults($lockedService, $user);
            } elseif ($decision === 'rejected' && $lockedRequest->usesChecklistWorkflow()) {
                $lockedService->workScopes()->delete();
            }
            $lockedRequest->unsetRelation('requestServices');
            $this->decisionNormalizer->normalize($lockedRequest, $user);
        });
    }

    public function finalizeRequestBilling(CustomerRequest $request, array $attributes, User $user): void
    {
        DB::transaction(function () use ($request, $attributes, $user): void {
            $lockedRequest = CustomerRequest::query()->with('billing')->lockForUpdate()->findOrFail($request->id);
            if (in_array($lockedRequest->status, ['closed', 'archived'], true)) {
                throw ValidationException::withMessages(['pricing' => 'Closed cases have read-only billing.']);
            }
            if (! $lockedRequest->billing && $this->billingStateResolver->resolve($lockedRequest)->legacy) {
                throw ValidationException::withMessages(['pricing' => 'Historical paid or frozen pricing is preserved and cannot be converted automatically.']);
            }
            $services = $lockedRequest->requestServices()->where('status', 'approved')->lockForUpdate()->get();
            if ($services->isEmpty()) {
                throw ValidationException::withMessages(['pricing' => 'Approve at least one selected service before freezing billing.']);
            }
            if ($services->contains(fn (RequestService $service): bool => $service->professional_fee === null && $service->original_professional_fee === null)) {
                throw ValidationException::withMessages(['pricing' => 'Every accepted service must have a Professional Fee before billing can be frozen.']);
            }
            if ($lockedRequest->case_planning_version > 0 && $lockedRequest->requestServices()->whereNull('decided_at')->exists()) {
                throw ValidationException::withMessages(['pricing' => 'Record a decision for every selected service before approving the case.']);
            }
            $undecided = $lockedRequest->requestServices()->whereNotIn('status', ['approved', 'rejected'])->pluck('service_name_en_snapshot')->filter()->values();
            if ($undecided->isNotEmpty()) {
                throw ValidationException::withMessages(['pricing' => 'Accept or reject every selected service before freezing billing. Undecided: '.$undecided->implode(', ').'.']);
            }
            if ($lockedRequest->usesChecklistWorkflow() && $services->contains(fn ($service) => ! $service->workScopes()->exists())) {
                throw ValidationException::withMessages(['pricing' => 'Every accepted service requires at least one selected work-scope item.']);
            }
            if ($lockedRequest->billing?->isLocked()) {
                throw ValidationException::withMessages(['pricing' => 'Request pricing is locked. Use the audited Unlock Pricing action before changing it.']);
            }

            $type = $attributes['discount_type'];
            $value = round((float) $attributes['discount_value'], 2);
            if ($type === 'percentage' && $value > 100) {
                throw ValidationException::withMessages(['discount_value' => 'Percentage discount must be between 0 and 100.']);
            }
            $charges = collect($attributes['government_charges'] ?? [])->map(fn ($charge, $index) => ['name' => $charge['name'], 'amount' => round((float) $charge['amount'], 2), 'note' => $charge['note'] ?? null, 'display_order' => (int) ($charge['display_order'] ?? $index)])->values();
            try {
                $calculation = $this->billingCalculator->calculate($services, $type, $value, (float) $attributes['gst_rate'], $charges->all());
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['discount_value' => $exception->getMessage()]);
            }

            $billing = $lockedRequest->billing()->updateOrCreate([], [
                ...$calculation->billingSnapshot(),
                'discount_reason' => $type === 'none' ? null : $attributes['discount_reason'], 'internal_note' => $attributes['internal_note'] ?? null,
                'applied_by' => $user->id, 'applied_at' => now(), 'pricing_locked_at' => now(), 'pricing_unlocked_at' => null, 'pricing_unlocked_by' => null,
            ]);
            $billing->charges()->delete();
            $billing->charges()->createMany($charges->all());
            $billing->history()->create(['request_id' => $lockedRequest->id, 'changed_by' => $user->id, 'action' => 'frozen', 'pricing_snapshot' => $this->requestBillingSnapshot($billing->fresh('charges')), 'reason' => null]);
            $lockedRequest->update(['amount_due' => $calculation->grandTotal, 'payment_status' => $calculation->paymentStatus, 'fee_updated_by' => $user->id, 'fee_updated_at' => now()]);

            if (in_array($lockedRequest->status, ['received', 'need_documents'], true)) {
                $from = $lockedRequest->status;
                $lockedRequest->update(['status' => 'under_review', 'last_status_changed_at' => now()]);
                $this->history($lockedRequest, $from, 'under_review', 'Request moved to final review.', true, $user->id);
            }

            if ($lockedRequest->status === 'under_review') {
                if (! $lockedRequest->file_number) {
                    $this->fileNumbers->assign($lockedRequest);
                }
                $lockedRequest->update(['status' => 'approved', 'case_approved_at' => now(), 'case_approved_by' => $user->id, 'last_status_changed_at' => now()]);
                $this->history($lockedRequest, 'under_review', 'approved', 'Request pricing approved.', true, $user->id);
            }

            if ($calculation->paymentRequired && $lockedRequest->status === 'approved') {
                $lockedRequest->update(['status' => 'payment_pending', 'last_status_changed_at' => now()]);
                $this->history($lockedRequest, 'approved', 'payment_pending', 'Payment summary generated.', true, $user->id);
            } elseif (! $calculation->paymentRequired && $lockedRequest->status === 'approved') {
                $lockedRequest->update(['status' => 'awaiting_staff_assignment', 'last_status_changed_at' => now()]);
                $this->history($lockedRequest, 'approved', 'awaiting_staff_assignment', 'Awaiting staff assignment.', true, $user->id);
            }
            $this->notifications->afterCommit($lockedRequest, NotificationMilestone::Accepted, 'billing', $billing->id);
            if ($calculation->paymentRequired) {
                $this->notifications->afterCommit($lockedRequest, NotificationMilestone::PaymentPending, 'billing', $billing->id);
            }
        });
    }

    public function unlockRequestBilling(CustomerRequest $request, string $reason, User $user): void
    {
        DB::transaction(function () use ($request, $reason, $user): void {
            if (in_array($request->status, ['closed', 'archived'], true)) {
                throw ValidationException::withMessages(['pricing' => 'Closed cases have read-only billing.']);
            }
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
            $lockedRequest = CustomerRequest::query()->with('billing')->lockForUpdate()->findOrFail($request->id);
            $eligibleStatuses = ['approved', 'payment_pending', 'payment_received', 'awaiting_staff_assignment', 'draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration', 'dispatched', 'completed', 'archived'];
            $frozenReviewLifecycle = in_array($lockedRequest->status, ['received', 'under_review', 'need_documents'], true) && $lockedRequest->billing?->isLocked();
            if (! $lockedRequest->file_number || (! in_array($lockedRequest->status, $eligibleStatuses, true) && ! $frozenReviewLifecycle)) {
                throw ValidationException::withMessages(['payment' => 'Payment can only be recorded after approval and file-number assignment.']);
            }

            $billingState = $this->billingStateResolver->resolve($lockedRequest);
            if (! $billingState->legacy && ! $billingState->pricingLocked) {
                throw ValidationException::withMessages(['payment' => 'Approve and freeze request billing before recording payment.']);
            }
            if ($billingState->lifecycle === 'invalid_frozen') {
                throw ValidationException::withMessages(['payment' => 'Payment cannot be recorded because the frozen billing Grand Total is zero while an accepted service has a professional fee. Approve and freeze the request billing again.']);
            }
            if (! $billingState->paymentRequired) {
                throw ValidationException::withMessages(['payment' => 'This frozen billing snapshot does not require payment.']);
            }
            if ($frozenReviewLifecycle) {
                $from = $lockedRequest->status;
                if ($from !== 'under_review') {
                    $this->history($lockedRequest, $from, 'under_review', 'Request moved to final review.', true, $user->id);
                }
                $this->history($lockedRequest, 'under_review', 'approved', 'Request pricing approved.', true, $user->id);
                $this->history($lockedRequest, 'approved', 'payment_pending', 'Payment summary generated.', true, $user->id);
                $lockedRequest->update(['status' => 'payment_pending', 'case_approved_at' => $lockedRequest->case_approved_at ?? now(), 'case_approved_by' => $lockedRequest->case_approved_by ?? $user->id, 'last_status_changed_at' => now()]);
            }
            $payableAmount = (float) $billingState->grandTotal;

            if (! in_array($attributes['payment_status'], ['pending', 'received', 'failed', 'refunded'], true)) {
                throw ValidationException::withMessages(['payment_status' => 'The selected payment status is invalid.']);
            }

            $allowedMethods = $lockedRequest->isOffline() ? ['upi', 'bank_transfer', 'cheque', 'cash', 'other'] : ['upi', 'bank_transfer', 'other'];
            if (! in_array($attributes['payment_method'], $allowedMethods, true)) {
                throw ValidationException::withMessages(['payment_method' => 'This payment method is not allowed for an online request.']);
            }

            $paymentStatus = $attributes['payment_status'];
            $prospectivePaid = $billingState->confirmedPaidAmount;
            if ($paymentStatus === 'received') {
                $prospectivePaid += (float) $attributes['amount'];
            } elseif ($paymentStatus === 'refunded') {
                $prospectivePaid = max(0, $prospectivePaid - (float) $attributes['amount']);
            }
            if ($paymentStatus === 'received' && $prospectivePaid > $payableAmount) {
                throw ValidationException::withMessages(['amount' => 'Confirmed payments cannot exceed the frozen billing Grand Total.']);
            }

            $lockedRequest->payments()->create([...$attributes, 'received_by' => $user->id]);
            $received = (float) $lockedRequest->payments()->where('payment_status', 'received')->sum('amount');
            $refunded = (float) $lockedRequest->payments()->where('payment_status', 'refunded')->sum('amount');
            $netPaid = max(0, $received - $refunded);
            $derivedPayment = $this->billingCalculator->paymentState($payableAmount, $netPaid);
            $storedPaymentStatus = $derivedPayment->status === 'paid' ? 'received' : $derivedPayment->status;
            $changes = ['amount_paid' => $netPaid, 'payment_status' => $storedPaymentStatus];

            if ($paymentStatus === 'pending' && $lockedRequest->status === 'approved') {
                $changes += ['status' => 'payment_pending', 'last_status_changed_at' => now()];
                $this->history($lockedRequest, 'approved', 'payment_pending', null, false, $user->id);
            }
            if ($derivedPayment->status === 'paid') {
                $lockedRequest->requestServices()->where('status', 'approved')->update(['pricing_locked_at' => now(), 'pricing_unlocked_at' => null, 'pricing_unlocked_by' => null]);
                $lockedRequest->billing()->update(['pricing_locked_at' => now(), 'pricing_unlocked_at' => null, 'pricing_unlocked_by' => null]);
                if (in_array($lockedRequest->status, ['approved', 'payment_pending'], true)) {
                    if ($lockedRequest->status === 'approved') {
                        $this->history($lockedRequest, 'approved', 'payment_pending', null, false, $user->id);
                    }
                    $changes['status'] = 'awaiting_staff_assignment';
                    $changes['last_status_changed_at'] = now();
                    $this->history($lockedRequest, 'payment_pending', 'payment_received', 'Payment received.', true, $user->id);
                    $this->history($lockedRequest, 'payment_received', 'awaiting_staff_assignment', 'Awaiting staff assignment.', true, $user->id);
                }
                $this->notifications->afterCommit($lockedRequest, NotificationMilestone::PaymentReceived, 'payment', $lockedRequest->payments()->latest('id')->value('id'));
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
