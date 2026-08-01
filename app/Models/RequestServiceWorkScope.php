<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestServiceWorkScope extends Model
{
    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = ['request_service_id', 'work_scope_item_id', 'name_en_snapshot', 'name_gu_snapshot', 'is_custom', 'status', 'internal_note', 'display_order', 'selected_by', 'completed_at'];

    protected function casts(): array
    {
        return ['is_custom' => 'boolean', 'display_order' => 'integer', 'completed_at' => 'datetime'];
    }

    public function requestService(): BelongsTo
    {
        return $this->belongsTo(RequestService::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(WorkScopeItem::class, 'work_scope_item_id');
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }
}
