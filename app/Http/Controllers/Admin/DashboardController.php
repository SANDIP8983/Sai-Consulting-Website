<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use App\Models\Holiday;
use App\Models\OfficeTiming;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'requestSummary' => [
                'received' => CustomerRequest::query()->where('status', 'received')->count(),
                'under_review' => CustomerRequest::query()->where('status', 'under_review')->count(),
                'need_documents' => CustomerRequest::query()->where('status', 'need_documents')->count(),
                'payment_pending' => CustomerRequest::query()->where('status', 'payment_pending')->count(),
                'in_progress' => CustomerRequest::query()->whereIn('status', ['approved', 'payment_received', 'draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration', 'dispatched'])->count(),
                'completed' => CustomerRequest::query()->where('status', 'completed')->count(),
                'processing_not_started' => CustomerRequest::query()->whereHas('requestServices.workScopes', fn($q)=>$q->where('status','pending'))->whereDoesntHave('requestServices.workScopes',fn($q)=>$q->whereIn('status',['in_progress','completed','cancelled']))->count(),
                'ready_to_complete' => CustomerRequest::query()->whereHas('requestServices.workScopes')->whereDoesntHave('requestServices.workScopes',fn($q)=>$q->whereIn('status',['pending','in_progress']))->whereNotIn('status',['completed','dispatched','delivered','closed'])->count(),
            ],
            'summary' => [
                'requests' => CustomerRequest::query()->count(),
                'online_requests' => CustomerRequest::query()->where('request_origin', 'online')->count(),
                'offline_requests' => CustomerRequest::query()->where('request_origin', 'offline')->count(),
                'settings' => Setting::query()->count(),
                'office_timings' => OfficeTiming::query()->count(),
                'holidays' => Holiday::query()->count(),
                'active_services' => Service::query()->where('is_active', true)->count(),
                'disabled_services' => Service::query()->where('is_active', false)->count(),
                'online_services' => Service::query()->where('is_active', true)->where('available_online', true)->count(),
                'offline_services' => Service::query()->where('is_active', true)->where('available_offline', true)->count(),
            ],
        ]);
    }
}
