<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernmentChargeType extends Model
{
    protected $fillable = ['name_en', 'name_gu', 'default_amount', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['default_amount' => 'decimal:2', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function requestCharges(): HasMany
    {
        return $this->hasMany(RequestBillingGovernmentCharge::class);
    }
}
