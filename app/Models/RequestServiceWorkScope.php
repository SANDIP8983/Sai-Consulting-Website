<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestServiceWorkScope extends Model
{
    public const STATUSES = ['pending', 'in_progress', 'completed', 'not_required', 'cancelled'];

    protected $fillable = ['request_service_id', 'work_scope_item_id', 'name_en_snapshot', 'name_gu_snapshot', 'is_custom', 'status', 'internal_note', 'customer_remark', 'resolution_reason', 'display_order', 'selected_by', 'started_at', 'completed_at', 'updated_by'];

    protected function casts(): array
    {
        return ['is_custom' => 'boolean', 'display_order' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(RequestServiceWorkScopeHistory::class);
    }
}
