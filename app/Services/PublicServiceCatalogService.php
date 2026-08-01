<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PublicServiceCatalogService
{
    public function paginate(?string $search): LengthAwarePaginator
    {
        return Service::query()
            ->where('is_active', true)
            ->withCount(['activeRequiredDocuments as required_documents_count'])
            ->when($search, fn ($query, string $term) => $query->where(function ($query) use ($term): void {
                $query->where('name_gu', 'like', "%{$term}%")
                    ->orWhere('name_en', 'like', "%{$term}%")
                    ->orWhere('description_en', 'like', "%{$term}%")
                    ->orWhere('description_gu', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            }))
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->paginate(24)
            ->withQueryString();
    }

    public function findActiveBySlug(string $slug): Service
    {
        return Service::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->with('activeRequiredDocuments')
            ->withCount(['activeRequiredDocuments as required_documents_count'])
            ->firstOrFail();
    }

    /** @return Collection<int, Service> */
    public function relatedTo(Service $service): Collection
    {
        return Service::query()
            ->where('is_active', true)
            ->whereKeyNot($service->id)
            ->withCount(['activeRequiredDocuments as required_documents_count'])
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->limit(3)
            ->get();
    }
}
