<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestBilling extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['total_original_professional_fee' => 'decimal:2', 'discount_value' => 'decimal:2', 'discount_amount' => 'decimal:2', 'net_professional_fee' => 'decimal:2', 'gst_rate' => 'decimal:2', 'gst_amount' => 'decimal:2', 'government_charges_total' => 'decimal:2', 'grand_total' => 'decimal:2', 'applied_at' => 'datetime', 'pricing_locked_at' => 'datetime', 'pricing_unlocked_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(RequestBillingGovernmentCharge::class)->orderBy('display_order')->orderBy('id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(RequestBillingHistory::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pricing_unlocked_by');
    }

    public function isLocked(): bool
    {
        return $this->pricing_locked_at !== null && $this->pricing_unlocked_at === null;
    }
}
