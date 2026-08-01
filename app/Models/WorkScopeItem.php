<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkScopeItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['name_en', 'name_gu', 'normalized_name', 'is_active', 'display_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'display_order' => 'integer'];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_work_scope_defaults')->withPivot(['is_default', 'display_order'])->withTimestamps();
    }

    public function requestScopes(): HasMany
    {
        return $this->hasMany(RequestServiceWorkScope::class);
    }
}
