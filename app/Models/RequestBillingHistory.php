<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestBillingHistory extends Model
{
    protected $fillable = ['request_id', 'changed_by', 'action', 'pricing_snapshot', 'reason'];
    protected function casts(): array { return ['pricing_snapshot' => 'array']; }
    public function billing(): BelongsTo { return $this->belongsTo(RequestBilling::class, 'request_billing_id'); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
