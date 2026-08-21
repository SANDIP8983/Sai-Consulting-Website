<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestService;
use App\Models\RequestServiceWorkScope;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkScopeItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CasePlanningService
{
    public function __construct(
        private readonly FileNumberService $fileNumbers,
        private readonly RequestBillingStateResolver $billingStateResolver,
        private readonly RequestDecisionNormalizer $decisionNormalizer,
    ) {}

    public function save(CustomerRequest $request, array $services, User $user): void
    {
        DB::transaction(function () use ($request, $services, $user): void {
            $locked = CustomerRequest::query()->with('billing')->lockForUpdate()->findOrFail($request->id);
            $this->assertMutable($locked);
            $rows = $locked->requestServices()->lockForUpdate()->get()->keyBy('id');
            if (count($services) !== $rows->count()) {
                throw ValidationException::withMessages(['services' => 'Submit a decision for every selected service.']);
            }
            foreach ($services as $id => $input) {
                $row = $rows->get((int) $id);
                if (! $row) {
                    throw ValidationException::withMessages(['services' => 'A submitted service does not belong to this request.']);
                }$decision = $input['decision'];
                $note = trim((string) ($input['decision_notes'] ?? ''));
                $scopeIds = array_values(array_unique(array_map('intval', $input['work_scope_ids'] ?? [])));
                $custom = trim((string) ($input['custom_work_item'] ?? ''));
                if ($decision === 'rejected' && $note === '') {
                    throw ValidationException::withMessages(["services.$id.decision_notes" => 'A rejection reason is required.']);
                }
                if ($decision === 'approved' && ! $row->isAddOn() && $scopeIds === [] && $custom === '') {
                    throw ValidationException::withMessages(["services.$id.work_scope_ids" => 'Select at least one work-scope item for each accepted service.']);
                }
                $row->update(['status' => $decision, 'decision_notes' => $note ?: null, 'customer_decision_message' => $input['customer_decision_message'] ?? null, 'decided_by' => $user->id, 'decided_at' => now(), 'approved_at' => $decision === 'approved' ? now() : null, 'rejected_at' => $decision === 'rejected' ? now() : null]);
                $this->syncScopes($row, $decision, $scopeIds, $custom, $input, $user);
                $row->approvalHistory()->create(['request_id' => $locked->id, 'approved_by' => $user->id, 'pricing_snapshot' => ['decision' => $decision, 'work_scope_ids' => $scopeIds, 'custom_work_item' => $custom ?: null], 'action' => 'decision', 'note' => $note ?: null]);
            }
            $this->removeDuplicateAddOnScopes($locked);
            $from = $locked->status;
            $locked->unsetRelation('requestServices');
            $this->decisionNormalizer->normalize($locked, $user);
            $changes = ['case_planning_version' => CustomerRequest::CURRENT_CASE_PLANNING_VERSION];
            if ($from === 'received' && $locked->status !== 'rejected') {
                $changes += ['status' => 'under_review', 'last_status_changed_at' => now()];
            }$locked->update($changes);
            if ($from === 'received' && $locked->status !== 'rejected') {
                $locked->statusHistory()->create(['from_status' => 'received', 'to_status' => 'under_review', 'remarks' => 'Case planning started.', 'is_visible_to_customer' => true, 'changed_by' => $user->id]);
            }
        });
    }

    public function addService(CustomerRequest $request, int $serviceId, float $professionalFee, ?string $internalNote, User $user): RequestService
    {
        return DB::transaction(function () use ($request, $serviceId, $professionalFee, $internalNote, $user) {
            $locked = CustomerRequest::query()->with('billing')->lockForUpdate()->findOrFail($request->id);
            $this->assertMutable($locked);
            $service = Service::query()->with('activeRequiredDocuments')->where('is_active', true)->findOrFail($serviceId);
            $configured = $locked->requestServices()->where('is_admin_added', false)->whereHas('service.activeAvailableAddOns', fn ($query) => $query->whereKey($service->id))->exists();
            if (! $configured) {
                throw ValidationException::withMessages(['service_id' => 'This additional paid service is not configured for the selected base services.']);
            }
            if ($locked->requestServices()->where('service_id', $service->id)->exists()) {
                throw ValidationException::withMessages(['service_id' => 'This service is already part of the request.']);
            }
            $this->assertAdjustmentReason($professionalFee, (float) ($service->service_fee ?? 0), $internalNote);
            $row = $locked->requestServices()->create(['service_id' => $service->id, 'added_by' => $user->id, 'is_admin_added' => true, 'service_name_en_snapshot' => $service->name_en, 'service_name_gu_snapshot' => $service->name_gu, 'professional_fee' => round($professionalFee, 2), 'original_professional_fee' => $service->service_fee ?? 0, 'gst_rate' => $service->gst_rate ?? 0, 'government_charges' => 0, 'government_charges_snapshot' => [], 'estimated_days' => $service->estimated_days, 'required_documents_snapshot' => $service->activeRequiredDocuments->map->only(['id', 'name_en', 'name_gu', 'requirement_type', 'is_mandatory', 'sort_order'])->values()->all(), 'status' => 'under_review', 'internal_note' => $internalNote]);
            $row->approvalHistory()->create(['request_id' => $locked->id, 'approved_by' => $user->id, 'pricing_snapshot' => ['default_professional_fee' => (float) ($service->service_fee ?? 0), 'professional_fee' => round($professionalFee, 2), 'gst_rate' => (float) ($service->gst_rate ?? 0)], 'action' => 'added', 'note' => $internalNote]);
            $locked->update(['case_planning_version' => CustomerRequest::CURRENT_CASE_PLANNING_VERSION]);

            return $row;
        });
    }

    public function updateServiceFee(CustomerRequest $request, RequestService $requestService, float $professionalFee, ?string $internalNote, User $user): void
    {
        DB::transaction(function () use ($request, $requestService, $professionalFee, $internalNote, $user): void {
            $locked = CustomerRequest::query()->with('billing')->lockForUpdate()->findOrFail($request->id);
            $this->assertMutable($locked);
            $row = $locked->requestServices()->whereKey($requestService->id)->lockForUpdate()->firstOrFail();
            $this->assertAdjustmentReason($professionalFee, (float) ($row->original_professional_fee ?? $row->professional_fee ?? 0), $internalNote);
            $previousFee = $row->professional_fee;
            $row->update(['professional_fee' => round($professionalFee, 2), 'internal_note' => $internalNote]);
            $row->approvalHistory()->create(['request_id' => $locked->id, 'approved_by' => $user->id, 'pricing_snapshot' => ['previous_professional_fee' => (float) $previousFee, 'professional_fee' => round($professionalFee, 2), 'default_professional_fee' => (float) ($row->original_professional_fee ?? 0), 'gst_rate' => (float) $row->gst_rate], 'action' => 'fee_updated', 'note' => $internalNote]);
        });
    }

    public function removeService(CustomerRequest $request, RequestService $requestService): void
    {
        DB::transaction(function () use ($request, $requestService): void {
            $locked = CustomerRequest::query()->with('billing')->lockForUpdate()->findOrFail($request->id);
            $this->assertMutable($locked);
            $row = $locked->requestServices()->whereKey($requestService->id)->lockForUpdate()->firstOrFail();
            if (! $row->isAddOn()) {
                throw ValidationException::withMessages(['service' => 'Only an additional paid service can be removed from the request.']);
            }
            $row->delete();
        });
    }

    public function rejectCase(CustomerRequest $request, User $user): void
    {
        DB::transaction(function () use ($request, $user): void {
            $locked = CustomerRequest::query()->with('billing')->lockForUpdate()->findOrFail($request->id);
            $this->assertMutable($locked);
            if ($locked->requestServices()->where('status', '!=', 'rejected')->exists()) {
                throw ValidationException::withMessages(['case' => 'Every service must be rejected before rejecting the case.']);
            }$from = $locked->status;
            $locked->update(['status' => 'rejected', 'case_approved_at' => now(), 'case_approved_by' => $user->id, 'last_status_changed_at' => now()]);
            $locked->statusHistory()->create(['from_status' => $from, 'to_status' => 'rejected', 'remarks' => 'No selected services were accepted.', 'is_visible_to_customer' => true, 'changed_by' => $user->id]);
        });
    }

    public function complete(CustomerRequest $request, ?string $remarks, User $user): void
    {
        DB::transaction(function () use ($request, $remarks, $user): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (in_array($locked->status, ['completed', 'dispatched', 'delivered', 'closed', 'archived'], true)) {
                throw ValidationException::withMessages(['status' => 'This request is already completed or closed.']);
            }$accepted = $locked->requestServices()->where('status', 'approved')->with('workScopes')->get();
            if ($accepted->isEmpty() || $accepted->contains(fn ($s) => ! $s->isAddOn() && $s->workScopes->isEmpty()) || $accepted->flatMap->workScopes->contains(fn ($s) => ! in_array($s->status, ['completed', 'not_required', 'cancelled'], true))) {
                throw ValidationException::withMessages(['work_scopes' => 'All selected work-scope items must be Completed or Cancelled / Not Required.']);
            }$from = $locked->status;
            $locked->update(['status' => 'completed', 'last_status_changed_at' => now()]);
            $locked->statusHistory()->create(['from_status' => $from, 'to_status' => 'completed', 'remarks' => $remarks ?: 'All planned work is complete.', 'is_visible_to_customer' => true, 'changed_by' => $user->id]);
        });
    }

    public function updateScope(CustomerRequest $request, RequestServiceWorkScope $scope, array $attributes): void
    {
        DB::transaction(function () use ($request, $scope, $attributes): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (in_array($locked->status, ['completed', 'dispatched', 'delivered', 'closed', 'archived'], true)) {
                throw ValidationException::withMessages(['status' => 'Completed or closed cases cannot be changed.']);
            }
            $row = RequestServiceWorkScope::query()->whereKey($scope->id)->whereHas('requestService', fn ($query) => $query->where('request_id', $locked->id)->where('status', 'approved'))->lockForUpdate()->firstOrFail();
            $status = $attributes['status'];
            $row->update(['status' => $status, 'internal_note' => $attributes['internal_note'] ?? $row->internal_note, 'completed_at' => $status === 'completed' ? now() : null]);
            if (in_array($locked->status, ['approved', 'payment_pending', 'payment_received'], true) && in_array($status, ['in_progress', 'completed'], true)) {
                $locked->update(['status' => 'draft_in_progress', 'last_status_changed_at' => now()]);
            }
        });
    }

    private function syncScopes(RequestService $row, string $decision, array $scopeIds, string $custom, array $input, User $user): void
    {
        if ($decision !== 'approved') {
            $row->workScopes()->delete();

            return;
        }
        if ($row->isAddOn() && $scopeIds !== []) {
            $existingRequestScopeIds = RequestServiceWorkScope::query()
                ->whereHas('requestService', fn ($query) => $query->where('request_id', $row->request_id)->whereKeyNot($row->id)->where('status', 'approved'))
                ->whereNotNull('work_scope_item_id')
                ->pluck('work_scope_item_id')
                ->all();
            $scopeIds = array_values(array_diff($scopeIds, $existingRequestScopeIds));
        }
        $items = WorkScopeItem::query()->where('is_active', true)->whereIn('id', $scopeIds)->get()->keyBy('id');
        if ($items->count() !== count($scopeIds)) {
            throw ValidationException::withMessages(['work_scopes' => 'One or more work-scope items are unavailable.']);
        }$retained = [];
        foreach ($scopeIds as $order => $scopeId) {
            $item = $items[$scopeId];
            $existing = $row->workScopes()->where('work_scope_item_id', $scopeId)->first();
            $scope = $row->workScopes()->updateOrCreate(['work_scope_item_id' => $scopeId], ['name_en_snapshot' => $item->name_en, 'name_gu_snapshot' => $item->name_gu, 'is_custom' => false, 'status' => $existing?->status ?? 'pending', 'internal_note' => $input['internal_note'] ?? $existing?->internal_note, 'display_order' => $order + 1, 'selected_by' => $user->id]);
            $retained[] = $scope->id;
        }if ($custom !== '') {
            $scope = $row->workScopes()->whereNull('work_scope_item_id')->where('is_custom', true)->firstOrNew();
            $scope->fill(['name_en_snapshot' => $custom, 'name_gu_snapshot' => null, 'is_custom' => true, 'status' => $input['custom_status'] ?? 'pending', 'internal_note' => $input['internal_note'] ?? null, 'display_order' => count($retained) + 1, 'selected_by' => $user->id])->save();
            $retained[] = $scope->id;
        }$row->workScopes()->whereNotIn('id', $retained)->delete();
    }

    private function assertMutable(CustomerRequest $request): void
    {
        if (in_array($request->status, ['completed', 'dispatched', 'delivered', 'closed', 'archived'], true)) {
            throw ValidationException::withMessages(['case' => 'Completed or closed cases cannot be replanned.']);
        }

        // A confirmed payment locks planning through the billing lock. An explicit,
        // audited pricing unlock deliberately makes the case editable again.
        if ($this->billingStateResolver->resolve($request)->pricingLocked) {
            throw ValidationException::withMessages(['case' => 'Payment-confirmed pricing is locked. Use the audited Unlock Pricing action before making changes.']);
        }
    }

    private function assertAdjustmentReason(float $finalFee, float $baseFee, ?string $reason): void
    {
        if (round($finalFee, 2) !== round($baseFee, 2) && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['internal_note' => 'An adjustment reason is required when the request fee differs from the default fee.']);
        }
    }

    private function removeDuplicateAddOnScopes(CustomerRequest $request): void
    {
        $baseScopeIds = RequestServiceWorkScope::query()
            ->whereHas('requestService', fn ($query) => $query->where('request_id', $request->id)->where('is_admin_added', false)->where('status', 'approved'))
            ->whereNotNull('work_scope_item_id')
            ->pluck('work_scope_item_id');

        if ($baseScopeIds->isNotEmpty()) {
            RequestServiceWorkScope::query()
                ->whereHas('requestService', fn ($query) => $query->where('request_id', $request->id)->where('is_admin_added', true)->where('status', 'approved'))
                ->whereIn('work_scope_item_id', $baseScopeIds)
                ->delete();
        }
    }
}
