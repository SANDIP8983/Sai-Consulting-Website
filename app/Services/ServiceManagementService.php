<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceManagementService
{
    public function paginate(): LengthAwarePaginator
    {
        return Service::query()
            ->withCount('requiredDocuments')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->paginate(15);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Service
    {
        return DB::transaction(function () use ($attributes): Service {
            $service = Service::query()->create([
                ...$this->serviceAttributes($attributes),
                'slug' => $this->uniqueSlug($attributes['name_en']),
            ]);

            $this->syncRequiredDocuments($service, $attributes['documents'] ?? []);

            return $service;
        });
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Service $service, array $attributes): void
    {
        DB::transaction(function () use ($service, $attributes): void {
            $service->update($this->serviceAttributes($attributes));
            $this->syncRequiredDocuments($service, $attributes['documents'] ?? []);
        });
    }

    public function delete(Service $service): bool
    {
        if ($service->requests()->exists()) {
            return false;
        }

        $service->delete();

        return true;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function serviceAttributes(array $attributes): array
    {
        return Arr::only($attributes, [
            'name_en',
            'name_gu',
            'description',
            'sort_order',
            'is_active',
        ]);
    }

    /**
     * @param array<int, array{name_en: string, name_gu: string, sort_order: int}> $documents
     */
    private function syncRequiredDocuments(Service $service, array $documents): void
    {
        $service->requiredDocuments()->delete();

        foreach ($documents as $document) {
            $service->requiredDocuments()->create($document);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'service';
        $slug = $baseSlug;
        $suffix = 2;

        while (Service::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
