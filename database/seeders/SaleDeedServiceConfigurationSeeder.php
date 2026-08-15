<?php

namespace Database\Seeders;

use App\Models\CommonRequiredDocument;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaleDeedServiceConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            Service::query()->where('slug', 'sale-deed')->update(['is_active' => false, 'available_online' => false, 'available_offline' => false]);

            $included = Service::query()->where('slug', 'sale-deed')->first()?->defaultWorkScopes()->pluck('work_scope_items.id')->all() ?? [];
            $definitions = [
                'agricultural-land-sale-deed' => ['name_en' => 'Agricultural Land Sale Deed', 'name_gu' => 'ખેતીની જમીનનો વેચાણ દસ્તાવેજ', 'any' => ['7-12-extract', '8-a-extract']],
                'non-agricultural-property-sale-deed' => ['name_en' => 'Non-Agricultural Property Sale Deed', 'name_gu' => 'બિનખેતી મિલકતનો વેચાણ દસ્તાવેજ', 'any' => ['property-card', 'assessment-register-village-form-2']],
            ];
            foreach ($definitions as $slug => $definition) {
                $service = Service::query()->firstOrCreate(['slug' => $slug], [
                    'name_en' => $definition['name_en'], 'name_gu' => $definition['name_gu'], 'service_fee' => 1999,
                    'gst_rate' => 18, 'advance_percentage' => 100, 'estimated_days' => 4, 'processing_time_label' => '4 Working Days',
                    'is_active' => true, 'available_online' => true, 'available_offline' => true,
                    'requires_property_documents' => true, 'requires_dispatch' => true,
                    'requires_payment_before_processing' => true, 'uses_drafting_workflow' => true,
                    'requires_token_booking' => true, 'requires_registration' => true, 'requires_certified_copy' => true,
                ]);
                $newService = $service->wasRecentlyCreated;
                if ($service->defaultWorkScopes()->doesntExist() && $included !== []) {
                    $service->defaultWorkScopes()->sync(collect($included)->mapWithKeys(fn ($id, $order) => [$id => ['is_default' => true, 'display_order' => $order + 1]])->all());
                }
                if ($newService) {
                    foreach (CommonRequiredDocument::query()->where('is_active', true)->get() as $order => $document) {
                        $type = in_array($document->code, $definition['any'], true) ? 'any_one_required' : 'optional';
                        $service->requiredDocuments()->updateOrCreate(['common_required_document_id' => $document->id], [
                            'name_en' => $document->name_en, 'name_gu' => $document->name_gu, 'requirement_type' => $type,
                            'is_mandatory' => false, 'is_active' => true, 'sort_order' => $order + 1,
                            'allowed_file_types' => $document->allowed_file_types, 'max_upload_size_kb' => $document->max_upload_size_kb,
                        ]);
                    }
                }
                $addOns = Service::query()->whereIn('slug', ['property-title-verification', 'sub-registrar-office-token-booking', 'legal-consulting'])->pluck('id');
                foreach ($addOns as $order => $addOnId) {
                    DB::table('service_add_ons')->insertOrIgnore(['service_id' => $service->id, 'add_on_service_id' => $addOnId, 'is_active' => true, 'sort_order' => $order + 1, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
    }
}
