<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectPaymentSubmissionRequest;
use App\Models\CustomerRequest;
use App\Services\PaymentSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentSubmissionController extends Controller
{
    public function proof(CustomerRequest $customerRequest): BinaryFileResponse|Response
    {
        $submission = $customerRequest->paymentSubmission;
        abort_unless($submission?->proof_path && Storage::disk('local')->exists($submission->proof_path), 404);

        return response()->download(Storage::disk('local')->path($submission->proof_path), $submission->proof_original_name, [
            'Content-Type' => $submission->proof_mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    public function reject(RejectPaymentSubmissionRequest $request, CustomerRequest $customerRequest, PaymentSubmissionService $submissions): RedirectResponse
    {
        $submission = $customerRequest->paymentSubmission()->firstOrFail();
        $submissions->reject($submission, $request->validated('review_note'), $request->user());

        return back()->with('success', 'Customer payment submission rejected. The customer may submit corrected details.');
    }
}
