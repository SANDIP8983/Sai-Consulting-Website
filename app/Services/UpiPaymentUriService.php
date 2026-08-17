<?php

namespace App\Services;

use App\Models\CustomerRequest;

class UpiPaymentUriService
{
    /** @param array<string, mixed> $paymentOptions */
    public function build(CustomerRequest $request, array $paymentOptions): string
    {
        $note = $request->file_number
            ? $request->reference_no.' / '.$request->file_number
            : $request->reference_no;

        return 'upi://pay?'.http_build_query([
            'pa' => $paymentOptions['upi_id'],
            'pn' => $paymentOptions['payee_name'],
            'am' => number_format((float) $paymentOptions['grand_total'], 2, '.', ''),
            'cu' => 'INR',
            'tn' => $note,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
