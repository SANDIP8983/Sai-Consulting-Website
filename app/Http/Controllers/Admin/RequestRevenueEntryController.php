<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRevenueEntryRequest;
use App\Http\Requests\Admin\UpdateRevenueEntryRequirementRequest;
use App\Http\Requests\Admin\UploadRevenueEntryDocumentRequest;
use App\Models\CustomerRequest;
use App\Models\RequestRevenueEntry;
use App\Models\RequestRevenueEntryDocument;
use App\Services\RevenueEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestRevenueEntryController extends Controller
{
    public function __construct(private readonly RevenueEntryService $service) {}

    public function requirement(UpdateRevenueEntryRequirementRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->service->setRequired($customerRequest, $request->boolean('revenue_entry_required'), $request->user());

        return back()->with('success', 'Revenue Entry requirement updated.');
    }

    public function store(SaveRevenueEntryRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->service->create($customerRequest, $request->validated(), $request->user());

        return redirect()->to(route('admin.requests.show', $customerRequest).'#section-revenue-entry')->with('success', 'Revenue Entry added.');
    }

    public function update(SaveRevenueEntryRequest $request, CustomerRequest $customerRequest, RequestRevenueEntry $revenueEntry): RedirectResponse
    {
        $this->service->update($customerRequest, $revenueEntry, $request->validated(), $request->user());

        return redirect()->to(route('admin.requests.show', $customerRequest).'#section-revenue-entry')->with('success', 'Revenue Entry updated.');
    }

    public function upload(UploadRevenueEntryDocumentRequest $request, CustomerRequest $customerRequest, RequestRevenueEntry $revenueEntry): RedirectResponse
    {
        $this->service->upload($customerRequest, $revenueEntry, $request->file('document'), $request->validated('document_type'), $request->user());

        return redirect()->to(route('admin.requests.show', $customerRequest).'#section-revenue-entry')->with('success', 'Supporting document uploaded privately.');
    }

    public function download(CustomerRequest $customerRequest, RequestRevenueEntry $revenueEntry, RequestRevenueEntryDocument $document): StreamedResponse
    {
        abort_unless($revenueEntry->request_id === $customerRequest->id && $document->request_revenue_entry_id === $revenueEntry->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }
}
