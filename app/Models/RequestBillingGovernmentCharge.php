<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestBillingGovernmentCharge extends Model
{
    protected $fillable = ['government_charge_type_id', 'name', 'name_gu', 'amount', 'note', 'display_order'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'display_order' => 'integer'];
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(RequestBilling::class, 'request_billing_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(GovernmentChargeType::class, 'government_charge_type_id');
    }
}
