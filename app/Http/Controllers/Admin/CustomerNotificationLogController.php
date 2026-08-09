<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationMilestone;
use App\Http\Controllers\Controller;
use App\Models\CustomerNotificationDelivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerNotificationLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'milestone' => ['nullable', 'string'], 'channel' => ['nullable', 'in:email,whatsapp'], 'status' => ['nullable', 'in:pending,sent,failed,skipped'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
        $deliveries = CustomerNotificationDelivery::query()->with('event.customerRequest:id,reference_no')->latest()
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->whereHas('event.customerRequest', fn ($r) => $r->where('reference_no', 'like', "%{$value}%")))
            ->when($filters['milestone'] ?? null, fn ($q, $value) => $q->whereHas('event', fn ($e) => $e->where('milestone', $value)))
            ->when($filters['channel'] ?? null, fn ($q, $value) => $q->where('channel', $value))->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '<=', $value))->paginate(30)->withQueryString();

        return view('admin.notifications.index', ['deliveries' => $deliveries, 'milestones' => NotificationMilestone::cases()]);
    }
}
