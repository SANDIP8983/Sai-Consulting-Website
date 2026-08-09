<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestAssignmentService
{
    public function __construct(private readonly RequestBillingStateResolver $billingStateResolver) {}

    public function assign(CustomerRequest $request, User $assignee, User $actor): CustomerRequest
    {
        return DB::transaction(function () use ($request, $assignee, $actor): CustomerRequest {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $lockedAssignee = User::query()->lockForUpdate()->findOrFail($assignee->id);

            if (! $actor->can('requests.assign')) {
                abort(403);
            }
            if (! $lockedAssignee->is_active || ! $lockedAssignee->hasPermission('processing.manage')) {
                throw ValidationException::withMessages(['assigned_user_id' => 'Only an active user with request-processing permission can receive a request assignment.']);
            }
            if ($locked->shouldDeriveRejectedLifecycle() || in_array($locked->status, ['rejected', 'closed', 'archived'], true)) {
                throw ValidationException::withMessages(['assigned_user_id' => 'This request is not open for staff assignment.']);
            }
            if (! $this->paymentConditionsSatisfied($locked)) {
                throw ValidationException::withMessages(['assigned_user_id' => 'Complete or resolve the required payment before assigning this request.']);
            }

            $assignedAt = now();
            $previous = $locked->assigned_user_id;
            $changes = [
                'assigned_user_id' => $lockedAssignee->id,
                'assigned_by' => $actor->id,
                'assigned_at' => $assignedAt,
            ];
            if (in_array($locked->status, ['approved', 'payment_pending', 'payment_received'], true)) {
                $fromStatus = $locked->status;
                $changes += ['status' => 'awaiting_staff_assignment', 'last_status_changed_at' => now()];
                $locked->statusHistory()->create([
                    'from_status' => $fromStatus,
                    'to_status' => 'awaiting_staff_assignment',
                    'remarks' => 'Awaiting staff assignment.',
                    'is_visible_to_customer' => true,
                    'changed_by' => $actor->id,
                ]);
            }
            $locked->update($changes);
            $locked->assignmentHistory()->create([
                'previous_assigned_user_id' => $previous,
                'assigned_user_id' => $lockedAssignee->id,
                'assigned_by' => $actor->id,
                'assigned_at' => $assignedAt,
            ]);

            return $locked->fresh(['assignedUser', 'assignedBy', 'assignmentHistory']);
        });
    }

    public function hasValidAssignment(CustomerRequest $request): bool
    {
        $assignee = $request->assigned_user_id ? User::query()->find($request->assigned_user_id) : null;

        return $assignee?->is_active === true && $assignee->hasPermission('processing.manage');
    }

    public function assertStaffCanAccess(CustomerRequest $request, User $user): void
    {
        if ($user->role === 'staff' && (! $user->is_active || $request->assigned_user_id !== $user->id)) {
            abort(403);
        }
    }

    public function assertCanProcess(CustomerRequest $request, User $user): void
    {
        if (! $this->hasValidAssignment($request)) {
            throw ValidationException::withMessages(['assignment' => 'Assign this request to an active Admin or Staff user before processing can begin.']);
        }
        if (! $user->isSuperAdmin() && $request->assigned_user_id !== $user->id) {
            abort(403);
        }
    }

    public function paymentConditionsSatisfied(CustomerRequest $request): bool
    {
        $status = $this->billingStateResolver->resolve($request)->paymentStatus;

        return in_array($status, ['paid', 'not_required'], true);
    }
}
