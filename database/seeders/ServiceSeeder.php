<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name_en' => 'Sale Deed Drafting', 'name_gu' => 'વેચાણ દસ્તાવેજ ડ્રાફ્ટિંગ', 'slug' => 'sale-deed-drafting'],
            ['name_en' => 'Release Deed', 'name_gu' => 'હક્ક કમી લેખ', 'slug' => 'release-deed'],
            ['name_en' => 'Partition Deed', 'name_gu' => 'વહેંચણી લેખ', 'slug' => 'partition-deed'],
            ['name_en' => 'Property Title Verification', 'name_gu' => 'મિલકત ટાઇટલ તપાસ', 'slug' => 'property-title-verification'],
            ['name_en' => 'Legal & Documentation Consulting', 'name_gu' => 'કાનૂની અને દસ્તાવેજ કન્સલ્ટિંગ', 'slug' => 'legal-documentation-consulting'],
        ];

        foreach ($services as $index => $service) {
            Service::query()->updateOrCreate(['slug' => $service['slug']], [
                ...$service,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
