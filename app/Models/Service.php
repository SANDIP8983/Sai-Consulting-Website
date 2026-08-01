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
        'uses_drafting_workflow',
        'requires_token_booking',
        'requires_registration',
        'requires_certified_copy',
    ];

    protected function casts(): array
    {
        return [
            'service_fee' => 'decimal:2',
            'advance_percentage' => 'integer',
            'estimated_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'uses_drafting_workflow' => 'boolean',
            'requires_token_booking' => 'boolean',
            'requires_registration' => 'boolean',
            'requires_certified_copy' => 'boolean',
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
