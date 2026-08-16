<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendRequestFinalDocumentsRequest;
use App\Http\Requests\Admin\UploadRequestFinalDocumentsRequest;
use App\Models\CustomerRequest;
use App\Models\RequestFinalDocument;
use App\Services\FinalDocumentDeliveryService;
use App\Services\FinalDocumentDownloadService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RequestFinalDocumentController extends Controller
{
    public function __construct(private readonly FinalDocumentDeliveryService $deliveries) {}

    public function store(UploadRequestFinalDocumentsRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->deliveries->upload($customerRequest, $request->file('documents'), $request->user());

        return back()->with('success', 'Final customer document(s) uploaded privately.');
    }

    public function destroy(CustomerRequest $customerRequest, RequestFinalDocument $finalDocument): RedirectResponse
    {
        $this->deliveries->delete($customerRequest, $finalDocument);

        return back()->with('success', 'Unsent final document removed.');
    }

    public function send(SendRequestFinalDocumentsRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->deliveries->queueEmail($customerRequest, $request->validated('document_ids'), $request->user());

        return back()->with('success', 'Selected final documents queued for customer email delivery.');
    }

    public function download(CustomerRequest $customerRequest, RequestFinalDocument $finalDocument, FinalDocumentDownloadService $downloads): BinaryFileResponse
    {
        abort_unless($finalDocument->request_id === $customerRequest->id, 404);

        return $downloads->download($finalDocument);
    }
}
