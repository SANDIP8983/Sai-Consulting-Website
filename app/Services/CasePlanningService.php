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
    public function __construct(private readonly FileNumberService $fileNumbers) {}

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
                if ($decision === 'approved' && $scopeIds === [] && $custom === '') {
                    throw ValidationException::withMessages(["services.$id.work_scope_ids" => 'Select at least one work-scope item for each accepted service.']);
                }
                $row->update(['status' => $decision, 'decision_notes' => $note ?: null, 'customer_decision_message' => $input['customer_decision_message'] ?? null, 'decided_by' => $user->id, 'decided_at' => now(), 'approved_at' => $decision === 'approved' ? now() : null, 'rejected_at' => $decision === 'rejected' ? now() : null]);
                $this->syncScopes($row, $decision, $scopeIds, $custom, $input, $user);
                $row->approvalHistory()->create(['request_id' => $locked->id, 'approved_by' => $user->id, 'pricing_snapshot' => ['decision' => $decision, 'work_scope_ids' => $scopeIds, 'custom_work_item' => $custom ?: null], 'action' => 'decision', 'note' => $note ?: null]);
            }
            $from = $locked->status;
            $changes = ['case_planning_version' => 1];
            if ($from === 'received') {
                $changes += ['status' => 'under_review', 'last_status_changed_at' => now()];
            }$locked->update($changes);
            if ($from === 'received') {
                $locked->statusHistory()->create(['from_status' => 'received', 'to_status' => 'under_review', 'remarks' => 'Case planning started.', 'is_visible_to_customer' => true, 'changed_by' => $user->id]);
            }
        });
    }

    public function addService(CustomerRequest $request, int $serviceId, User $user): RequestService
    {
        return DB::transaction(function () use ($request, $serviceId) {
            $locked = CustomerRequest::query()->with('billing')->lockForUpdate()->findOrFail($request->id);
            $this->assertMutable($locked);
            $service = Service::query()->with('activeRequiredDocuments')->where('is_active', true)->findOrFail($serviceId);
            if ($locked->requestServices()->where('service_id', $service->id)->exists()) {
                throw ValidationException::withMessages(['service_id' => 'This service is already part of the request.']);
            }$row = $locked->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en, 'service_name_gu_snapshot' => $service->name_gu, 'professional_fee' => $service->service_fee ?? 0, 'original_professional_fee' => $service->service_fee ?? 0, 'gst_rate' => $service->gst_rate ?? 0, 'government_charges' => 0, 'government_charges_snapshot' => [], 'estimated_days' => $service->estimated_days, 'required_documents_snapshot' => $service->activeRequiredDocuments->map->only(['id', 'name_en', 'name_gu', 'is_mandatory', 'sort_order'])->values()->all(), 'status' => 'under_review']);
            $locked->update(['case_planning_version' => 1]);

            return $row;
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
            if (in_array($locked->status, ['completed', 'archived', 'dispatched'], true)) {
                throw ValidationException::withMessages(['status' => 'This request is already completed or closed.']);
            }$accepted = $locked->requestServices()->where('status', 'approved')->with('workScopes')->get();
            if ($accepted->isEmpty() || $accepted->contains(fn ($s) => $s->workScopes->isEmpty()) || $accepted->flatMap->workScopes->contains(fn ($s) => ! in_array($s->status, ['completed', 'cancelled'], true))) {
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
            if (in_array($locked->status, ['completed', 'dispatched', 'archived'], true)) {
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
        }$items = WorkScopeItem::query()->where('is_active', true)->whereIn('id', $scopeIds)->get()->keyBy('id');
        if ($items->count() !== count($scopeIds)) {
            throw ValidationException::withMessages(['work_scopes' => 'One or more work-scope items are unavailable.']);
        }$retained = [];
        foreach ($scopeIds as $order => $scopeId) {
            $item = $items[$scopeId];
            $scope = $row->workScopes()->updateOrCreate(['work_scope_item_id' => $scopeId], ['name_en_snapshot' => $item->name_en, 'name_gu_snapshot' => $item->name_gu, 'is_custom' => false, 'status' => $input['scope_statuses'][$scopeId] ?? 'pending', 'internal_note' => $input['internal_note'] ?? null, 'display_order' => $order + 1, 'selected_by' => $user->id]);
            $retained[] = $scope->id;
        }if ($custom !== '') {
            $scope = $row->workScopes()->whereNull('work_scope_item_id')->where('is_custom', true)->firstOrNew();
            $scope->fill(['name_en_snapshot' => $custom, 'name_gu_snapshot' => null, 'is_custom' => true, 'status' => $input['custom_status'] ?? 'pending', 'internal_note' => $input['internal_note'] ?? null, 'display_order' => count($retained) + 1, 'selected_by' => $user->id])->save();
            $retained[] = $scope->id;
        }$row->workScopes()->whereNotIn('id', $retained)->delete();
    }

    private function assertMutable(CustomerRequest $request): void
    {
        if (in_array($request->status, ['completed', 'dispatched', 'archived'], true)) {
            throw ValidationException::withMessages(['case' => 'Completed or closed cases cannot be replanned.']);
        }

        // A confirmed payment locks planning through the billing lock. An explicit,
        // audited pricing unlock deliberately makes the case editable again.
        if ($request->billing?->isLocked() || ($request->payment_status === 'received' && ! $request->billing)) {
            throw ValidationException::withMessages(['case' => 'Payment-confirmed pricing is locked. Use the audited Unlock Pricing action before making changes.']);
        }
    }
}
