<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\WorkScopeItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceCommercialConfigurationSeeder extends Seeder
{
    /** @var array<string, array{fee: int, days: int, scopes: array<int, string>}> */
    private const SERVICES = [
        'sale-deed' => ['fee' => 1999, 'days' => 4, 'scopes' => ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'relinquishment-deed' => ['fee' => 1199, 'days' => 4, 'scopes' => ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'partition-deed' => ['fee' => 1399, 'days' => 5, 'scopes' => ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'rent-agreement' => ['fee' => 499, 'days' => 3, 'scopes' => ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'power-of-attorney' => ['fee' => 899, 'days' => 4, 'scopes' => ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'gift-deed' => ['fee' => 2199, 'days' => 4, 'scopes' => ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'mortgage' => ['fee' => 999, 'days' => 4, 'scopes' => ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'mortgage-release' => ['fee' => 999, 'days' => 4, 'scopes' => ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'banakhat-agreement-to-sell' => ['fee' => 499, 'days' => 3, 'scopes' => ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'property-title-verification' => ['fee' => 2499, 'days' => 7, 'scopes' => ['initial-review', 'property-record-check', 'title-verification', 'final-title-report']],
        'legal-consulting' => ['fee' => 1499, 'days' => 4, 'scopes' => ['initial-review', 'information-document-review', 'guidance']],
        'sub-registrar-office-token-booking' => ['fee' => 399, 'days' => 3, 'scopes' => ['initial-review', 'garvi-portal-work', 'garvi-token-booking']],
        'other' => ['fee' => 1499, 'days' => 5, 'scopes' => []],
    ];

    /** @return array<int, string> */
    public static function serviceSlugs(): array
    {
        return array_keys(self::SERVICES);
    }

    /** @var array<string, array{name_en: string, name_gu: string, aliases: array<int, string>}> */
    private const SCOPES = [
        'initial-review' => ['name_en' => 'Initial Document / Information Review', 'name_gu' => 'દસ્તાવેજો / માહિતીની પ્રાથમિક ચકાસણી', 'aliases' => ['document review']],
        'draft-preparation' => ['name_en' => 'Draft Preparation', 'name_gu' => 'દસ્તાવેજનું લખાણ / Draft Preparation', 'aliases' => ['drafting']],
        'draft-check-correction' => ['name_en' => 'Draft Check / Correction', 'name_gu' => 'Draftની ચકાસણી અને જરૂરી સુધારા', 'aliases' => []],
        'final-draft' => ['name_en' => 'Final Draft Preparation', 'name_gu' => 'Final Draft તૈયાર કરવો', 'aliases' => []],
        'property-record-check' => ['name_en' => 'Property / Revenue Record Check', 'name_gu' => 'મિલકત / Revenue Recordની ચકાસણી', 'aliases' => ['revenue entry follow-up']],
        'title-verification' => ['name_en' => 'Title Verification', 'name_gu' => 'Title Verification / ટાઇટલ ચકાસણી', 'aliases' => ['title verification']],
        'final-title-report' => ['name_en' => 'Final Title Report / Opinion', 'name_gu' => 'Final Title Report / Opinion તૈયાર કરવો', 'aliases' => []],
        'stamp-registration-fee-assistance' => ['name_en' => 'Stamp Duty / Registration Fee Calculation Assistance', 'name_gu' => 'Stamp Duty / Registration Fee Calculation Assistance', 'aliases' => ['stamp duty calculation']],
        'garvi-portal-work' => ['name_en' => 'GARVI Portal Work', 'name_gu' => 'GARVI Portal સંબંધિત કામગીરી', 'aliases' => ['sub-registrar office work']],
        'garvi-token-booking' => ['name_en' => 'GARVI Token Booking', 'name_gu' => 'GARVI Token Booking', 'aliases' => ['garvi portal token booking']],
        'registration-preparation' => ['name_en' => 'Registration Document / Details Preparation', 'name_gu' => 'Registration માટે દસ્તાવેજ/વિગતો તૈયાર કરવી', 'aliases' => ['registration assistance']],
        'other-work' => ['name_en' => 'Other Work', 'name_gu' => 'અન્ય કામગીરી', 'aliases' => ['other']],
        'information-document-review' => ['name_en' => 'Information / Document Review', 'name_gu' => 'માહિતી / દસ્તાવેજોની સમીક્ષા', 'aliases' => []],
        'guidance' => ['name_en' => 'Guidance', 'name_gu' => 'માર્ગદર્શન', 'aliases' => []],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $services = Service::query()->whereIn('slug', array_keys(self::SERVICES))->get()->keyBy('slug');
            $missing = array_diff(array_keys(self::SERVICES), $services->keys()->all());
            if ($missing !== []) {
                throw new RuntimeException('Missing required Service Master slugs: '.implode(', ', $missing));
            }

            $scopeItems = $this->configureWorkScopeMaster();

            foreach (self::SERVICES as $slug => $configuration) {
                /** @var Service $service */
                $service = $services->get($slug);
                $service->update([
                    'service_fee' => $configuration['fee'],
                    'gst_rate' => 18,
                    'estimated_days' => $configuration['days'],
                    'processing_time_label' => $configuration['days'].' Working Days',
                    'advance_percentage' => 100,
                    'government_charges' => 0,
                ]);

                $defaults = collect($configuration['scopes'])->mapWithKeys(
                    fn (string $code, int $order): array => [
                        $scopeItems[$code]->id => ['is_default' => true, 'display_order' => $order + 1],
                    ],
                )->all();
                $service->defaultWorkScopes()->sync($defaults);
            }
        });
    }

    /** @return array<string, WorkScopeItem> */
    private function configureWorkScopeMaster(): array
    {
        $configured = [];
        $displayOrder = 0;
        foreach (self::SCOPES as $code => $definition) {
            $item = WorkScopeItem::withTrashed()
                ->where('normalized_name', $code)
                ->orWhereIn('normalized_name', $definition['aliases'])
                ->first() ?? new WorkScopeItem;

            if ($item->trashed()) {
                $item->restore();
            }
            $item->fill([
                'normalized_name' => $code,
                'name_en' => $definition['name_en'],
                'name_gu' => $definition['name_gu'],
                'is_active' => true,
                'display_order' => ++$displayOrder,
            ])->save();
            $configured[$code] = $item;
        }

        WorkScopeItem::query()
            ->whereIn('normalized_name', ['registration fee calculation', 'certified copy', 'dispatch / delivery preparation'])
            ->update(['is_active' => false]);

        return $configured;
    }
}
