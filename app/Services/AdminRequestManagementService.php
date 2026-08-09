<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRequestManagementService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return CustomerRequest::query()
            ->when(request()->user()?->role === 'staff', fn ($query) => $query->where('assigned_user_id', request()->user()->id))
            ->with(['service:id,name_en,name_gu', 'requestServices.service:id,name_en,name_gu', 'requestServices.workScopes', 'billing', 'payments', 'dispatches'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('reference_no', 'like', "%{$term}%")->orWhere('file_number', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('mobile', 'like', "%{$term}%")->orWhere('property_village', 'like', "%{$term}%")->orWhere('village', 'like', "%{$term}%")->orWhere('survey_numbers', 'like', "%{$term}%")->orWhere('khata_number', 'like', "%{$term}%")->orWhereHas('requestServices.service', fn ($service) => $service->where('name_en', 'like', "%{$term}%")->orWhere('name_gu', 'like', "%{$term}%"))))
            ->when($filters['village'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('property_village', 'like', "%{$v}%")->orWhere('village', 'like', "%{$v}%")->orWhere('revenue_village', 'like', "%{$v}%")))
            ->when($filters['survey_number'] ?? null, fn ($q, $v) => $q->where('survey_numbers', 'like', "%{$v}%"))
            ->when($filters['khata_number'] ?? null, fn ($q, $v) => $q->where('khata_number', 'like', "%{$v}%"))
            ->when($filters['processing_stage'] ?? null, fn ($q, $v) => $q->whereHas('processing', fn ($processing) => $processing->where('processing_stage', $v)))
            ->when($filters['overdue'] ?? false, fn ($q) => $q->whereDate('estimated_completion_date', '<', today())->whereNotIn('status', ['completed', 'dispatched', 'delivered', 'closed', 'archived', 'rejected']))
            ->when(($filters['queue'] ?? null) === 'pending_approval', fn ($q) => $q->whereIn('status', ['received', 'under_review', 'need_documents']))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('request_origin', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['processing_state'] ?? null, function ($q, $v): void {
                if ($v === 'not_started') {
                    $q->whereHas('requestServices.workScopes', fn ($s) => $s->where('status', 'pending'))->whereDoesntHave('requestServices.workScopes', fn ($s) => $s->whereIn('status', ['in_progress', 'completed', 'not_required', 'cancelled']));
                }
                if ($v === 'in_progress') {
                    $q->whereHas('requestServices.workScopes', fn ($s) => $s->whereIn('status', ['pending', 'in_progress']))->whereHas('requestServices.workScopes', fn ($s) => $s->whereIn('status', ['in_progress', 'completed', 'not_required', 'cancelled']));
                }
                if ($v === 'ready') {
                    $q->whereHas('requestServices.workScopes')->whereDoesntHave('requestServices.workScopes', fn ($s) => $s->whereIn('status', ['pending', 'in_progress']))->whereNotIn('status', ['completed', 'dispatched', 'delivered', 'closed']);
                }
                if ($v === 'completed') {
                    $q->whereIn('status', ['completed', 'dispatched', 'delivered', 'closed']);
                }
            })
            ->when($filters['dispatch_state'] ?? null, function ($q, $state): void {
                if ($state === 'pending') {
                    $q->where('status', 'completed')->whereDoesntHave('dispatches', fn ($d) => $d->whereIn('dispatch_status', ['dispatched', 'in_transit', 'delivered', 'collected']));
                }
                if ($state === 'dispatched') {
                    $q->whereHas('dispatches', fn ($d) => $d->where('dispatch_status', 'dispatched'));
                }
                if ($state === 'in_transit') {
                    $q->whereHas('dispatches', fn ($d) => $d->where('dispatch_status', 'in_transit'));
                }
                if ($state === 'delivered') {
                    $q->whereHas('dispatches', fn ($d) => $d->whereIn('dispatch_status', ['delivered', 'collected']));
                }
                if ($state === 'ready_to_close') {
                    $q->where('status', 'delivered')->whereHas('dispatches', fn ($d) => $d->whereIn('dispatch_status', ['delivered', 'collected']))->whereDoesntHave('dispatches', fn ($d) => $d->whereIn('dispatch_status', ['prepared', 'not_dispatched', 'dispatched', 'in_transit', 'failed_returned']));
                }
                if ($state === 'closed') {
                    $q->where('status', 'closed');
                }
                if ($state === 'failed_returned') {
                    $q->whereHas('dispatches', fn ($d) => $d->where('dispatch_status', 'failed_returned'));
                }
            })
            ->when($filters['service_id'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('service_id', $v)->orWhereHas('requestServices', fn ($s) => $s->where('service_id', $v))))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()->paginate(20)->withQueryString();
    }

    public function load(CustomerRequest $request): CustomerRequest
    {
        return $request->load([
            'service',
            'requestServices.service',
            'requestServices.decidedBy:id,name',
            'requestServices.addedBy:id,name',
            'requestServices.approvalHistory' => fn ($q) => $q->with('approvedBy:id,name')->latest(),
            'requestServices.workScopes.updatedBy:id,name',
            'requestServices.workScopes.history' => fn ($q) => $q->with('changedBy:id,name')->latest(),
            'requestServices.service.defaultWorkScopes',
            'billing.charges',
            'billing.appliedBy:id,name',
            'billing.unlockedBy:id,name',
            'billing.history' => fn ($q) => $q->with('changedBy:id,name')->latest(),
            'feeUpdatedBy:id,name',
            'documents',
            'statusHistory' => fn ($q) => $q->with('changedBy:id,name')->latest(),
            'payments' => fn ($q) => $q->with('receivedBy:id,name')->latest('received_at'),
            'dispatches' => fn ($q) => $q->with(['performedBy:id,name', 'updatedBy:id,name', 'proofs', 'history.changedBy:id,name'])->latest('dispatch_date'),
            'processing.fileInCharge:id,name',
            'assignedUser:id,name,role,is_active',
            'assignedBy:id,name',
            'assignmentHistory' => fn ($q) => $q->with(['previousAssignee:id,name', 'assignee:id,name', 'assignedBy:id,name'])->latest('assigned_at'),
            'contactChangeHistory' => fn ($q) => $q->with('changedBy:id,name')->latest('changed_at'),
            'processingHistory' => fn ($q) => $q->with('changedBy:id,name')->latest(),
            'caseActionHistory' => fn ($q) => $q->with('performedBy:id,name')->latest(),
        ]);
    }

    public function updateFinalFee(CustomerRequest $request, float $fee, User $user): void
    {
        DB::transaction(function () use ($request, $fee, $user): void {
            $lockedRequest = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $eligibleStatuses = ['approved', 'payment_pending', 'payment_received', 'draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration', 'dispatched', 'completed', 'archived'];
            if (! $lockedRequest->file_number || ! in_array($lockedRequest->status, $eligibleStatuses, true)) {
                throw ValidationException::withMessages(['final_fee' => 'Final fee can only be set after approval and file-number assignment.']);
            }
            if ($lockedRequest->billing()->exists()) {
                throw ValidationException::withMessages(['final_fee' => 'This request has a billing snapshot. Update its billing summary instead of changing the payment amount.']);
            }
            if ($lockedRequest->billing()->whereNotNull('pricing_locked_at')->whereNull('pricing_unlocked_at')->exists() || $lockedRequest->requestServices()->whereNotNull('pricing_locked_at')->exists()) {
                throw ValidationException::withMessages(['final_fee' => 'Finalized pricing must be changed per service using the explicit Unlock action.']);
            }
            $lockedRequest->update(['amount_due' => $fee, 'fee_updated_by' => $user->id, 'fee_updated_at' => now()]);
        });
    }
}
