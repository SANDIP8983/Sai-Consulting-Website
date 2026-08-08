<?php

namespace App\Models;

use App\Support\PublicDocumentPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected static function booted(): void
    {
        static::created(function (Service $service): void {
            CommonRequiredDocument::query()->where('is_active', true)->where('is_common', true)->get()->filter(fn (CommonRequiredDocument $document) => PublicDocumentPolicy::isSafe($document->name_en))->each(fn (CommonRequiredDocument $document) => $service->requiredDocuments()->firstOrCreate(
                ['common_required_document_id' => $document->id],
                ['name_en' => $document->name_en, 'name_gu' => $document->name_gu, 'is_mandatory' => false, 'is_active' => true, 'sort_order' => 999, 'allowed_file_types' => $document->allowed_file_types ?? ['pdf', 'jpg', 'jpeg', 'png'], 'max_upload_size_kb' => $document->max_upload_size_kb ?? 10240],
            ));
        });
    }

    protected $fillable = [
        'name_en',
        'name_gu',
        'slug',
        'short_description',
        'description',
        'description_gu',
        'description_en',
        'customer_instructions',
        'important_notes',
        'disclaimer',
        'processing_time_label',
        'service_fee',
        'gst_rate',
        'government_charges',
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
            'gst_rate' => 'decimal:2',
            'government_charges' => 'decimal:2',
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

    public function requestServices(): HasMany
    {
        return $this->hasMany(RequestService::class);
    }

    public function defaultWorkScopes(): BelongsToMany
    {
        return $this->belongsToMany(WorkScopeItem::class, 'service_work_scope_defaults')->withPivot(['is_default', 'display_order'])->withTimestamps()->orderByPivot('display_order');
    }

    public function governmentChargeItems(): HasMany
    {
        return $this->hasMany(ServiceGovernmentCharge::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeGovernmentChargeItems(): HasMany
    {
        return $this->hasMany(ServiceGovernmentCharge::class)->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function requiredDocuments(): HasMany
    {
        return $this->hasMany(ServiceRequiredDocument::class)
            ->orderBy('sort_order');
    }

    public function activeRequiredDocuments(): HasMany
    {
        $query = $this->hasMany(ServiceRequiredDocument::class)
            ->where('is_active', true)
            ->where('requirement_type', '!=', 'not_applicable')
            ->where(fn ($query) => $query->whereNull('common_required_document_id')
                ->orWhereHas('commonDocument', fn ($master) => $master->where('is_active', true)))
            ->orderByDesc('is_mandatory')
            ->orderBy('sort_order')
            ->orderBy('id');
        foreach (PublicDocumentPolicy::PROHIBITED_TERMS as $term) {
            $query->whereRaw('LOWER(name_en) NOT LIKE ?', ['%'.$term.'%']);
        }

        return $query;
    }
}
