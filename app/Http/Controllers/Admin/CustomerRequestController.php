<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCustomerRequestsRequest;
use App\Http\Requests\Admin\FinalizeRequestBillingRequest;
use App\Http\Requests\Admin\DecideRequestServiceRequest;
use App\Http\Requests\Admin\RecordRequestPaymentRequest;
use App\Http\Requests\Admin\StoreOfflineCustomerRequestRequest;
use App\Http\Requests\Admin\StoreRequestDispatchRequest;
use App\Http\Requests\Admin\StoreRequestRemarkRequest;
use App\Http\Requests\Admin\TransitionCustomerRequestRequest;
use App\Http\Requests\Admin\UpdateRequestEstimateRequest;
use App\Http\Requests\Admin\UpdateRequestFinalFeeRequest;
use App\Http\Requests\Admin\UnlockRequestBillingRequest;
use App\Models\CustomerRequest;
use App\Models\RequestService;
use App\Models\Service;
use App\Models\User;
use App\Services\AdminRequestManagementService;
use App\Services\DispatchManagementService;
use App\Services\RequestWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CustomerRequestController extends Controller
{
    public function __construct(
        private readonly AdminRequestManagementService $management,
        private readonly RequestWorkflowService $workflow,
        private readonly DispatchManagementService $dispatchManagement,
    ) {}

    public function index(FilterCustomerRequestsRequest $request): View
    {
        return view('admin.requests.index', ['requests' => $this->management->paginate($request->validated()), 'services' => Service::query()->orderBy('name_en')->get(['id', 'name_en']), 'statuses' => RequestWorkflowService::STATUSES]);
    }

    public function create(): View
    {
        return view('admin.requests.create', [
            'services' => Service::query()->where('is_active', true)->where('available_offline', true)->with('activeRequiredDocuments')->orderBy('sort_order')->orderBy('name_en')->get(),
        ]);
    }

    public function store(StoreOfflineCustomerRequestRequest $request): RedirectResponse
    {
        $customerRequest = $this->workflow->submitOffline(
            $request->safe()->except('documents'),
            $request->file('documents', []),
            $request->user(),
        );

        return to_route('admin.requests.show', $customerRequest)
            ->with('success', "Offline request {$customerRequest->reference_no} created successfully.");
    }

    public function show(CustomerRequest $customerRequest): View
    {
        $transitions = array_values(array_filter(
            $this->workflow->transitions($customerRequest),
            fn (string $status): bool => $status !== 'dispatched'
                && (! $customerRequest->processing || ! in_array($status, ['draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration', 'completed'], true)),
        ));

        $customerRequest = $this->management->load($customerRequest);

        return view('admin.requests.show', [
            'customerRequest' => $customerRequest,
            'transitions' => $transitions,
            'processingTransitions' => $customerRequest->processing ? app(\App\Services\FileDocumentProcessingService::class)->transitions($customerRequest->processing) : [],
            'fileInChargeUsers' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function transition(TransitionCustomerRequestRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->workflow->transition($customerRequest, $request->validated(), $request->user());

        return back()->with('success', 'Request status updated successfully.');
    }

    public function decideService(DecideRequestServiceRequest $request, CustomerRequest $customerRequest, RequestService $requestService): RedirectResponse
    {
        $this->workflow->decideService($customerRequest, $requestService, $request->validated(), $request->user());
        return back()->with('success', 'Selected service decision updated successfully.');
    }

    public function finalizeBilling(FinalizeRequestBillingRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->workflow->finalizeRequestBilling($customerRequest, $request->validated(), $request->user());
        return back()->with('success', 'Request billing approved and frozen successfully.');
    }

    public function unlockBilling(UnlockRequestBillingRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->workflow->unlockRequestBilling($customerRequest, $request->validated('unlock_reason'), $request->user());
        return back()->with('success', 'Request billing unlocked. The reason was recorded in audit history.');
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

    public function dispatch(StoreRequestDispatchRequest $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $this->dispatchManagement->record($customerRequest, $request->validated(), $request->user());

        return back()->with('success', 'Dispatch update recorded successfully.');
    }
}
