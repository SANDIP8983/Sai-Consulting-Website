<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name_en',
        'name_gu',
        'slug',
        'description',
        'service_fee',
        'advance_percentage',
        'estimated_days',
        'required_documents',
        'notes',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'service_fee' => 'decimal:2',
            'advance_percentage' => 'integer',
            'estimated_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CustomerRequest::class);
    }

    public function requiredDocuments(): HasMany
    {
        return $this->hasMany(ServiceRequiredDocument::class)
            ->orderBy('sort_order');
    }
}
