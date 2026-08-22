<?php

namespace App\Services;

use App\Enums\NotificationMilestone;
use App\Models\CustomerRequest;
use App\Models\RequestDispatch;
use App\Models\RequestDispatchProof;
use App\Models\User;
use App\Services\Notifications\CustomerNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DispatchManagementService
{
    public function __construct(private readonly RequestBillingStateResolver $billingStateResolver, private readonly CustomerNotificationService $notifications) {}

    public const METHODS = ['whatsapp', 'email', 'office_collection', 'hand_delivery', 'courier', 'speed_post', 'rpad', 'other'];

    public const STATUSES = ['prepared', 'dispatched', 'in_transit', 'delivered', 'collected', 'failed_returned', 'cancelled'];

    public const RESOLVED = ['delivered', 'collected', 'cancelled'];

    public const PROOF_TYPES = ['courier_receipt', 'postal_receipt', 'pod', 'delivery_acknowledgement', 'office_collection_acknowledgement', 'other'];

    public const LEGACY_METHODS = ['india_post_registered', 'india_post_speed_post'];

    public const LEGACY_STATUSES = ['not_dispatched'];

    private const TRANSITIONS = [
        'prepared' => ['dispatched', 'collected', 'cancelled'],
        'dispatched' => ['in_transit', 'delivered', 'collected', 'failed_returned', 'cancelled'],
        'in_transit' => ['delivered', 'failed_returned'],
        'failed_returned' => ['prepared', 'cancelled'],
        'delivered' => [], 'collected' => [], 'cancelled' => [],
    ];

    public function eligibility(CustomerRequest $request): array
    {
        $reasons = [];
        $requiresDispatch = $request->processing?->requires_dispatch ?? $request->service?->requires_dispatch ?? true;
        $requiresPayment = $request->processing?->requires_payment_before_processing ?? $request->service?->requires_payment_before_processing ?? true;
        if (! $requiresDispatch) {
            $reasons[] = 'Dispatch is not required for this service.';
        }
        if (! in_array($request->status, ['completed', 'dispatched', 'delivered'], true)) {
            $reasons[] = 'Complete the case processing before adding dispatch or delivery details.';
        }
        if (! $request->file_number) {
            $reasons[] = 'A file number is required before dispatch.';
        }
        $billingState = $this->billingStateResolver->resolve($request);
        $paymentPending = $requiresPayment && ! in_array($billingState->paymentStatus, ['paid', 'not_required'], true);
        if ($paymentPending) {
            $reasons[] = 'Payment Pending: payment must be confirmed before dispatch.';
        }

        $scopes = $request->requestServices()->with('workScopes')->get()->flatMap->workScopes;
        if ($scopes->isNotEmpty() && $scopes->contains(fn ($scope) => ! in_array($scope->status, ProcessingChecklistService::RESOLVED, true))) {
            $reasons[] = 'All selected work-scope items must be resolved before dispatch.';
        } elseif ($scopes->isEmpty() && $request->processing && ! in_array($request->processing->processing_stage, ['completed', 'dispatched'], true)) {
            $reasons[] = 'Legacy processing must be completed before dispatch.';
        }

        return ['eligible' => $reasons === [], 'payment_pending' => $paymentPending, 'reasons' => $reasons];
    }

    public function closeEligibility(CustomerRequest $request): array
    {
        $reasons = $this->eligibility($request)['reasons'];
        $dispatches = $request->dispatches()->get();
        if (! $dispatches->contains(fn ($dispatch) => in_array($dispatch->dispatch_status, ['delivered', 'collected'], true))) {
            $reasons[] = 'At least one dispatch must be Delivered or Collected.';
        }
        if ($dispatches->contains(fn ($dispatch) => in_array($dispatch->dispatch_status, ['prepared', 'dispatched', 'in_transit', 'not_dispatched'], true))) {
            $reasons[] = 'All dispatch records must be resolved before closing the case.';
        }
        if ($dispatches->contains(fn ($dispatch) => $dispatch->dispatch_status === 'failed_returned')) {
            $reasons[] = 'Failed / Returned dispatch records must be resolved before closing the case.';
        }

        return ['eligible' => $reasons === [], 'reasons' => array_values(array_unique($reasons))];
    }

    public function create(CustomerRequest $request, array $attributes, User $user, ?UploadedFile $proof = null): RequestDispatch
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($request, $attributes, $user, $proof, &$storedPath): RequestDispatch {
                $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
                $this->assertEligible($locked);
                if ($locked->status === 'closed') {
                    throw ValidationException::withMessages(['dispatch' => 'Closed cases must be reopened before adding dispatch records.']);
                }
                $status = $attributes['dispatch_status'] ?? 'prepared';
                if (! in_array($status, ['prepared', 'dispatched', 'collected'], true)) {
                    throw ValidationException::withMessages(['dispatch_status' => 'A new dispatch must start as Prepared, Dispatched, or Collected.']);
                }
                $this->validateDetails($attributes, $status);
                $dispatch = $locked->dispatches()->create([...collect($attributes)->except('proof_type')->all(), 'dispatch_status' => $status, 'performed_by' => $user->id, 'updated_by' => $user->id]);
                $this->audit($locked, $dispatch, 'created', null, $status, null, [], $dispatch->toArray(), $user);
                if ($proof) {
                    $this->storeProof($locked, $dispatch, $proof, $attributes['proof_type'], $user, $storedPath);
                }
                $this->syncRequestStatus($locked, $status, $user, $attributes['customer_remark'] ?? null);

                return $dispatch->refresh();
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }
    }

    public function update(CustomerRequest $request, RequestDispatch $dispatch, array $attributes, User $user): RequestDispatch
    {
        return DB::transaction(function () use ($request, $dispatch, $attributes, $user): RequestDispatch {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertOwned($locked, $dispatch);
            $this->assertEligible($locked);
            if (in_array($locked->status, ['closed'], true)) {
                throw ValidationException::withMessages(['dispatch' => 'Closed cases are read-only. Reopen the case first.']);
            }
            $row = RequestDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            if (in_array($row->dispatch_status, ['delivered', 'collected'], true)) {
                throw ValidationException::withMessages(['dispatch' => 'Delivered or Collected records are read-only. Use the audited reopen action.']);
            }
            $old = $row->toArray();
            $details = [...$row->toArray(), ...$attributes];
            $this->validateDetails($details, $row->dispatch_status);
            $row->update([...$attributes, 'updated_by' => $user->id]);
            $this->audit($locked, $row, 'edited', $row->dispatch_status, $row->dispatch_status, null, $old, $row->fresh()->toArray(), $user);

            return $row->refresh();
        });
    }

    public function transition(CustomerRequest $request, RequestDispatch $dispatch, string $to, array $attributes, User $user): RequestDispatch
    {
        return DB::transaction(function () use ($request, $dispatch, $to, $attributes, $user): RequestDispatch {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertOwned($locked, $dispatch);
            $this->assertEligible($locked);
            if ($locked->status === 'closed') {
                throw ValidationException::withMessages(['dispatch' => 'Closed cases are read-only. Reopen the case first.']);
            }
            $row = RequestDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            $from = $row->dispatch_status;
            if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['dispatch_status' => 'This dispatch-status transition is not allowed.']);
            }
            $details = [...$row->toArray(), ...$attributes];
            $this->validateDetails($details, $to);
            $changes = ['dispatch_status' => $to, 'updated_by' => $user->id];
            if ($to === 'delivered') {
                $changes['delivered_at'] = $attributes['delivered_at'];
            }
            if ($to === 'collected') {
                $changes['collected_at'] = $attributes['collected_at'];
            }
            if ($to === 'failed_returned') {
                $changes['failure_reason'] = $attributes['reason'];
            }
            if ($to === 'cancelled') {
                $changes['cancellation_reason'] = $attributes['reason'];
            }
            $row->update([...$attributes, ...$changes]);
            $this->audit($locked, $row, 'status_changed', $from, $to, $attributes['reason'] ?? null, ['dispatch_status' => $from], $row->fresh()->toArray(), $user);
            $this->syncRequestStatus($locked, $to, $user, $attributes['customer_remark'] ?? $row->customer_remark);

            return $row->refresh();
        });
    }

    public function reopenDispatch(CustomerRequest $request, RequestDispatch $dispatch, string $reason, User $user): RequestDispatch
    {
        return DB::transaction(function () use ($request, $dispatch, $reason, $user): RequestDispatch {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertOwned($locked, $dispatch);
            if ($locked->status === 'closed') {
                throw ValidationException::withMessages(['dispatch' => 'Reopen the closed case before reopening a dispatch record.']);
            }
            $row = RequestDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            if (! in_array($row->dispatch_status, ['delivered', 'collected'], true)) {
                throw ValidationException::withMessages(['dispatch_status' => 'Only Delivered or Collected records can be reopened.']);
            }
            $from = $row->dispatch_status;
            $row->update(['dispatch_status' => 'dispatched', 'delivered_at' => null, 'collected_at' => null, 'updated_by' => $user->id]);
            $this->audit($locked, $row, 'reopened', $from, 'dispatched', $reason, ['dispatch_status' => $from], ['dispatch_status' => 'dispatched'], $user);
            if ($locked->status === 'delivered') {
                $this->setRequestStatus($locked, 'dispatched', 'Delivery record reopened.', false, $user);
            }

            return $row->refresh();
        });
    }

    public function uploadProof(CustomerRequest $request, RequestDispatch $dispatch, UploadedFile $proof, string $type, User $user): RequestDispatchProof
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($request, $dispatch, $proof, $type, $user, &$storedPath): RequestDispatchProof {
                $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
                $this->assertOwned($locked, $dispatch);
                if ($locked->status === 'closed') {
                    throw ValidationException::withMessages(['proof' => 'Closed cases are read-only.']);
                }

                return $this->storeProof($locked, $dispatch, $proof, $type, $user, $storedPath);
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }
    }

    public function close(CustomerRequest $request, array $attributes, User $user): void
    {
        DB::transaction(function () use ($request, $attributes, $user): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $eligibility = $this->closeEligibility($locked);
            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages(['closure' => implode(' ', $eligibility['reasons'])]);
            }
            $from = $locked->status;
            $locked->update(['status' => 'closed', 'closed_at' => $attributes['closure_date'], 'closure_customer_remark' => $attributes['customer_remark'] ?? null, 'closure_internal_note' => $attributes['internal_note'] ?? null, 'closed_by' => $user->id, 'last_status_changed_at' => now()]);
            $locked->statusHistory()->create(['from_status' => $from, 'to_status' => 'closed', 'remarks' => $attributes['customer_remark'] ?? 'Case closed after confirmed delivery.', 'is_visible_to_customer' => true, 'changed_by' => $user->id]);
            $locked->caseActionHistory()->create(['action' => 'closed', 'from_status' => $from, 'to_status' => 'closed', 'internal_note' => $attributes['internal_note'] ?? null, 'customer_remark' => $attributes['customer_remark'] ?? null, 'performed_by' => $user->id]);
            $this->notifications->afterCommit($locked, NotificationMilestone::DeliveredClosed, 'request_status', $locked->id);
        });
    }

    public function reopenCase(CustomerRequest $request, string $reason, User $user): void
    {
        DB::transaction(function () use ($request, $reason, $user): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== 'closed') {
                throw ValidationException::withMessages(['closure' => 'Only a Closed case can be reopened.']);
            }
            $target = $locked->dispatches()->whereIn('dispatch_status', ['delivered', 'collected'])->exists() ? 'delivered' : 'completed';
            $old = $locked->only(['closed_at', 'closure_customer_remark', 'closure_internal_note', 'closed_by']);
            $locked->update(['status' => $target, 'closed_at' => null, 'closure_customer_remark' => null, 'closure_internal_note' => null, 'closed_by' => null, 'last_status_changed_at' => now()]);
            $locked->statusHistory()->create(['from_status' => 'closed', 'to_status' => $target, 'remarks' => 'Case reopened.', 'is_visible_to_customer' => true, 'changed_by' => $user->id]);
            $locked->caseActionHistory()->create(['action' => 'reopened_after_closure', 'from_status' => 'closed', 'to_status' => $target, 'reason' => $reason, 'internal_note' => json_encode($old), 'performed_by' => $user->id]);
        });
    }

    private function assertEligible(CustomerRequest $request): void
    {
        $eligibility = $this->eligibility($request);
        if (! $eligibility['eligible']) {
            throw ValidationException::withMessages(['dispatch' => implode(' ', $eligibility['reasons'])]);
        }
    }

    private function assertOwned(CustomerRequest $request, RequestDispatch $dispatch): void
    {
        if ($dispatch->request_id !== $request->id) {
            abort(404);
        }
    }

    private function validateDetails(array $attributes, string $status): void
    {
        $method = $attributes['dispatch_method'];
        if ($method === 'whatsapp' && blank($attributes['recipient_mobile'] ?? null)) {
            throw ValidationException::withMessages(['recipient_mobile' => 'Recipient mobile is required for WhatsApp delivery.']);
        }
        if ($method === 'email' && blank($attributes['recipient_email'] ?? null)) {
            throw ValidationException::withMessages(['recipient_email' => 'Recipient email is required for Email delivery.']);
        }
        if (in_array($method, ['courier', 'speed_post', 'rpad'], true) && in_array($status, ['dispatched', 'in_transit', 'delivered'], true)) {
            if (blank($attributes['delivery_address'] ?? null)) {
                throw ValidationException::withMessages(['delivery_address' => 'Postal / delivery address is required for this method.']);
            }
            if (blank($attributes['carrier_name'] ?? null)) {
                throw ValidationException::withMessages(['carrier_name' => 'Courier / postal service name is required for this method.']);
            }
            if (blank($attributes['tracking_number'] ?? null)) {
                throw ValidationException::withMessages(['tracking_number' => 'Tracking / consignment number is required before dispatch.']);
            }
        }
        if ($method === 'office_collection' && $status === 'collected') {
            if (blank($attributes['recipient_name'] ?? null)) {
                throw ValidationException::withMessages(['recipient_name' => 'Collected-by name is required.']);
            }
            if (blank($attributes['collected_at'] ?? null)) {
                throw ValidationException::withMessages(['collected_at' => 'Collection date and time is required.']);
            }
        }
        if ($status === 'collected' && $method !== 'office_collection') {
            throw ValidationException::withMessages(['dispatch_status' => 'Collected status is available only for Office Collection.']);
        }
        if ($method === 'hand_delivery' && blank($attributes['recipient_name'] ?? null)) {
            throw ValidationException::withMessages(['recipient_name' => 'Recipient name is required for Hand Delivery.']);
        }
        if ($status === 'delivered' && blank($attributes['delivered_at'] ?? null)) {
            throw ValidationException::withMessages(['delivered_at' => 'Delivery date and time is required.']);
        }
        if ($method === 'other' && (blank($attributes['method_description'] ?? null) || blank($attributes['customer_remark'] ?? null))) {
            throw ValidationException::withMessages(['method_description' => 'Other method description and customer-visible remark are required.']);
        }
        if (in_array($status, ['failed_returned', 'cancelled'], true) && blank($attributes['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required for Failed / Returned or Cancelled dispatch.']);
        }
    }

    private function syncRequestStatus(CustomerRequest $request, string $status, User $user, ?string $remark): void
    {
        if (in_array($status, ['dispatched', 'in_transit'], true) && $request->status === 'completed') {
            $this->setRequestStatus($request, 'dispatched', $remark ?: 'Documents dispatched.', true, $user);
        }
        if (in_array($status, ['delivered', 'collected'], true) && in_array($request->status, ['completed', 'dispatched'], true)) {
            $this->setRequestStatus($request, 'delivered', $remark ?: 'Documents delivered or collected.', true, $user);
        }
    }

    private function setRequestStatus(CustomerRequest $request, string $to, string $remark, bool $visible, User $user): void
    {
        $from = $request->status;
        $request->update(['status' => $to, 'last_status_changed_at' => now()]);
        $request->statusHistory()->create(['from_status' => $from, 'to_status' => $to, 'remarks' => $remark, 'is_visible_to_customer' => $visible, 'changed_by' => $user->id]);
        if ($to === 'dispatched') {
            $this->notifications->afterCommit($request, NotificationMilestone::Dispatched, 'request_status', $request->id);
        }
        if ($to === 'delivered') {
            $this->notifications->afterCommit($request, NotificationMilestone::DeliveredClosed, 'request_status', $request->id);
        }
    }

    private function storeProof(CustomerRequest $request, RequestDispatch $dispatch, UploadedFile $proof, string $type, User $user, ?string &$storedPath): RequestDispatchProof
    {
        $storedPath = $proof->store("customer-requests/{$request->id}/dispatch-proofs", 'local');
        if ($storedPath === false) {
            throw new \RuntimeException('Dispatch proof could not be stored.');
        }
        $record = $dispatch->proofs()->create(['proof_type' => $type, 'file_name' => $proof->getClientOriginalName(), 'file_path' => $storedPath, 'mime_type' => $proof->getMimeType(), 'file_size' => $proof->getSize(), 'uploaded_by' => $user->id]);
        $this->audit($request, $dispatch, 'proof_uploaded', $dispatch->dispatch_status, $dispatch->dispatch_status, null, [], ['proof_type' => $type, 'file_name' => $record->file_name], $user);

        return $record;
    }

    private function audit(CustomerRequest $request, RequestDispatch $dispatch, string $action, ?string $from, ?string $to, ?string $reason, array $old, array $new, User $user): void
    {
        $dispatch->history()->create(['request_id' => $request->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'old_values' => $old, 'new_values' => $new, 'reason' => $reason, 'changed_by' => $user->id]);
    }
}
