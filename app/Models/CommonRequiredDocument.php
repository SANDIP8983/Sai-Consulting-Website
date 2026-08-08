<?php

namespace App\Models;

use App\Support\PublicDocumentPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommonRequiredDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name_en', 'name_gu', 'normalized_name', 'allowed_file_types', 'max_upload_size_kb', 'is_active', 'is_common', 'display_order'];

    protected function casts(): array
    {
        return ['allowed_file_types' => 'array', 'max_upload_size_kb' => 'integer', 'is_active' => 'boolean', 'is_common' => 'boolean', 'display_order' => 'integer'];
    }

    public function serviceConfigurations(): HasMany
    {
        return $this->hasMany(ServiceRequiredDocument::class);
    }

    protected static function booted(): void
    {
        static::created(fn (CommonRequiredDocument $document) => self::synchronize($document));
        static::updated(function (CommonRequiredDocument $document): void {
            if ($document->wasChanged(['is_active', 'is_common'])) {
                self::synchronize($document);
            }
            if ($document->wasChanged(['name_en', 'name_gu'])) {
                $document->serviceConfigurations()->update(['name_en' => $document->name_en, 'name_gu' => $document->name_gu]);
            }
        });
    }

    private static function synchronize(CommonRequiredDocument $document): void
    {
        if (! $document->is_active || ! $document->is_common || ! PublicDocumentPolicy::isSafe($document->name_en)) {
            return;
        }
        Service::query()->where('is_active', true)->get(['id'])->each(fn (Service $service) => $document->serviceConfigurations()->firstOrCreate(
            ['service_id' => $service->id],
            ['name_en' => $document->name_en, 'name_gu' => $document->name_gu, 'is_mandatory' => false, 'is_active' => true, 'sort_order' => 999, 'allowed_file_types' => $document->allowed_file_types ?? ['pdf', 'jpg', 'jpeg', 'png'], 'max_upload_size_kb' => $document->max_upload_size_kb ?? 10240],
        ));
    }
}
