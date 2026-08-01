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
        'short_description',
        'description',
        'service_fee',
        'advance_percentage',
        'estimated_days',
        'required_documents',
        'notes',
        'is_active',
        'sort_order',
        'available_online',
        'available_offline',
        'requires_property_documents',
        'requires_dispatch',
        'requires_payment_before_processing',
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
            'available_online' => 'boolean',
            'available_offline' => 'boolean',
            'requires_property_documents' => 'boolean',
            'requires_dispatch' => 'boolean',
            'requires_payment_before_processing' => 'boolean',
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

    public function activeRequiredDocuments(): HasMany
    {
        return $this->hasMany(ServiceRequiredDocument::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
