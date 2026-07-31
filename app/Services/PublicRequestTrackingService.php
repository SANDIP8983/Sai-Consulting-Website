<?php

namespace App\Services;

use App\Models\CustomerRequest;
use Illuminate\Validation\ValidationException;

class PublicRequestTrackingService
{
    public function find(string $trackingNumber, string $mobile): CustomerRequest
    {
        $request = CustomerRequest::query()
            ->select(['id', 'reference_no', 'file_number', 'service_id', 'name', 'status', 'payment_status', 'estimated_completion_date', 'last_status_changed_at', 'updated_at'])
            ->where(fn ($query) => $query->where('reference_no', $trackingNumber)->orWhere('file_number', $trackingNumber))
            ->where('mobile', $mobile)
            ->with([
                'service:id,name_en,name_gu',
                'service.requiredDocuments:id,service_id,name_en,name_gu,sort_order',
                'statusHistory' => fn ($query) => $query
                    ->select(['id', 'request_id', 'to_status', 'remarks', 'created_at'])
                    ->where('is_visible_to_customer', true)
                    ->latest('created_at'),
            ])
            ->first();

        if (! $request) {
            throw ValidationException::withMessages([
                'reference_no' => 'No request matches the reference/file number and mobile number provided.',
            ]);
        }

        return $request;
    }
}
