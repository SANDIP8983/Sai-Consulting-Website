<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestRevenueEntry extends Model
{
    public const STATUSES = ['not_started', 'application_preparation', 'submitted', 'entry_number_received', 'certification_pending', 'certified', 'rejected', 'cancelled'];

    public const COMPLETED_STATUSES = ['certified', 'cancelled'];

    protected $fillable = ['request_id', 'entry_number', 'entry_date', 'application_number', 'application_date', 'village', 'taluka', 'district', 'survey_numbers', 'status', 'submitted_at', 'certified_at', 'rejected_at', 'rejection_reason', 'admin_notes', 'customer_note', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'application_date' => 'date', 'survey_numbers' => 'array', 'submitted_at' => 'datetime', 'certified_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RequestRevenueEntryDocument::class, 'request_revenue_entry_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(RequestRevenueEntryHistory::class, 'request_revenue_entry_id');
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, self::COMPLETED_STATUSES, true);
    }
}
