<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentSubmissionRequest;
use App\Models\CustomerRequest;
use App\Services\PaymentSubmissionService;
use Illuminate\Http\RedirectResponse;

class PaymentSubmissionController extends Controller
{
    public function __invoke(StorePaymentSubmissionRequest $request, CustomerRequest $customerRequest, PaymentSubmissionService $submissions): RedirectResponse
    {
        $submissions->submit($customerRequest, $request->validated(), $request->file('proof'));

        return to_route('request.track')->with('success', 'Payment details submitted. Our team will verify the payment.');
    }
}
