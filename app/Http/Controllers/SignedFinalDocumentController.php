<?php

namespace App\Http\Controllers;

use App\Models\CustomerRequest;
use App\Models\RequestFinalDocument;
use App\Services\FinalDocumentDownloadService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SignedFinalDocumentController extends Controller
{
    public function __invoke(CustomerRequest $customerRequest, RequestFinalDocument $finalDocument, FinalDocumentDownloadService $downloads): BinaryFileResponse
    {
        abort_unless($finalDocument->request_id === $customerRequest->id, 404);
        abort_unless($finalDocument->deliveries()->where('request_id', $customerRequest->id)->where('status', 'sent')->exists(), 404);

        return $downloads->download($finalDocument);
    }
}
