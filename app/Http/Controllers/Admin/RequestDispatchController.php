<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloseDispatchedCaseRequest;
use App\Http\Requests\Admin\ReopenDispatchRequest;
use App\Http\Requests\Admin\StoreRequestDispatchRequest;
use App\Http\Requests\Admin\TransitionRequestDispatchRequest;
use App\Http\Requests\Admin\UpdateRequestDispatchRequest;
use App\Http\Requests\Admin\UploadRequestDispatchProofRequest;
use App\Models\CustomerRequest;
use App\Models\RequestDispatch;
use App\Models\RequestDispatchProof;
use App\Services\DispatchManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestDispatchController extends Controller
{
    public function __construct(private readonly DispatchManagementService $dispatches) {}

    public function store(StoreRequestDispatchRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->dispatches->create($customerRequest, $request->safe()->except('proof'), $request->user(), $request->file('proof'));
        return back()->with('success', 'Dispatch record created successfully.');
    }

    public function update(UpdateRequestDispatchRequest $request, CustomerRequest $customerRequest, RequestDispatch $dispatch): RedirectResponse
    {
        $this->dispatches->update($customerRequest, $dispatch, $request->validated(), $request->user());
        return back()->with('success', 'Dispatch details updated successfully.');
    }

    public function transition(TransitionRequestDispatchRequest $request, CustomerRequest $customerRequest, RequestDispatch $dispatch): RedirectResponse
    {
        $this->dispatches->transition($customerRequest, $dispatch, $request->validated('dispatch_status'), $request->safe()->except('dispatch_status'), $request->user());
        return back()->with('success', 'Dispatch status updated successfully.');
    }

    public function reopen(ReopenDispatchRequest $request, CustomerRequest $customerRequest, RequestDispatch $dispatch): RedirectResponse
    {
        $this->dispatches->reopenDispatch($customerRequest, $dispatch, $request->validated('reason'), $request->user());
        return back()->with('success', 'Dispatch record reopened with an audit entry.');
    }

    public function uploadProof(UploadRequestDispatchProofRequest $request, CustomerRequest $customerRequest, RequestDispatch $dispatch): RedirectResponse
    {
        $this->dispatches->uploadProof($customerRequest, $dispatch, $request->file('proof'), $request->validated('proof_type'), $request->user());
        return back()->with('success', 'Private dispatch proof uploaded successfully.');
    }

    public function downloadProof(CustomerRequest $customerRequest, RequestDispatch $dispatch, RequestDispatchProof $proof): StreamedResponse
    {
        abort_unless($dispatch->request_id === $customerRequest->id && $proof->request_dispatch_id === $dispatch->id, 404);
        abort_unless(Storage::disk('local')->exists($proof->file_path), 404);
        return Storage::disk('local')->download($proof->file_path, $proof->file_name, ['Content-Type' => $proof->mime_type]);
    }

    public function close(CloseDispatchedCaseRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->dispatches->close($customerRequest, $request->safe()->except('confirmed'), $request->user());
        return back()->with('success', 'Case closed successfully.');
    }

    public function reopenCase(ReopenDispatchRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->dispatches->reopenCase($customerRequest, $request->validated('reason'), $request->user());
        return back()->with('success', 'Closed case reopened with full audit history.');
    }
}
