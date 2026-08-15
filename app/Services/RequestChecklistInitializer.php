<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestService;
use App\Models\RequestServiceWorkScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestChecklistInitializer
{
    public function snapshotConfiguredDefaults(RequestService $requestService, ?User $user): int
    {
        $requestService->loadMissing('service.defaultWorkScopes');
        $defaults = $requestService->service?->defaultWorkScopes
            ->filter(fn ($item): bool => $item->is_active && (bool) $item->pivot->is_default)
            ->values() ?? collect();

        if ($requestService->isAddOn() && $defaults->isNotEmpty()) {
            $existingRequestScopeIds = $requestService->request->requestServices()
                ->whereKeyNot($requestService->id)
                ->where('status', 'approved')
                ->whereHas('workScopes')
                ->with('workScopes:id,request_service_id,work_scope_item_id')
                ->get()
                ->flatMap->workScopes
                ->pluck('work_scope_item_id')
                ->filter();
            $defaults = $defaults->whereNotIn('id', $existingRequestScopeIds)->values();
        }

        foreach ($defaults as $order => $item) {
            $requestService->workScopes()->firstOrCreate(
                ['work_scope_item_id' => $item->id],
                [
                    'name_en_snapshot' => $item->name_en,
                    'name_gu_snapshot' => $item->name_gu,
                    'is_custom' => false,
                    'status' => 'pending',
                    'display_order' => (int) ($item->pivot->display_order ?: $order + 1),
                    'selected_by' => $user?->id,
                ],
            );
        }

        if (! $requestService->isAddOn() && $defaults->isNotEmpty()) {
            RequestServiceWorkScope::query()
                ->whereHas('requestService', fn ($query) => $query->where('request_id', $requestService->request_id)->where('is_admin_added', true)->where('status', 'approved'))
                ->whereIn('work_scope_item_id', $defaults->pluck('id'))
                ->delete();
        }

        return $requestService->workScopes()->count();
    }

    public function repairEligible(CustomerRequest $request, User $user): void
    {
        DB::transaction(function () use ($request, $user): void {
            $locked = CustomerRequest::query()->with(['billing', 'requestServices.service.defaultWorkScopes'])->lockForUpdate()->findOrFail($request->id);
            if ($locked->case_planning_version >= CustomerRequest::CURRENT_CASE_PLANNING_VERSION) {
                return;
            }
            if ($locked->created_at->lt(CustomerRequest::CHECKLIST_WORKFLOW_CUTOFF_AT)) {
                throw ValidationException::withMessages(['request' => 'Historical legacy requests cannot be converted to the checklist workflow.']);
            }
            if (in_array($locked->status, ['completed', 'dispatched', 'delivered', 'closed', 'archived'], true)
                || $locked->billing?->isLocked()
                || $locked->payments()->where('payment_status', 'received')->exists()
                || (float) $locked->amount_paid > 0) {
                throw ValidationException::withMessages(['request' => 'Only editable, unpaid requests can be repaired.']);
            }

            foreach ($locked->requestServices->where('status', 'approved') as $requestService) {
                $this->snapshotConfiguredDefaults($requestService, $user);
            }
            $locked->update(['case_planning_version' => CustomerRequest::CURRENT_CASE_PLANNING_VERSION]);
            $locked->caseActionHistory()->create([
                'action' => 'checklist_initialized',
                'performed_by' => $user->id,
                'internal_note' => json_encode(['from_version' => 0, 'to_version' => CustomerRequest::CURRENT_CASE_PLANNING_VERSION], JSON_THROW_ON_ERROR),
            ]);
        });
    }
}
