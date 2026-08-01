<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestServiceApprovalHistory extends Model
{
    protected $fillable = ['request_service_id', 'request_id', 'approved_by', 'pricing_snapshot', 'action', 'note'];

    protected function casts(): array
    {
        return ['pricing_snapshot' => 'array'];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
