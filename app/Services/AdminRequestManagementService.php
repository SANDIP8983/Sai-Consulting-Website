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
        return CustomerRequest::query()->with(['service:id,name_en,name_gu', 'requestServices.service:id,name_en,name_gu'])->when($filters['q'] ?? null, function ($query, $term): void {
            $query->where(fn ($query) => $query->where('reference_no', 'like', "%{$term}%")->orWhere('file_number', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('mobile', 'like', "%{$term}%"));
        })->when($filters['source'] ?? null, fn ($q, $v) => $q->where('request_origin', $v))->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))->when($filters['service_id'] ?? null, fn ($q, $v) => $q->where(fn ($query) => $query->where('service_id', $v)->orWhereHas('requestServices', fn ($services) => $services->where('service_id', $v))))->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))->latest()->paginate(20)->withQueryString();
    }

    public function load(CustomerRequest $request): CustomerRequest
    {
        return $request->load([
            'service',
            'requestServices.service',
            'requestServices.decidedBy:id,name',
            'feeUpdatedBy:id,name',
            'documents',
            'statusHistory' => fn ($q) => $q->with('changedBy:id,name')->latest(),
            'payments' => fn ($q) => $q->with('receivedBy:id,name')->latest('received_at'),
            'dispatches' => fn ($q) => $q->with('performedBy:id,name')->latest('dispatch_date'),
            'processing.fileInCharge:id,name',
            'processingHistory' => fn ($q) => $q->with('changedBy:id,name')->latest(),
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
            $lockedRequest->update(['amount_due' => $fee, 'fee_updated_by' => $user->id, 'fee_updated_at' => now()]);
        });
    }
}
