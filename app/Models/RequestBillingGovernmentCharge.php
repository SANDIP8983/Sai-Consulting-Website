<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestBillingGovernmentCharge extends Model
{
    protected $fillable = ['name', 'amount', 'note', 'display_order'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'display_order' => 'integer'];
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(RequestBilling::class, 'request_billing_id');
    }
}
