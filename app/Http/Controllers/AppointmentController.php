<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Service;
use App\Services\AppointmentAvailabilityService;
use App\Services\AppointmentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function create(): View
    {
        return view('frontend.appointments.create', ['services' => Service::where('is_active', true)->orderBy('sort_order')->orderBy('name_en')->get()]);
    }

    public function availability(Request $request, AppointmentAvailabilityService $service): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now('Asia/Kolkata')->addMonths(6)->toDateString()], 'service_id' => ['required', 'exists:services,id']]);
        abort_unless(Service::whereKey($data['service_id'])->where('is_active', true)->exists(), 422);

        return response()
            ->json(['slots' => $service->slots($data['date'])])
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function store(StoreAppointmentRequest $request, AppointmentWorkflowService $workflow): RedirectResponse
    {
        $appointment = $workflow->create($request->safe()->except(['admin_note']));

        return to_route('appointments.success')->with('submitted_appointment', [
            'reference_no' => $appointment->reference_no,
            'service_name' => $appointment->service->name_en,
            'scheduled_at' => $appointment->scheduled_at->format('d M Y, g:i A'),
            'status' => $appointment->status->value,
        ]);
    }

    public function success(Request $request): View|RedirectResponse
    {
        return $request->session()->has('submitted_appointment')
            ? view('frontend.appointments.success')
            : to_route('appointments.create');
    }
}
