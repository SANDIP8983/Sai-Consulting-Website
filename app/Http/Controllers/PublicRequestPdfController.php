<?php

namespace App\Http\Controllers;

use App\Enums\PdfDocumentType;
use App\Models\CustomerRequest;
use App\Services\Pdf\PdfGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicRequestPdfController extends Controller
{
    public function __invoke(Request $request, CustomerRequest $customerRequest, string $documentType, PdfGenerationService $pdf): Response
    {
        $verifiedAt = $request->session()->get('public_tracking.verified_requests.'.$customerRequest->id);
        abort_unless(is_int($verifiedAt) && now()->timestamp - $verifiedAt <= 1800, 404);

        $type = PdfDocumentType::tryFrom($documentType);
        abort_unless($type, 404);
        abort_unless($type->isCustomerAvailable($customerRequest), 404);

        return $pdf->download($type, $customerRequest);
    }
}
