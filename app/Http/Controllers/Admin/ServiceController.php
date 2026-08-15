<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterServicesRequest;
use App\Http\Requests\Admin\StoreServiceRequest;
use App\Http\Requests\Admin\UpdateServiceRequest;
use App\Models\Service;
use App\Models\WorkScopeItem;
use App\Services\ServiceManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceManagementService $serviceManagementService) {}

    public function index(FilterServicesRequest $request): View
    {
        return view('admin.services.index', [
            'services' => $this->serviceManagementService->paginate($request->validated()),
        ]);
    }

    public function create(): View
    {
        return view('admin.services.create', $this->formData());
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $this->serviceManagementService->create($request->validated());

        return to_route('admin.services.index')->with('success', 'Service created successfully.');
    }

    public function edit(Service $service): View
    {
        $service->load(['requiredDocuments', 'governmentChargeItems', 'defaultWorkScopes', 'availableAddOns']);

        return view('admin.services.edit', ['service' => $service, ...$this->formData($service)]);
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->serviceManagementService->update($service, $request->validated());

        return to_route('admin.services.index')->with('success', 'Service updated successfully.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        if (! $this->serviceManagementService->delete($service)) {
            return back()->with('error', 'This service cannot be deleted because it is linked to customer requests.');
        }

        return to_route('admin.services.index')->with('success', 'Service deleted successfully.');
    }

    private function formData(?Service $service = null): array
    {
        return [
            'workScopeItems' => WorkScopeItem::query()->where('is_active', true)->orderBy('display_order')->get(),
            'addOnServices' => Service::query()->where('is_active', true)->when($service, fn ($query) => $query->whereKeyNot($service->id))->orderBy('name_en')->get(),
        ];
    }
}
