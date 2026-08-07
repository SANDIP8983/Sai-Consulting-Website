<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceGovernmentCharge extends Model
{
    protected $fillable = ['name', 'amount', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
