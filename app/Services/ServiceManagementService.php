<?php

namespace App\Services;

use App\Models\Service;
use App\Models\CommonRequiredDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceManagementService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Service::query()
            ->withCount('requiredDocuments')
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->where(function ($query) use ($term): void {
                $query->where('name_en', 'like', "%{$term}%")
                    ->orWhere('name_gu', 'like', "%{$term}%");
            }))
            ->when(array_key_exists('active', $filters) && $filters['active'] !== null, fn ($query) => $query->where('is_active', (bool) $filters['active']))
            ->when(($filters['availability'] ?? null) === 'online', fn ($query) => $query->where('available_online', true))
            ->when(($filters['availability'] ?? null) === 'offline', fn ($query) => $query->where('available_offline', true))
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Service
    {
        return DB::transaction(function () use ($attributes): Service {
            $service = Service::query()->create([
                ...$this->serviceAttributes($attributes),
                'slug' => $this->uniqueSlug($attributes['name_en']),
            ]);

            $this->syncRequiredDocuments($service, $attributes['documents'] ?? []);
            $this->syncGovernmentCharges($service, $attributes['government_charge_items'] ?? []);

            return $service;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Service $service, array $attributes): void
    {
        DB::transaction(function () use ($service, $attributes): void {
            $service->update($this->serviceAttributes($attributes));
            $this->syncRequiredDocuments($service, $attributes['documents'] ?? []);
            $this->syncGovernmentCharges($service, $attributes['government_charge_items'] ?? []);
        });
    }

    public function delete(Service $service): bool
    {
        if ($service->requests()->exists() || $service->requestServices()->exists()) {
            return false;
        }

        $service->delete();

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function serviceAttributes(array $attributes): array
    {
        return [
            ...Arr::only($attributes, [
                'name_en',
                'name_gu',
                'short_description',
                'description',
                'notes',
                'description_gu',
                'description_en',
                'customer_instructions',
                'important_notes',
                'disclaimer',
                'processing_time_label',
                'service_fee',
                'gst_rate',
                'government_charges',
                'estimated_days',
                'sort_order',
                'is_active',
            ]),
            'description' => $attributes['description_en'] ?? $attributes['description'] ?? null,
            'description_en' => $attributes['description_en'] ?? $attributes['description'] ?? null,
            'notes' => $attributes['customer_instructions'] ?? $attributes['notes'] ?? null,
            'customer_instructions' => $attributes['customer_instructions'] ?? $attributes['notes'] ?? null,
            'available_online' => (bool) ($attributes['available_online'] ?? false),
            'available_offline' => (bool) ($attributes['available_offline'] ?? false),
            'requires_property_documents' => (bool) ($attributes['requires_property_documents'] ?? false),
            'requires_dispatch' => (bool) ($attributes['requires_dispatch'] ?? false),
            'requires_payment_before_processing' => (bool) ($attributes['requires_payment_before_processing'] ?? false),
            'uses_drafting_workflow' => (bool) ($attributes['uses_drafting_workflow'] ?? false),
            'requires_token_booking' => (bool) ($attributes['requires_token_booking'] ?? false),
            'requires_registration' => (bool) ($attributes['requires_registration'] ?? false),
            'requires_certified_copy' => (bool) ($attributes['requires_certified_copy'] ?? false),
        ];
    }

    /**
     * @param  array<int, array{name_en: string, name_gu: string, sort_order: int}>  $documents
     */
    private function syncRequiredDocuments(Service $service, array $documents): void
    {
        $retainedIds = [];
        foreach ($documents as $document) {
            $normalized = Str::of($document['name_en'])->trim()->lower()->squish()->value();
            $master = CommonRequiredDocument::query()->firstOrCreate(['normalized_name' => $normalized], [
                'name_en' => $document['name_en'], 'name_gu' => $document['name_gu'], 'allowed_file_types' => $document['allowed_file_types'] ?? ['pdf', 'jpg', 'jpeg', 'png'], 'max_upload_size_kb' => $document['max_upload_size_kb'] ?? 10240, 'is_active' => true, 'is_common' => false,
            ]);
            $attributes = [
                ...Arr::only($document, ['name_en', 'name_gu', 'allowed_file_types', 'max_upload_size_kb', 'sort_order']),
                'is_mandatory' => (bool) ($document['is_mandatory'] ?? true),
                'common_required_document_id' => $master->id,
                'allowed_file_types' => $document['allowed_file_types'] ?? ['pdf', 'jpg', 'jpeg', 'png'],
                'max_upload_size_kb' => $document['max_upload_size_kb'] ?? 10240,
            ];
            $existing = isset($document['id'])
                ? $service->requiredDocuments()->whereKey($document['id'])->first()
                : $service->requiredDocuments()->where('common_required_document_id', $master->id)->first();
            if ($existing) {
                $service->requiredDocuments()->where('common_required_document_id', $master->id)->whereKeyNot($existing->id)->get()->each(fn ($duplicate) => $duplicate->requestDocuments()->exists() ? $duplicate->delete() : $duplicate->forceDelete());
                $existing->update($attributes);
                $retainedIds[] = $existing->id;
            } else {
                $retainedIds[] = $service->requiredDocuments()->create($attributes)->id;
            }
        }

        $service->requiredDocuments()->whereNotIn('id', $retainedIds)->where(function ($query): void {
            $query->whereNull('common_required_document_id')
                ->orWhereHas('commonDocument', fn ($master) => $master->where('is_common', false));
        })->get()->each(function ($document): void {
            $document->requestDocuments()->exists() ? $document->delete() : $document->forceDelete();
        });
    }

    private function syncGovernmentCharges(Service $service, array $items): void
    {
        if ($items === []) {
            return;
        }
        $retained = [];
        foreach ($items as $item) {
            $attributes = [
                ...Arr::only($item, ['name', 'amount', 'description', 'sort_order']),
                'is_active' => (bool) ($item['is_active'] ?? false),
            ];
            $charge = isset($item['id']) ? $service->governmentChargeItems()->whereKey($item['id'])->first() : null;
            if ($charge) {
                $charge->update($attributes);
            } else {
                $charge = $service->governmentChargeItems()->create($attributes);
            }
            $retained[] = $charge->id;
        }
        $service->governmentChargeItems()->whereNotIn('id', $retained)->delete();
        $service->updateQuietly(['government_charges' => $service->governmentChargeItems()->where('is_active', true)->sum('amount')]);
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
