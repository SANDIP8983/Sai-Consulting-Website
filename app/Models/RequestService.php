<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestService extends Model
{
    protected $fillable = ['service_id', 'added_by', 'is_admin_added', 'service_name_en_snapshot', 'service_name_gu_snapshot', 'professional_fee', 'original_professional_fee', 'discount_type', 'discount_value', 'discount_amount', 'discount_reason', 'net_professional_fee', 'gst_rate', 'gst_amount', 'government_charges', 'government_charges_snapshot', 'final_total', 'pricing_locked_at', 'pricing_unlocked_at', 'pricing_unlocked_by', 'estimated_days', 'required_documents_snapshot', 'status', 'approved_at', 'rejected_at', 'decision_notes', 'internal_note', 'customer_decision_message', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['is_admin_added' => 'boolean', 'professional_fee' => 'decimal:2', 'original_professional_fee' => 'decimal:2', 'discount_value' => 'decimal:2', 'discount_amount' => 'decimal:2', 'net_professional_fee' => 'decimal:2', 'gst_rate' => 'decimal:2', 'gst_amount' => 'decimal:2', 'government_charges' => 'decimal:2', 'government_charges_snapshot' => 'array', 'final_total' => 'decimal:2', 'pricing_locked_at' => 'datetime', 'pricing_unlocked_at' => 'datetime', 'estimated_days' => 'integer', 'required_documents_snapshot' => 'array', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function billingProfessionalFee(): float
    {
        // Older base-service rows used zero as an unset placeholder. A deliberate
        // zero adjustment is distinguishable because the required reason is saved.
        if (! $this->is_admin_added && (float) $this->professional_fee === 0.0 && (float) $this->original_professional_fee > 0 && blank($this->internal_note)) {
            return (float) $this->original_professional_fee;
        }

        return (float) ($this->professional_fee ?? $this->original_professional_fee ?? 0);
    }

    public function isAddOn(): bool
    {
        return $this->is_admin_added;
    }

    public function billingRoleLabel(): string
    {
        return $this->isAddOn() ? 'Add-on / Additional Charge' : 'Base Service';
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function total(): float
    {
        return $this->final_total !== null ? (float) $this->final_total : (float) $this->professional_fee + ((float) $this->professional_fee * (float) $this->gst_rate / 100) + (float) $this->government_charges;
    }

    public function approvalHistory()
    {
        return $this->hasMany(RequestServiceApprovalHistory::class);
    }

    public function workScopes()
    {
        return $this->hasMany(RequestServiceWorkScope::class)->orderBy('display_order')->orderBy('id');
    }
}
