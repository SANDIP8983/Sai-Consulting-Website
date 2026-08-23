<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestRevenueEntryHistory extends Model
{
    protected $fillable = ['request_id', 'request_revenue_entry_id', 'action', 'from_status', 'to_status', 'old_values', 'new_values', 'reason', 'changed_by'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
