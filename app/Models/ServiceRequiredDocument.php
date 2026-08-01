<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRequiredDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'service_id',
        'name_en',
        'name_gu',
        'is_mandatory',
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

    public function requestDocuments(): HasMany
    {
        return $this->hasMany(RequestDocument::class);
    }
}
