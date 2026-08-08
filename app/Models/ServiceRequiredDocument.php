<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRequiredDocument extends Model
{
    use SoftDeletes;

    public const REQUIREMENT_TYPES = ['required', 'optional', 'any_one_required', 'not_applicable'];

    protected static function booted(): void
    {
        static::saving(function (ServiceRequiredDocument $document): void {
            if (! $document->exists && ! array_key_exists('requirement_type', $document->getAttributes())) {
                $document->requirement_type = $document->is_mandatory ? 'required' : 'optional';
            }
        });
    }

    protected $fillable = [
        'service_id',
        'common_required_document_id',
        'name_en',
        'name_gu',
        'is_mandatory',
        'requirement_type',
        'is_active',
        'allowed_file_types',
        'max_upload_size_kb',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'allowed_file_types' => 'array',
            'max_upload_size_kb' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function commonDocument(): BelongsTo
    {
        return $this->belongsTo(CommonRequiredDocument::class, 'common_required_document_id')->withTrashed();
    }

    public function requestDocuments(): HasMany
    {
        return $this->hasMany(RequestDocument::class);
    }
}
