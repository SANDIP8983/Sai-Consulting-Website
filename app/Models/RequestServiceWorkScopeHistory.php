<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestServiceWorkScopeHistory extends Model
{
    protected $fillable = ['request_service_work_scope_id', 'request_id', 'action', 'from_status', 'to_status', 'reason', 'internal_note', 'customer_remark', 'changed_by'];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
