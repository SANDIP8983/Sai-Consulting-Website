<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\AppointmentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $r): View
    {
        $q = Appointment::with('service')->latest('scheduled_at');
        if ($s = $r->string('search')->toString()) {
            $q->where(fn ($x) => $x->where('reference_no', 'like', "%$s%")->orWhere('customer_name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"));
        } foreach (['status', 'service_id'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->input($f));
            }
        } if ($r->filled('from')) {
            $q->whereDate('scheduled_at', '>=', $r->input('from'));
        } if ($r->filled('to')) {
            $q->whereDate('scheduled_at', '<=', $r->input('to'));
        }

        return view('admin.appointments.index', ['appointments' => $q->paginate(20)->withQueryString(), 'services' => Service::where('is_active', true)->orderBy('name_en')->get(), 'statuses' => AppointmentStatus::cases()]);
    }

    public function create(): View
    {
        return view('admin.appointments.create', ['services' => Service::where('is_active', true)->orderBy('name_en')->get()]);
    }

    public function store(StoreAppointmentRequest $r, AppointmentWorkflowService $w): RedirectResponse
    {
        $a = $w->create($r->validated(), 'admin', $r->user());

        return to_route('admin.appointments.show', $a)->with('success', 'Appointment created.');
    }

    public function show(Appointment $appointment): View
    {
        return view('admin.appointments.show', ['appointment' => $appointment->load(['service', 'histories']), 'statuses' => AppointmentStatus::cases()]);
    }

    public function transition(Request $r, Appointment $appointment, AppointmentWorkflowService $w): RedirectResponse
    {
        $data = $r->validate(['status' => ['required', 'in:confirmed,rescheduled,completed,cancelled'], 'appointment_date' => ['required_if:status,rescheduled', 'nullable', 'date_format:Y-m-d'], 'appointment_time' => ['required_if:status,rescheduled', 'nullable', 'date_format:H:i'], 'note' => ['nullable', 'string', 'max:1000']]);
        $w->transition($appointment, AppointmentStatus::from($data['status']), $r->user(), $data['note'] ?? null, $data);

        return back()->with('success', 'Appointment updated.');
    }
}
