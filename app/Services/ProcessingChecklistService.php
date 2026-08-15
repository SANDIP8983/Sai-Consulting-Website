<?php

namespace App\Services;

use App\Enums\NotificationMilestone;
use App\Models\CustomerRequest;
use App\Models\RequestServiceWorkScope;
use App\Models\User;
use App\Services\Notifications\CustomerNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessingChecklistService
{
    public const RESOLVED = ['completed', 'not_required', 'cancelled'];

    public function __construct(
        private readonly RequestBillingStateResolver $billingStateResolver,
        private readonly RequestAssignmentService $assignments,
        private readonly CustomerNotificationService $notifications,
    ) {}

    public function eligibility(CustomerRequest $request): array
    {
        $accepted = $request->requestServices()->where('status', 'approved')->with(['service', 'workScopes'])->get();
        $requiresPayment = $accepted->contains(fn ($service) => (bool) ($service->service?->requires_payment_before_processing ?? true));
        $reasons = [];
        if ($accepted->isEmpty()) {
            $reasons[] = 'At least one accepted service is required.';
        }
        if (! $request->case_approved_at && $request->case_planning_version < 1) {
            $reasons[] = 'Case Planning must be approved.';
        }
        if (! $request->file_number) {
            $reasons[] = 'A file number must be generated.';
        }
        if ($accepted->isNotEmpty() && $accepted->contains(fn ($service) => ! $service->isAddOn() && $service->workScopes->isEmpty())) {
            $reasons[] = 'Every accepted base service must have selected included work-scope items.';
        }
        $paymentPending = $requiresPayment && $this->billingStateResolver->resolve($request)->paymentStatus !== 'paid';
        if ($paymentPending) {
            $reasons[] = 'Payment Pending: payment must be confirmed before processing can start.';
        }
        if (! $this->assignments->hasValidAssignment($request)) {
            $reasons[] = 'Assign this request to an active Admin or Staff user before processing can start.';
        }

        return ['eligible' => $reasons === [], 'payment_pending' => $paymentPending, 'requires_payment' => $requiresPayment, 'reasons' => $reasons];
    }

    public function update(CustomerRequest $request, RequestServiceWorkScope $scope, array $attributes, User $user): void
    {
        DB::transaction(function () use ($request, $scope, $attributes, $user): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertProcessable($locked, $user);
            $item = $this->ownedScope($locked, $scope->id);
            $to = $attributes['status'];
            $from = $item->status;
            if ($from === 'completed' && $to !== 'completed') {
                throw ValidationException::withMessages(['status' => 'Use the audited Reopen Work Item action for completed items.']);
            }
            if ($to === 'in_progress' && $from !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only Pending work can be started.']);
            }
            if ($to === 'completed' && $from !== 'in_progress') {
                throw ValidationException::withMessages(['status' => 'Only In Progress work can be completed.']);
            }
            if (in_array($to, ['not_required', 'cancelled'], true) && blank($attributes['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required for Not Required or Cancelled work.']);
            }
            $this->apply($locked, $item, $to, $attributes, $user, 'status_changed');
        });
    }

    public function reopenItem(CustomerRequest $request, RequestServiceWorkScope $scope, string $reason, User $user): void
    {
        DB::transaction(function () use ($request, $scope, $reason, $user): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertProcessable($locked, $user);
            $item = $this->ownedScope($locked, $scope->id);
            if ($item->status !== 'completed') {
                throw ValidationException::withMessages(['status' => 'Only completed work items can be reopened.']);
            }
            $this->apply($locked, $item, 'in_progress', ['reason' => $reason], $user, 'reopened');
        });
    }

    public function bulk(CustomerRequest $request, array $attributes, User $user): void
    {
        DB::transaction(function () use ($request, $attributes, $user): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertProcessable($locked, $user);
            $query = RequestServiceWorkScope::query()->whereHas('requestService', fn ($q) => $q->where('request_id', $locked->id)->where('status', 'approved'))->whereIn('id', $attributes['work_scope_ids']);
            $items = $query->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['work_scope_ids' => 'No eligible work items were selected for this request.']);
            }
            $to = match ($attributes['action']) {
                'start_selected' => 'in_progress','complete_selected' => 'completed','not_required' => 'not_required','cancel_selected' => 'cancelled',default => null
            };
            if (in_array($attributes['action'], ['not_required', 'cancel_selected'], true) && blank($attributes['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'A common reason is required for Not Required or Cancelled work.']);
            }
            foreach ($items as $item) {
                if ($item->status === 'completed' && $to !== 'completed' && $to !== null) {
                    throw ValidationException::withMessages(['status' => 'Completed items require an audited individual reopen.']);
                }
                if ($to === 'in_progress' && $item->status !== 'pending') {
                    throw ValidationException::withMessages(['status' => 'Start Selected accepts only Pending items.']);
                }
                if ($to === 'completed' && $item->status !== 'in_progress') {
                    throw ValidationException::withMessages(['status' => 'Complete Selected accepts only In Progress items.']);
                }
                if ($to) {
                    $this->apply($locked, $item, $to, $attributes, $user, 'bulk_'.$attributes['action']);
                } else {
                    $note = trim(implode("\n", array_filter([$item->internal_note, $attributes['internal_note'] ?? null])));
                    $item->update(['internal_note' => $note ?: null, 'updated_by' => $user->id]);
                    $this->audit($locked, $item, 'bulk_note', $item->status, $item->status, $attributes, $user);
                }
            }
        });
    }

    public function complete(CustomerRequest $request, array $attributes, User $user): void
    {
        DB::transaction(function () use ($request, $attributes, $user): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assignments->assertCanProcess($locked, $user);
            $eligibility = $this->eligibility($locked);
            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages(['case' => implode(' ', $eligibility['reasons'])]);
            }
            if ($locked->requestServices()->where('status', 'under_review')->exists()) {
                throw ValidationException::withMessages(['case' => 'Resolve all services still Under Review before completing the case.']);
            }
            $scopes = $this->scopes($locked);
            if ($scopes->isEmpty() || $scopes->contains(fn ($scope) => ! in_array($scope->status, self::RESOLVED, true))) {
                throw ValidationException::withMessages(['work_scopes' => 'All selected work items must be Completed or Not Required.']);
            }
            $from = $locked->status;
            $completedAt = Carbon::parse($attributes['completion_date'])->endOfDay();
            $locked->update(['status' => 'completed', 'completed_at' => $completedAt, 'completion_customer_remark' => $attributes['customer_remark'] ?? null, 'completion_internal_note' => $attributes['internal_note'] ?? null, 'last_status_changed_at' => now()]);
            $locked->statusHistory()->create(['from_status' => $from, 'to_status' => 'completed', 'remarks' => $attributes['customer_remark'] ?? 'Case processing completed.', 'is_visible_to_customer' => true, 'changed_by' => $user->id]);
            $locked->caseActionHistory()->create(['action' => 'completed', 'from_status' => $from, 'to_status' => 'completed', 'internal_note' => $attributes['internal_note'] ?? null, 'customer_remark' => $attributes['customer_remark'] ?? null, 'performed_by' => $user->id]);
            $this->notifications->afterCommit($locked, NotificationMilestone::Completed, 'request_status', $locked->id);
        });
    }

    public function reopenCase(CustomerRequest $request, string $reason, User $user): void
    {
        DB::transaction(function () use ($request, $reason, $user): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assignments->assertCanProcess($locked, $user);
            if ($locked->status !== 'completed') {
                throw ValidationException::withMessages(['case' => 'Only a Completed case can be reopened. Closed cases cannot be reopened.']);
            }
            $locked->update(['status' => 'in_progress', 'completed_at' => null, 'last_status_changed_at' => now()]);
            $locked->statusHistory()->create(['from_status' => 'completed', 'to_status' => 'in_progress', 'remarks' => 'Case reopened.', 'is_visible_to_customer' => true, 'changed_by' => $user->id]);
            $locked->caseActionHistory()->create(['action' => 'reopened', 'from_status' => 'completed', 'to_status' => 'in_progress', 'reason' => $reason, 'performed_by' => $user->id]);
        });
    }

    private function assertProcessable(CustomerRequest $request, User $user): void
    {
        if ($request->shouldDeriveRejectedLifecycle() || $request->status === 'rejected') {
            throw ValidationException::withMessages(['case' => 'Rejected requests cannot be processed.']);
        }
        $this->assignments->assertCanProcess($request, $user);
        if (in_array($request->status, ['completed', 'dispatched', 'delivered', 'closed', 'archived'], true)) {
            throw ValidationException::withMessages(['case' => 'Processing is locked for this case.']);
        }
        $eligibility = $this->eligibility($request);
        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages(['case' => implode(' ', $eligibility['reasons'])]);
        }
    }

    private function scopes(CustomerRequest $request): Collection
    {
        return RequestServiceWorkScope::query()->whereHas('requestService', fn ($q) => $q->where('request_id', $request->id)->where('status', 'approved'))->get();
    }

    private function ownedScope(CustomerRequest $request, int $id): RequestServiceWorkScope
    {
        return RequestServiceWorkScope::query()->whereKey($id)->whereHas('requestService', fn ($q) => $q->where('request_id', $request->id)->where('status', 'approved'))->lockForUpdate()->firstOrFail();
    }

    private function apply(CustomerRequest $request, RequestServiceWorkScope $item, string $to, array $attributes, User $user, string $action): void
    {
        $from = $item->status;
        $item->update(['status' => $to, 'started_at' => in_array($to, ['in_progress', 'completed'], true) ? ($item->started_at ?? now()) : $item->started_at, 'completed_at' => $to === 'completed' ? now() : null, 'updated_by' => $user->id, 'internal_note' => $attributes['internal_note'] ?? $item->internal_note, 'customer_remark' => $attributes['customer_remark'] ?? $item->customer_remark, 'resolution_reason' => in_array($to, ['not_required', 'cancelled'], true) ? $attributes['reason'] : null]);
        $this->audit($request, $item, $action, $from, $to, $attributes, $user);
        if (in_array($to, ['in_progress', 'completed'], true) && ! in_array($request->status, ['in_progress'], true)) {
            $old = $request->status;
            $request->update(['status' => 'in_progress', 'last_status_changed_at' => now()]);
            $request->statusHistory()->create(['from_status' => $old, 'to_status' => 'in_progress', 'remarks' => 'Processing started.', 'is_visible_to_customer' => true, 'changed_by' => $user->id]);
            $this->notifications->afterCommit($request, NotificationMilestone::ProcessingStarted, 'request_status', $request->id);
        }
    }

    private function audit(CustomerRequest $request, RequestServiceWorkScope $item, string $action, ?string $from, ?string $to, array $attributes, User $user): void
    {
        $item->history()->create(['request_id' => $request->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'reason' => $attributes['reason'] ?? null, 'internal_note' => $attributes['internal_note'] ?? null, 'customer_remark' => $attributes['customer_remark'] ?? null, 'changed_by' => $user->id]);
    }
}
