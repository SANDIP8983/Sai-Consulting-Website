<?php

namespace App\Services;

use App\Models\CommonRequiredDocument;
use App\Models\Service;
use App\Models\ServiceRequiredDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceRequiredDocumentManagementService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return ServiceRequiredDocument::query()
            ->with('service')
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where(function ($query) use ($term): void {
                $query->where('name_en', 'like', '%'.$term.'%')
                    ->orWhere('name_gu', 'like', '%'.$term.'%')
                    ->orWhereHas('service', fn ($query) => $query->where('name_en', 'like', '%'.$term.'%')->orWhere('name_gu', 'like', '%'.$term.'%'));
            }))
            ->when($filters['service_id'] ?? null, fn ($query, $serviceId) => $query->where('service_id', $serviceId))
            ->when(array_key_exists('active', $filters), fn ($query) => $query->where('is_active', (bool) $filters['active']))
            ->orderBy('service_id')->orderBy('sort_order')->orderBy('id')
            ->paginate(20)->withQueryString();
    }

    public function create(array $attributes): ServiceRequiredDocument
    {
        return DB::transaction(function () use ($attributes): ServiceRequiredDocument {
            $master = $this->master($attributes);
            Service::query()->pluck('id')->each(function (int $serviceId) use ($master): void {
                ServiceRequiredDocument::query()->firstOrCreate(
                    ['service_id' => $serviceId, 'common_required_document_id' => $master->id],
                    ['name_en' => $master->name_en, 'name_gu' => $master->name_gu, 'is_mandatory' => false, 'is_active' => false, 'sort_order' => 999, 'allowed_file_types' => $master->allowed_file_types ?? ['pdf', 'jpg', 'jpeg', 'png'], 'max_upload_size_kb' => $master->max_upload_size_kb ?? 10240],
                );
            });
            $configuration = ServiceRequiredDocument::query()->where('service_id', $attributes['service_id'])->where('common_required_document_id', $master->id)->firstOrFail();
            $configuration->update($this->attributes($attributes) + ['common_required_document_id' => $master->id]);

            return $configuration;
        });
    }

    public function update(ServiceRequiredDocument $document, array $attributes): void
    {
        DB::transaction(function () use ($document, $attributes): void {
            $master = $document->commonDocument ?? $this->master($attributes);
            $master->update(['name_en' => $attributes['name_en'], 'name_gu' => $attributes['name_gu'], 'normalized_name' => $this->normalize($attributes['name_en'])]);
            $master->serviceConfigurations()->update(['name_en' => $attributes['name_en'], 'name_gu' => $attributes['name_gu']]);
            $document->update($this->attributes($attributes) + ['common_required_document_id' => $master->id]);
        });
    }

    public function delete(ServiceRequiredDocument $document): bool
    {
        $wasUsed = $document->requestDocuments()->exists();
        $wasUsed ? $document->delete() : $document->forceDelete();

        return $wasUsed;
    }

    public function reorder(int $serviceId, array $documentIds): void
    {
        $validIds = ServiceRequiredDocument::query()->where('service_id', $serviceId)->whereKey($documentIds)->pluck('id')->all();
        if (count($validIds) !== count($documentIds)) {
            throw ValidationException::withMessages(['documents' => 'Every document must belong to the selected service.']);
        }

        DB::transaction(function () use ($documentIds): void {
            foreach ($documentIds as $position => $documentId) {
                ServiceRequiredDocument::query()->whereKey($documentId)->update(['sort_order' => $position + 1]);
            }
        });
    }

    private function attributes(array $attributes): array
    {
        return [
            ...Arr::only($attributes, ['service_id', 'name_gu', 'name_en', 'sort_order']),
            'is_mandatory' => (bool) $attributes['is_mandatory'],
            'is_active' => (bool) $attributes['is_active'],
        ];
    }

    private function master(array $attributes): CommonRequiredDocument
    {
        $normalized = $this->normalize($attributes['name_en']);
        $master = CommonRequiredDocument::withTrashed()->firstOrCreate(
            ['normalized_name' => $normalized],
            ['name_en' => $attributes['name_en'], 'name_gu' => $attributes['name_gu'], 'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'], 'max_upload_size_kb' => 10240, 'is_active' => true, 'is_common' => true],
        );
        if (! $master->is_common) {
            $master->update(['is_common' => true, 'is_active' => true]);
        }

        return $master;
    }

    private function normalize(string $name): string
    {
        return Str::of($name)->trim()->lower()->squish()->value();
    }
}
