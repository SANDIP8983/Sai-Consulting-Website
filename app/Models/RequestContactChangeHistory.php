<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestContactChangeHistory extends Model
{
    protected $fillable = ['request_id', 'changed_by', 'changed_fields', 'masked_old_values', 'masked_new_values', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_fields' => 'array', 'masked_old_values' => 'array', 'masked_new_values' => 'array', 'changed_at' => 'datetime'];
    }

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
