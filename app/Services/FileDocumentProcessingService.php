<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestProcessingDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FileDocumentProcessingService
{
    public function __construct(private readonly RequestWorkflowService $workflow) {}

    public const STAGES = ['file_opened', 'documents_under_review', 'documents_incomplete', 'drafting_started', 'draft_ready', 'customer_verification_pending', 'correction_required', 'final_draft_ready', 'token_booking_pending', 'token_booked', 'registration_pending', 'registered', 'certified_copy_pending', 'certified_copy_received', 'ready_for_dispatch', 'dispatched', 'completed'];

    private const TRANSITIONS = [
        'file_opened' => ['documents_under_review'],
        'documents_under_review' => ['documents_incomplete', 'drafting_started', 'token_booking_pending', 'registration_pending', 'ready_for_dispatch'],
        'documents_incomplete' => ['documents_under_review'],
        'drafting_started' => ['draft_ready'], 'draft_ready' => ['customer_verification_pending'],
        'customer_verification_pending' => ['correction_required', 'final_draft_ready'],
        'correction_required' => ['drafting_started'],
        'final_draft_ready' => ['token_booking_pending', 'registration_pending', 'ready_for_dispatch'],
        'token_booking_pending' => ['token_booked'], 'token_booked' => ['registration_pending', 'ready_for_dispatch'],
        'registration_pending' => ['registered'], 'registered' => ['certified_copy_pending', 'ready_for_dispatch'],
        'certified_copy_pending' => ['certified_copy_received'], 'certified_copy_received' => ['ready_for_dispatch'],
        'ready_for_dispatch' => [], 'dispatched' => ['completed'], 'completed' => [],
    ];

    public function transitions(RequestProcessingDetail $processing): array
    {
        return self::TRANSITIONS[$processing->processing_stage] ?? [];
    }

    public function open(CustomerRequest $request, array $attributes, User $user): RequestProcessingDetail
    {
        return DB::transaction(function () use ($request, $attributes, $user): RequestProcessingDetail {
            $locked = CustomerRequest::query()->with('service')->lockForUpdate()->findOrFail($request->id);
            if (! $locked->file_number || ! in_array($locked->status, $this->eligibleRequestStatuses(), true)) {
                throw ValidationException::withMessages(['processing' => 'Processing can begin only after approval and file-number assignment.']);
            }
            if ($locked->processing()->exists()) {
                throw ValidationException::withMessages(['processing' => 'Processing has already been opened for this request.']);
            }
            $processing = $locked->processing()->create([
                'processing_stage' => 'file_opened', 'file_opened_at' => $attributes['file_opened_at'] ?? now()->toDateString(),
                'priority' => $attributes['priority'] ?? 'normal', 'file_in_charge_user_id' => $attributes['file_in_charge_user_id'] ?? null,
                'internal_file_note' => $attributes['internal_file_note'] ?? null,
                'uses_drafting_workflow' => $locked->service->uses_drafting_workflow,
                'requires_token_booking' => $locked->service->requires_token_booking,
                'requires_registration' => $locked->service->requires_registration,
                'requires_certified_copy' => $locked->service->requires_certified_copy,
            ]);
            $locked->processingHistory()->create(['from_stage' => null, 'to_stage' => 'file_opened', 'remarks' => $attributes['customer_remark'] ?? null, 'is_visible_to_customer' => filled($attributes['customer_remark'] ?? null), 'changed_by' => $user->id]);
            return $processing;
        });
    }

    public function transition(CustomerRequest $request, string $to, array $attributes, User $user): RequestProcessingDetail
    {
        return DB::transaction(function () use ($request, $to, $attributes, $user): RequestProcessingDetail {
            $processing = RequestProcessingDetail::query()->where('request_id', $request->id)->lockForUpdate()->firstOrFail();
            $from = $processing->processing_stage;
            if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['processing_stage' => 'This processing-stage transition is not allowed.']);
            }
            $this->enforceStageRules($request->fresh(), $processing, $to, $attributes);
            $processing->update(['processing_stage' => $to, ...$this->automaticDates($to, $attributes)]);
            $request->processingHistory()->create(['from_stage' => $from, 'to_stage' => $to, 'remarks' => $attributes['remarks'] ?? null, 'is_visible_to_customer' => (bool) ($attributes['is_visible_to_customer'] ?? false), 'changed_by' => $user->id]);
            $this->syncRequestStatus($request->fresh(), $to, $user);
            return $processing->refresh();
        });
    }

    public function updateFileInformation(CustomerRequest $request, array $attributes, User $user): RequestProcessingDetail
    {
        return $this->updateInformation($request, $attributes, $user, 'File information updated.');
    }

    public function updateDrafting(CustomerRequest $request, array $attributes, User $user): RequestProcessingDetail
    {
        return $this->updateInformation($request, $attributes, $user, 'Drafting information updated.', 'drafting_customer_remark');
    }

    public function updateRegistration(CustomerRequest $request, array $attributes, User $user): RequestProcessingDetail
    {
        return $this->updateInformation($request, $attributes, $user, 'Registration information updated.', 'registration_customer_remark');
    }

    public function updatePostRegistration(CustomerRequest $request, array $attributes, User $user): RequestProcessingDetail
    {
        return $this->updateInformation($request, $attributes, $user, 'Post-registration information updated.');
    }

    public function storeRegisteredScan(CustomerRequest $request, UploadedFile $file, User $user): RequestProcessingDetail
    {
        $path = $file->store("customer-requests/{$request->id}", 'local');
        if ($path === false) { throw new \RuntimeException('The registered document scan could not be stored.'); }
        try {
            return DB::transaction(function () use ($request, $file, $path, $user): RequestProcessingDetail {
                $processing = RequestProcessingDetail::query()->where('request_id', $request->id)->lockForUpdate()->firstOrFail();
                $document = $request->documents()->create(['file_name' => $file->getClientOriginalName(), 'file_path' => $path, 'file_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'source' => 'admin']);
                $processing->update(['registered_document_id' => $document->id, 'registered_scan_received_at' => now()]);
                $request->processingHistory()->create(['from_stage' => $processing->processing_stage, 'to_stage' => $processing->processing_stage, 'remarks' => 'Registered document scan received.', 'is_visible_to_customer' => false, 'changed_by' => $user->id]);
                return $processing->refresh();
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    private function updateInformation(CustomerRequest $request, array $attributes, User $user, string $auditRemark, ?string $customerRemarkKey = null): RequestProcessingDetail
    {
        return DB::transaction(function () use ($request, $attributes, $user, $auditRemark, $customerRemarkKey): RequestProcessingDetail {
            $processing = RequestProcessingDetail::query()->where('request_id', $request->id)->lockForUpdate()->firstOrFail();
            $processing->update($attributes);
            $customerRemark = $customerRemarkKey ? ($attributes[$customerRemarkKey] ?? null) : null;
            $request->processingHistory()->create(['from_stage' => $processing->processing_stage, 'to_stage' => $processing->processing_stage, 'remarks' => $customerRemark ?: $auditRemark, 'is_visible_to_customer' => filled($customerRemark), 'changed_by' => $user->id]);
            return $processing->refresh();
        });
    }

    private function syncRequestStatus(CustomerRequest $request, string $stage, User $user): void
    {
        $target = match ($stage) {
            'drafting_started' => 'draft_in_progress',
            'draft_ready', 'customer_verification_pending', 'correction_required' => 'ready_for_verification',
            'final_draft_ready' => 'customer_approved',
            'token_booking_pending', 'token_booked', 'registration_pending', 'registered', 'certified_copy_pending', 'certified_copy_received', 'ready_for_dispatch' => 'ready_for_registration',
            default => null,
        };
        if ($target && $request->status !== $target && in_array($target, $this->workflow->transitions($request), true)) {
            $this->workflow->transition($request, ['status' => $target, 'remarks' => null, 'is_visible_to_customer' => false], $user);
        }
    }

    private function enforceStageRules(CustomerRequest $request, RequestProcessingDetail $processing, string $to, array $attributes): void
    {
        $registrationStages = ['token_booking_pending', 'token_booked', 'registration_pending', 'registered', 'certified_copy_pending', 'certified_copy_received', 'ready_for_dispatch'];
        if (in_array($to, $registrationStages, true) && $request->payment_status !== 'received') {
            throw ValidationException::withMessages(['processing_stage' => 'Payment must be received before registration processing.']);
        }
        if ($to === 'token_booked' && (blank($attributes['token_number'] ?? $processing->token_number) || blank($attributes['token_scheduled_at'] ?? $processing->token_scheduled_at))) {
            throw ValidationException::withMessages(['token_number' => 'Token number and scheduled date/time are required.']);
        }
        if ($to === 'registered' && (blank($attributes['registration_date'] ?? $processing->registration_date) || blank($attributes['registration_number'] ?? $processing->registration_number))) {
            throw ValidationException::withMessages(['registration_number' => 'Registration date and registration/document number are required.']);
        }
        if ($to === 'certified_copy_received' && blank($attributes['certified_copy_received_date'] ?? $processing->certified_copy_received_date)) {
            throw ValidationException::withMessages(['certified_copy_received_date' => 'Certified copy received date is required.']);
        }
    }

    private function automaticDates(string $stage, array $attributes): array
    {
        return match ($stage) {
            'drafting_started' => ['draft_started_at' => $attributes['draft_started_at'] ?? now()->toDateString()],
            'draft_ready' => ['draft_ready_at' => $attributes['draft_ready_at'] ?? now()->toDateString()],
            'final_draft_ready' => ['final_draft_at' => $attributes['final_draft_at'] ?? now()->toDateString()],
            'ready_for_dispatch' => ['ready_for_dispatch_date' => $attributes['ready_for_dispatch_date'] ?? now()->toDateString()],
            'completed' => ['actual_completion_date' => $attributes['actual_completion_date'] ?? now()->toDateString()], default => [],
        };
    }

    private function eligibleRequestStatuses(): array
    {
        return ['approved', 'payment_pending', 'payment_received', 'draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration', 'dispatched', 'completed', 'archived'];
    }
}
