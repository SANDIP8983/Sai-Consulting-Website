<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequestRequest;
use App\Http\Requests\TrackCustomerRequestRequest;
use App\Models\Service;
use App\Services\HomepageService;
use App\Services\PaymentSubmissionService;
use App\Services\PublicRequestTrackingService;
use App\Services\RequestWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerRequestController extends Controller
{
    public function create(): View
    {
        $services = Service::query()->visibleOnPublicWebsite()->where('available_online', true)->with(['activeRequiredDocuments', 'activeGovernmentChargeItems'])
            ->orderBy('sort_order')->orderBy('name_en')->get();

        return view('frontend.request.create', compact('services'));
    }

    public function store(StoreCustomerRequestRequest $request, RequestWorkflowService $workflow): RedirectResponse
    {
        $customerRequest = $workflow->submit(
            $request->safe()->except(['document_uploads', 'declaration']),
            $request->file('document_uploads', []),
        );

        return redirect()->route('request.success')->with('submitted_request', [
            'reference_no' => $customerRequest->reference_no,
            'services' => $customerRequest->requestServices->map(fn ($item) => ['name_en' => $item->service->name_en, 'name_gu' => $item->service->name_gu, 'status' => $item->status])->all(),
            'status' => $customerRequest->status,
            'estimated_days' => $customerRequest->requestServices->max('estimated_days') ?? $customerRequest->service->estimated_days,
            'estimated_completion_date' => $customerRequest->estimated_completion_date?->toDateString(),
        ]);
    }

    public function success(): View|RedirectResponse
    {
        return session()->has('submitted_request')
            ? view('frontend.request.success')
            : redirect()->route('request.create');
    }

    public function track(Request $request, PublicRequestTrackingService $tracking, HomepageService $homepage, PaymentSubmissionService $payments): View
    {
        $verified = $request->session()->get('public_tracking.last_verified');
        $customerRequest = null;
        if (is_array($verified) && isset($verified['reference_no'], $verified['mobile'], $verified['verified_at']) && now()->timestamp - $verified['verified_at'] <= 1800) {
            $customerRequest = $tracking->find($verified['reference_no'], $verified['mobile']);
        }

        return view('frontend.request.track', [
            'customerRequest' => $customerRequest,
            'upiPayment' => $customerRequest ? $payments->options($customerRequest) : null,
            'whatsappUrl' => $homepage->publicSiteData()['whatsappUrl'],
        ]);
    }

    public function lookup(TrackCustomerRequestRequest $request, PublicRequestTrackingService $tracking, HomepageService $homepage, PaymentSubmissionService $payments): View
    {
        $customerRequest = $tracking->find(
            $request->validated('reference_no'),
            $request->validated('mobile'),
        );
        $request->session()->put('public_tracking.verified_requests.'.$customerRequest->id, now()->timestamp);
        $request->session()->put('public_tracking.last_verified', ['reference_no' => $customerRequest->reference_no, 'mobile' => $request->validated('mobile'), 'verified_at' => now()->timestamp]);

        return view('frontend.request.track', ['customerRequest' => $customerRequest, 'upiPayment' => $payments->options($customerRequest), 'whatsappUrl' => $homepage->publicSiteData()['whatsappUrl']]);
    }
}
