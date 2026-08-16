<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use App\Models\RequestFinalDocument;
use App\Services\FinalDocumentDownloadService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrackedFinalDocumentController extends Controller
{
    public function __invoke(Request $request, CustomerRequest $customerRequest, RequestFinalDocument $finalDocument, FinalDocumentDownloadService $downloads): BinaryFileResponse
    {
        $verifiedAt = $request->session()->get('public_tracking.verified_requests.'.$customerRequest->id);
        abort_unless(is_int($verifiedAt) && now()->timestamp - $verifiedAt <= 1800, 404);
        abort_unless($finalDocument->request_id === $customerRequest->id, 404);
        abort_unless($finalDocument->deliveries()->where('request_id', $customerRequest->id)->where('status', 'sent')->exists(), 404);

        return $downloads->download($finalDocument);
    }
}
