<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCustomerRequestsRequest;
use App\Http\Requests\Admin\RecordRequestPaymentRequest;
use App\Http\Requests\Admin\StoreRequestRemarkRequest;
use App\Http\Requests\Admin\TransitionCustomerRequestRequest;
use App\Http\Requests\Admin\UpdateRequestEstimateRequest;
use App\Http\Requests\Admin\UpdateRequestFinalFeeRequest;
use App\Models\CustomerRequest;
use App\Models\Service;
use App\Services\AdminRequestManagementService;
use App\Services\RequestWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CustomerRequestController extends Controller
{
    public function __construct(private readonly AdminRequestManagementService $management, private readonly RequestWorkflowService $workflow) {}
    public function index(FilterCustomerRequestsRequest $request): View
    {
        return view('admin.requests.index', ['requests' => $this->management->paginate($request->validated()), 'services' => Service::query()->orderBy('name_en')->get(['id', 'name_en']), 'statuses' => RequestWorkflowService::STATUSES]);
    }
    public function show(CustomerRequest $customerRequest): View
    {
        return view('admin.requests.show', ['customerRequest' => $this->management->load($customerRequest), 'transitions' => $this->workflow->transitions($customerRequest)]);
    }
    public function transition(TransitionCustomerRequestRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->workflow->transition($customerRequest, $request->validated(), $request->user());
        return back()->with('success', 'Request status updated successfully.');
    }
    public function estimate(UpdateRequestEstimateRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->workflow->updateEstimate($customerRequest, $request->validated('estimated_completion_date'));
        return back()->with('success', 'Estimated completion date updated.');
    }
    public function remark(StoreRequestRemarkRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->workflow->addRemark($customerRequest, $request->validated('remarks'), $request->boolean('is_visible_to_customer'), $request->user());
        return back()->with('success', 'Remark added successfully.');
    }
    public function payment(RecordRequestPaymentRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->workflow->recordPayment($customerRequest, $request->validated(), $request->user());
        return back()->with('success', 'Payment recorded successfully.');
    }
    public function fee(UpdateRequestFinalFeeRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->management->updateFinalFee($customerRequest, (float) $request->validated('final_fee'), $request->user());
        return back()->with('success', 'Final service fee updated successfully.');
    }
}
