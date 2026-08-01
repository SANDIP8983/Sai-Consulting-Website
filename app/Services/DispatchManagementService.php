<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestDispatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchManagementService
{
    public function __construct(private readonly RequestWorkflowService $workflow, private readonly FileDocumentProcessingService $processing) {}

    public function record(CustomerRequest $request, array $attributes, User $user): RequestDispatch
    {
        return DB::transaction(function () use ($request, $attributes, $user): RequestDispatch {
            $lockedRequest = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $requiresDispatch = $lockedRequest->processing?->requires_dispatch ?? $lockedRequest->service->requires_dispatch;
            if (! $requiresDispatch) {
                throw ValidationException::withMessages(['dispatch' => 'Dispatch is not required for this service.']);
            }
            $requiresPayment = $lockedRequest->processing?->requires_payment_before_processing ?? $lockedRequest->service->requires_payment_before_processing;
            if (($requiresPayment && $lockedRequest->payment_status !== 'received') || ! $lockedRequest->file_number) {
                throw ValidationException::withMessages(['dispatch' => 'Dispatch requires a file number and, when configured, received payment.']);
            }

            $allowedWorkflowStatuses = ['ready_for_registration', 'dispatched', 'completed', 'archived'];
            if (! in_array($lockedRequest->status, $allowedWorkflowStatuses, true)) {
                throw ValidationException::withMessages(['dispatch' => 'The request must be ready for registration before dispatch.']);
            }

            if ($attributes['dispatch_status'] === 'dispatched' && ! in_array($lockedRequest->status, ['ready_for_registration', 'dispatched'], true)) {
                throw ValidationException::withMessages(['dispatch_status' => 'This request cannot be marked dispatched from its current workflow status.']);
            }
            if ($attributes['dispatch_status'] === 'dispatched' && $lockedRequest->processing && $lockedRequest->processing->processing_stage !== 'ready_for_dispatch') {
                throw ValidationException::withMessages(['dispatch' => 'The processing stage must be Ready for Dispatch.']);
            }
            if ($attributes['dispatch_status'] === 'delivered' && ! $lockedRequest->dispatches()->where('dispatch_status', 'dispatched')->exists()) {
                throw ValidationException::withMessages(['dispatch_status' => 'A dispatched record is required before delivery can be recorded.']);
            }

            $dispatch = $lockedRequest->dispatches()->create([...$attributes, 'performed_by' => $user->id]);
            if ($attributes['dispatch_status'] === 'dispatched' && $lockedRequest->status === 'ready_for_registration') {
                $this->workflow->transition($lockedRequest, ['status' => 'dispatched', 'remarks' => 'Request dispatched.', 'is_visible_to_customer' => true], $user);
            }
            if ($attributes['dispatch_status'] === 'dispatched' && $lockedRequest->processing) {
                $this->processing->markDispatched($lockedRequest, $user);
            }

            return $dispatch;
        });
    }
}
