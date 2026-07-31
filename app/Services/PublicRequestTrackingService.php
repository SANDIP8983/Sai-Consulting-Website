<?php

namespace App\Services;

use App\Models\CustomerRequest;
use Illuminate\Validation\ValidationException;

class PublicRequestTrackingService
{
    public function find(string $referenceNumber, string $mobile): CustomerRequest
    {
        $request = CustomerRequest::query()
            ->select(['id', 'reference_no', 'file_number', 'service_id', 'status', 'payment_status', 'estimated_completion_date', 'last_status_changed_at', 'updated_at'])
            ->where('reference_no', $referenceNumber)
            ->where('mobile', $mobile)
            ->with([
                'service:id,name_en,name_gu',
                'statusHistory' => fn ($query) => $query
                    ->select(['id', 'request_id', 'to_status', 'remarks', 'created_at'])
                    ->where('is_visible_to_customer', true)
                    ->whereNotNull('remarks')
                    ->latest('created_at'),
            ])
            ->first();

        if (! $request) {
            throw ValidationException::withMessages([
                'reference_no' => 'No request matches the reference number and mobile number provided.',
            ]);
        }

        return $request;
    }
}
