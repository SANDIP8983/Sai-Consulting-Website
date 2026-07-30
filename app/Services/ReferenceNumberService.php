<?php

namespace App\Services;

use App\Models\CustomerRequest;

class ReferenceNumberService
{
    public function generate(): string
    {
        $year = date('Y');

        $lastRequest = CustomerRequest::whereYear('created_at', $year)
            ->latest('id')
            ->first();

        $nextNumber = 1;

        if ($lastRequest) {
            $parts = explode('/', $lastRequest->reference_no);

            $nextNumber = (int) end($parts) + 1;
        }

        return sprintf(
            'SC/%s/%06d',
            $year,
            $nextNumber
        );
    }
}
