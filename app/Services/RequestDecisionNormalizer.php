<?php

namespace App\Services;

use App\Enums\NotificationMilestone;
use App\Models\CustomerRequest;
use App\Models\User;
use App\Services\Notifications\CustomerNotificationService;

class RequestDecisionNormalizer
{
    public function __construct(private readonly CustomerNotificationService $notifications) {}

    public function normalize(CustomerRequest $request, User $actor): void
    {
        if (! $request->allSelectedServicesRejected() || $request->status === 'rejected') {
            return;
        }

        $from = $request->status;
        $request->update([
            'status' => 'rejected',
            'payment_status' => 'not_required',
            'amount_due' => 0,
            'assigned_user_id' => null,
            'assigned_by' => null,
            'assigned_at' => null,
            'last_status_changed_at' => now(),
        ]);
        $request->statusHistory()->create([
            'from_status' => $from,
            'to_status' => 'rejected',
            'remarks' => 'No selected services were accepted.',
            'is_visible_to_customer' => true,
            'changed_by' => $actor->id,
        ]);
        $this->notifications->afterCommit($request, NotificationMilestone::Rejected, 'request_status', $request->id);
    }
}
