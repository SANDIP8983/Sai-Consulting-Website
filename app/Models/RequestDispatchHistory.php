<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDispatchHistory extends Model
{
    protected $fillable = ['request_id', 'request_dispatch_id', 'action', 'from_status', 'to_status', 'old_values', 'new_values', 'reason', 'changed_by'];

    protected function casts(): array { return ['old_values' => 'array', 'new_values' => 'array']; }
    public function dispatch(): BelongsTo { return $this->belongsTo(RequestDispatch::class, 'request_dispatch_id'); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
