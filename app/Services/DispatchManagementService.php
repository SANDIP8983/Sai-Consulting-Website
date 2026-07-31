<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestDispatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchManagementService
{
    public function __construct(private readonly RequestWorkflowService $workflow) {}

    public function record(CustomerRequest $request, array $attributes, User $user): RequestDispatch
    {
        return DB::transaction(function () use ($request, $attributes, $user): RequestDispatch {
            $lockedRequest = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($lockedRequest->payment_status !== 'received' || ! $lockedRequest->file_number) {
                throw ValidationException::withMessages(['dispatch' => 'Dispatch is allowed only after payment is received and a file number exists.']);
            }

            $allowedWorkflowStatuses = ['ready_for_registration', 'dispatched', 'completed', 'archived'];
            if (! in_array($lockedRequest->status, $allowedWorkflowStatuses, true)) {
                throw ValidationException::withMessages(['dispatch' => 'The request must be ready for registration before dispatch.']);
            }

            if ($attributes['dispatch_status'] === 'dispatched' && ! in_array($lockedRequest->status, ['ready_for_registration', 'dispatched'], true)) {
                throw ValidationException::withMessages(['dispatch_status' => 'This request cannot be marked dispatched from its current workflow status.']);
            }
            if ($attributes['dispatch_status'] === 'delivered' && ! $lockedRequest->dispatches()->where('dispatch_status', 'dispatched')->exists()) {
                throw ValidationException::withMessages(['dispatch_status' => 'A dispatched record is required before delivery can be recorded.']);
            }

            $dispatch = $lockedRequest->dispatches()->create([...$attributes, 'performed_by' => $user->id]);
            if ($attributes['dispatch_status'] === 'dispatched' && $lockedRequest->status === 'ready_for_registration') {
                $this->workflow->transition($lockedRequest, ['status' => 'dispatched', 'remarks' => 'Request dispatched.', 'is_visible_to_customer' => true], $user);
            }

            return $dispatch;
        });
    }
}
