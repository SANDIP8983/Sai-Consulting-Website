<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use App\Services\DynamicUpiQrService;
use App\Services\PaymentSubmissionService;
use App\Services\UpiPaymentUriService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class DynamicUpiQrController extends Controller
{
    public function __invoke(
        Request $request,
        CustomerRequest $customerRequest,
        PaymentSubmissionService $payments,
        UpiPaymentUriService $paymentUri,
        DynamicUpiQrService $qrCode,
    ): Response|RedirectResponse {
        $verifiedAt = $request->session()->get('public_tracking.verified_requests.'.$customerRequest->id);
        abort_unless(is_int($verifiedAt) && now()->timestamp - $verifiedAt <= 1800, 403);

        $options = $payments->options($customerRequest);
        abort_unless($options, 404);

        try {
            $svg = $qrCode->render($paymentUri->build($customerRequest, $options));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('payments.upi-qr');
        }

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
