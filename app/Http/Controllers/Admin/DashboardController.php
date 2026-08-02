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
        $today = today()->toDateString();
        $operationalSummary = CustomerRequest::query()->selectRaw(
            "SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today_requests,
             SUM(CASE WHEN status IN ('received','under_review','need_documents') THEN 1 ELSE 0 END) as pending_approval,
             SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payment,
             SUM(CASE WHEN status IN ('approved','payment_received','in_progress','draft_in_progress','ready_for_verification','customer_approved','ready_for_registration') THEN 1 ELSE 0 END) as in_progress,
             SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as ready_for_dispatch,
             SUM(CASE WHEN status = 'completed' AND DATE(completed_at) = ? THEN 1 ELSE 0 END) as completed_today,
             SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived,
             SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
             SUM(CASE WHEN estimated_completion_date < ? AND status NOT IN ('completed','dispatched','delivered','closed','archived','rejected') THEN 1 ELSE 0 END) as overdue",
            [$today, $today, $today],
        )->first();

        return view('admin.dashboard', [
            'operationalSummary' => (array) $operationalSummary->getAttributes(),
            'requestSummary' => [
                'received' => CustomerRequest::query()->where('status', 'received')->count(),
                'under_review' => CustomerRequest::query()->where('status', 'under_review')->count(),
                'need_documents' => CustomerRequest::query()->where('status', 'need_documents')->count(),
                'payment_pending' => CustomerRequest::query()->where('status', 'payment_pending')->count(),
                'in_progress' => CustomerRequest::query()->whereIn('status', ['approved', 'payment_received', 'draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration', 'dispatched'])->count(),
                'completed' => CustomerRequest::query()->where('status', 'completed')->count(),
                'processing_not_started' => CustomerRequest::query()->whereHas('requestServices.workScopes', fn ($q) => $q->where('status', 'pending'))->whereDoesntHave('requestServices.workScopes', fn ($q) => $q->whereIn('status', ['in_progress', 'completed', 'not_required', 'cancelled']))->count(),
                'ready_to_complete' => CustomerRequest::query()->whereHas('requestServices.workScopes')->whereDoesntHave('requestServices.workScopes', fn ($q) => $q->whereIn('status', ['pending', 'in_progress']))->whereNotIn('status', ['completed', 'dispatched', 'delivered', 'closed'])->count(),
                'dispatch_pending' => CustomerRequest::query()->where('status', 'completed')->whereDoesntHave('dispatches', fn ($q) => $q->whereIn('dispatch_status', ['dispatched', 'in_transit', 'delivered', 'collected']))->count(),
                'in_transit' => CustomerRequest::query()->whereHas('dispatches', fn ($q) => $q->where('dispatch_status', 'in_transit'))->count(),
                'delivered_today' => CustomerRequest::query()->whereHas('dispatches', fn ($q) => $q->where(fn ($q) => $q->whereDate('delivered_at', today())->orWhereDate('collected_at', today())))->count(),
                'ready_to_close' => CustomerRequest::query()->where('status', 'delivered')->whereHas('dispatches', fn ($q) => $q->whereIn('dispatch_status', ['delivered', 'collected']))->whereDoesntHave('dispatches', fn ($q) => $q->whereIn('dispatch_status', ['prepared', 'not_dispatched', 'dispatched', 'in_transit', 'failed_returned']))->count(),
                'closed_month' => CustomerRequest::query()->where('status', 'closed')->whereBetween('closed_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
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
