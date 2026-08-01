<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpenRequestFileRequest;
use App\Http\Requests\Admin\TransitionRequestProcessingStageRequest;
use App\Http\Requests\Admin\UpdateRequestDraftingRequest;
use App\Http\Requests\Admin\UpdateRequestFileInformationRequest;
use App\Http\Requests\Admin\UpdateRequestRegistrationRequest;
use App\Http\Requests\Admin\UpdateRequestPostRegistrationRequest;
use App\Http\Requests\Admin\StoreRegisteredDocumentScanRequest;
use App\Models\CustomerRequest;
use App\Services\FileDocumentProcessingService;
use App\Services\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;

class RequestProcessingController extends Controller
{
    public function __construct(private readonly FileDocumentProcessingService $processing, private readonly RequestWorkflowService $workflow) {}

    public function open(OpenRequestFileRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->processing->open($customerRequest, $request->validated(), $request->user());
        return back()->with('success', 'File processing opened successfully.');
    }

    public function updateFile(UpdateRequestFileInformationRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $attributes = $request->safe()->except('estimated_completion_date');
        $this->processing->updateFileInformation($customerRequest, $attributes, $request->user());
        $this->workflow->updateEstimate($customerRequest, $request->validated('estimated_completion_date'));
        return back()->with('success', 'File information updated successfully.');
    }

    public function updateDrafting(UpdateRequestDraftingRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->processing->updateDrafting($customerRequest, $request->validated(), $request->user());
        return back()->with('success', 'Drafting information updated successfully.');
    }

    public function transition(TransitionRequestProcessingStageRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->processing->transition($customerRequest, $request->validated('processing_stage'), $request->safe()->except('processing_stage'), $request->user());
        return back()->with('success', 'Processing stage updated successfully.');
    }

    public function updateRegistration(UpdateRequestRegistrationRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->processing->updateRegistration($customerRequest, $request->validated(), $request->user());
        return back()->with('success', 'Registration information updated successfully.');
    }

    public function updatePostRegistration(UpdateRequestPostRegistrationRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->processing->updatePostRegistration($customerRequest, $request->validated(), $request->user());
        return back()->with('success', 'Post-registration information updated successfully.');
    }

    public function storeRegisteredScan(StoreRegisteredDocumentScanRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->processing->storeRegisteredScan($customerRequest, $request->file('registered_document'), $request->user());
        return back()->with('success', 'Registered document scan stored privately.');
    }
}
