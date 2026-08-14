<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppointmentBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentBlockController extends Controller
{
    public function index(): View
    {
        return view('admin.appointment-blocks.index', ['blocks' => AppointmentBlock::latest('block_date')->paginate(20)]);
    }

    public function store(Request $r): RedirectResponse
    {
        $d = $this->validated($r);
        $d['created_by'] = $r->user()->id;
        AppointmentBlock::create($d);

        return back()->with('success', 'Availability block added.');
    }

    public function update(Request $r, AppointmentBlock $appointmentBlock): RedirectResponse
    {
        abort_if($appointmentBlock->block_date->isPast(), 422);
        $appointmentBlock->update($this->validated($r));

        return back()->with('success', 'Block updated.');
    }

    public function destroy(AppointmentBlock $appointmentBlock): RedirectResponse
    {
        abort_if($appointmentBlock->block_date->isPast(), 422);
        $appointmentBlock->delete();

        return back()->with('success', 'Block deleted.');
    }

    private function validated(Request $r): array
    {
        $d = $r->validate(['block_date' => ['required', 'date', 'after_or_equal:today'], 'full_day' => ['nullable', 'boolean'], 'starts_at' => ['nullable', 'required_unless:full_day,1', 'date_format:H:i'], 'ends_at' => ['nullable', 'required_unless:full_day,1', 'date_format:H:i', 'after:starts_at'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $d['full_day'] = $r->boolean('full_day');
        if ($d['full_day']) {
            $d['starts_at'] = $d['ends_at'] = null;
        }

        return $d;
    }
}
