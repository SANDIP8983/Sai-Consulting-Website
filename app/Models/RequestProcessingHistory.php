<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestProcessingHistory extends Model
{
    protected $fillable = ['request_id', 'from_stage', 'to_stage', 'remarks', 'is_visible_to_customer', 'changed_by'];

    protected function casts(): array
    {
        return ['is_visible_to_customer' => 'boolean'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
