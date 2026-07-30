<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use App\Models\Holiday;
use App\Models\OfficeTiming;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'summary' => [
                'requests' => CustomerRequest::query()->count(),
                'settings' => Setting::query()->count(),
                'office_timings' => OfficeTiming::query()->count(),
                'holidays' => Holiday::query()->count(),
            ],
        ]);
    }
}
