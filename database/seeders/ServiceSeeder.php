<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name_en' => 'Sale Deed', 'name_gu' => 'વેચાણ દસ્તાવેજ', 'slug' => 'sale-deed', 'aliases' => ['sale-deed-drafting'], 'names' => ['Sale Deed Drafting']],
            ['name_en' => 'Relinquishment Deed', 'name_gu' => 'હક્ક કમી લેખ', 'slug' => 'relinquishment-deed', 'aliases' => ['release-deed'], 'names' => ['Release Deed']],
            ['name_en' => 'Partition Deed', 'name_gu' => 'વહેંચણી લેખ', 'slug' => 'partition-deed'],
            ['name_en' => 'Rent Agreement', 'name_gu' => 'ભાડા કરાર', 'slug' => 'rent-agreement'],
            ['name_en' => 'Power of Attorney', 'name_gu' => 'પાવર ઓફ એટર્ની', 'slug' => 'power-of-attorney'],
            ['name_en' => 'Property Title Verification', 'name_gu' => 'મિલકતનું ટાઇટલ ચેકિંગ', 'slug' => 'property-title-verification'],
            ['name_en' => 'Gift Deed', 'name_gu' => 'બક્ષિસનો દસ્તાવેજ', 'slug' => 'gift-deed'],
            ['name_en' => 'Mortgage', 'name_gu' => 'ગીરોખત', 'slug' => 'mortgage'],
            ['name_en' => 'Mortgage Release', 'name_gu' => 'ગીરો મુક્ત', 'slug' => 'mortgage-release'],
            ['name_en' => 'Banakhat (Agreement to Sell)', 'name_gu' => 'બાનાખત', 'slug' => 'banakhat-agreement-to-sell'],
            ['name_en' => 'Sub Registrar Office Token Booking', 'name_gu' => 'સબ રજિસ્ટ્રાર કચેરી માટે ગરવી પોર્ટલ ટોકન બુકિંગ', 'slug' => 'sub-registrar-office-token-booking'],
            ['name_en' => 'Legal Consulting', 'name_gu' => 'લીગલ કન્સલ્ટિંગ', 'slug' => 'legal-consulting', 'aliases' => ['legal-documentation-consulting'], 'names' => ['Legal & Documentation Consulting']],
            ['name_en' => 'Other', 'name_gu' => 'અન્ય', 'slug' => 'other'],
        ];

        foreach ($services as $index => $definition) {
            $existing = Service::query()
                ->where('slug', $definition['slug'])
                ->orWhereIn('slug', $definition['aliases'] ?? [])
                ->orWhere('name_en', $definition['name_en'])
                ->orWhereIn('name_en', $definition['names'] ?? [])
                ->first();

            Service::query()->updateOrCreate(
                $existing ? ['id' => $existing->id] : ['slug' => $definition['slug']],
                [
                    'name_en' => $definition['name_en'],
                    'name_gu' => $definition['name_gu'],
                    'slug' => $definition['slug'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
