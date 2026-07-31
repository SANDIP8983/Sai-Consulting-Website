<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequestRequest;
use App\Http\Requests\TrackCustomerRequestRequest;
use App\Models\Service;
use App\Services\PublicRequestTrackingService;
use App\Services\RequestWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CustomerRequestController extends Controller
{
    public function create(): View
    {
        $services = Service::query()->where('is_active', true)->with('requiredDocuments')
            ->orderBy('sort_order')->orderBy('name_en')->get();

        return view('frontend.request.create', compact('services'));
    }

    public function store(StoreCustomerRequestRequest $request, RequestWorkflowService $workflow): RedirectResponse
    {
        $customerRequest = $workflow->submit(
            $request->safe()->except(['documents', 'declaration']),
            $request->file('documents', []),
        );

        return redirect()->route('request.success')->with('submitted_request', [
            'reference_no' => $customerRequest->reference_no,
            'estimated_days' => $customerRequest->service->estimated_days,
            'estimated_completion_date' => $customerRequest->estimated_completion_date?->toDateString(),
        ]);
    }

    public function success(): View|RedirectResponse
    {
        return session()->has('submitted_request')
            ? view('frontend.request.success')
            : redirect()->route('request.create');
    }

    public function track(): View
    {
        return view('frontend.request.track');
    }

    public function lookup(TrackCustomerRequestRequest $request, PublicRequestTrackingService $tracking): View
    {
        $customerRequest = $tracking->find(
            $request->validated('reference_no'),
            $request->validated('mobile'),
        );

        return view('frontend.request.track', compact('customerRequest'));
    }
}
