<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommonRequiredDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ['name_en', 'name_gu', 'normalized_name', 'allowed_file_types', 'max_upload_size_kb', 'is_active'];

    protected function casts(): array
    {
        return ['allowed_file_types' => 'array', 'max_upload_size_kb' => 'integer', 'is_active' => 'boolean'];
    }

    public function serviceConfigurations(): HasMany
    {
        return $this->hasMany(ServiceRequiredDocument::class);
    }

    protected static function booted(): void
    {
        static::created(function (CommonRequiredDocument $document): void {
            Service::query()->get(['id', 'requires_property_documents'])->each(fn (Service $service) => $document->serviceConfigurations()->firstOrCreate(
                ['service_id' => $service->id],
                ['name_en' => $document->name_en, 'name_gu' => $document->name_gu, 'is_mandatory' => false, 'is_active' => $service->requires_property_documents, 'sort_order' => 999, 'allowed_file_types' => $document->allowed_file_types, 'max_upload_size_kb' => $document->max_upload_size_kb],
            ));
        });
    }
}
