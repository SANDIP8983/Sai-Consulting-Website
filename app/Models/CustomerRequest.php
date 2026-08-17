<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerRequest extends Model
{
    public const CURRENT_CASE_PLANNING_VERSION = 1;

    public const CHECKLIST_WORKFLOW_CUTOFF_AT = '2026-08-02 12:00:00';

    /**
     * Laravel ને જણાવો કે આ Model "requests" table વાપરે છે.
     */
    protected $table = 'requests';

    /**
     * Mass Assignable Fields
     */
    protected $fillable = [
        'reference_no',
        'submission_fingerprint',
        'file_number',
        'request_origin',
        'case_planning_version',
        'service_id',
        'name',
        'mobile',
        'whatsapp',
        'email',
        'address',
        'village', 'taluka', 'district',
        'property_village', 'property_taluka', 'property_district', 'property_address_remarks',
        'survey_numbers',
        'khata_number',
        'tp_number', 'final_plot_number', 'revenue_village',
        'details',
        'status', 'assigned_user_id', 'assigned_by', 'assigned_at',
        'case_approved_at', 'case_approved_by',
        'payment_status', 'amount_due', 'fee_updated_by', 'fee_updated_at', 'amount_paid', 'estimated_completion_date', 'completed_at', 'completion_customer_remark', 'completion_internal_note', 'closed_at', 'closure_customer_remark', 'closure_internal_note', 'closed_by', 'last_status_changed_at',
    ];

    protected function casts(): array
    {
        return ['amount_due' => 'decimal:2', 'amount_paid' => 'decimal:2', 'fee_updated_at' => 'datetime', 'estimated_completion_date' => 'date', 'completed_at' => 'datetime', 'closed_at' => 'datetime', 'last_status_changed_at' => 'datetime', 'case_approved_at' => 'datetime', 'assigned_at' => 'datetime', 'case_planning_version' => 'integer'];
    }

    /**
     * Service Relationship
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function requestServices(): HasMany
    {
        return $this->hasMany(RequestService::class, 'request_id');
    }

    public function billing(): HasOne
    {
        return $this->hasOne(RequestBilling::class, 'request_id');
    }

    /**
     * Uploaded Documents
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RequestDocument::class, 'request_id');
    }

    public function finalDocuments(): HasMany
    {
        return $this->hasMany(RequestFinalDocument::class, 'request_id');
    }

    public function finalDocumentDeliveries(): HasMany
    {
        return $this->hasMany(RequestFinalDocumentDelivery::class, 'request_id');
    }

    /**
     * Status History
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'request_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RequestPayment::class, 'request_id');
    }

    public function paymentSubmission(): HasOne
    {
        return $this->hasOne(RequestPaymentSubmission::class, 'request_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(RequestDispatch::class, 'request_id')->latest('dispatch_date');
    }

    public function dispatchHistory(): HasMany
    {
        return $this->hasMany(RequestDispatchHistory::class, 'request_id');
    }

    public function processing(): HasOne
    {
        return $this->hasOne(RequestProcessingDetail::class, 'request_id');
    }

    public function processingHistory(): HasMany
    {
        return $this->hasMany(RequestProcessingHistory::class, 'request_id');
    }

    public function caseActionHistory(): HasMany
    {
        return $this->hasMany(RequestCaseActionHistory::class, 'request_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(RequestAssignmentHistory::class, 'request_id')->latest('assigned_at');
    }

    public function contactChangeHistory(): HasMany
    {
        return $this->hasMany(RequestContactChangeHistory::class, 'request_id')->latest('changed_at');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function usesChecklistWorkflow(): bool
    {
        return $this->case_planning_version >= self::CURRENT_CASE_PLANNING_VERSION;
    }

    public function allSelectedServicesRejected(): bool
    {
        if ($this->relationLoaded('requestServices')) {
            return $this->requestServices->isNotEmpty()
                && $this->requestServices->every(fn ($service): bool => $service->status === 'rejected');
        }

        return $this->requestServices()->exists()
            && ! $this->requestServices()->where('status', '!=', 'rejected')->exists();
    }

    public function lifecycleStatus(): string
    {
        return $this->shouldDeriveRejectedLifecycle() ? 'rejected' : $this->status;
    }

    public function shouldDeriveRejectedLifecycle(): bool
    {
        return in_array($this->status, ['received', 'under_review', 'need_documents', 'approved', 'payment_pending', 'rejected'], true)
            && $this->allSelectedServicesRejected();
    }

    public function feeUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fee_updated_by');
    }

    public function isOffline(): bool
    {
        return $this->request_origin === 'offline';
    }
}
