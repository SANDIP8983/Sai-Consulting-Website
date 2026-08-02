<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PdfDocumentType;
use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use App\Services\Pdf\PdfGenerationService;
use Illuminate\Http\Response;

class RequestPdfController extends Controller
{
    public function __invoke(CustomerRequest $customerRequest, string $documentType, PdfGenerationService $pdf): Response
    {
        $type = PdfDocumentType::tryFrom($documentType);
        abort_unless($type, 404);

        return $pdf->download($type, $customerRequest);
    }
}
