<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequiredDocument extends Model
{
    protected $fillable = [
        'service_id',
        'name_en',
        'name_gu',
        'is_mandatory',
        'allowed_file_types',
        'max_upload_size_kb',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_mandatory' => 'boolean',
            'allowed_file_types' => 'array',
            'max_upload_size_kb' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
