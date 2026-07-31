<?php

namespace App\Services;

use App\Models\CustomerRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminRequestManagementService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return CustomerRequest::query()->with('service:id,name_en,name_gu')->when($filters['q'] ?? null, function ($query, $term): void {
            $query->where(fn ($query) => $query->where('reference_no', 'like', "%{$term}%")->orWhere('file_number', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")->orWhere('mobile', 'like', "%{$term}%"));
        })->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['payment_status'] ?? null, fn ($q, $v) => $q->where('payment_status', $v))->when($filters['service_id'] ?? null, fn ($q, $v) => $q->where('service_id', $v))->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))->latest()->paginate(20)->withQueryString();
    }

    public function load(CustomerRequest $request): CustomerRequest
    {
        return $request->load(['service', 'documents', 'statusHistory' => fn ($q) => $q->with('changedBy:id,name')->latest(), 'payments' => fn ($q) => $q->with('receivedBy:id,name')->latest('received_at')]);
    }
}
