<?php

namespace App\Services;

use App\Models\ServiceRequiredDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
        return ServiceRequiredDocument::query()->create($this->attributes($attributes));
    }

    public function update(ServiceRequiredDocument $document, array $attributes): void
    {
        $document->update($this->attributes($attributes));
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
}
