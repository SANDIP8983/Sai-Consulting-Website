<?php

namespace Database\Seeders;

use App\Models\GovernmentChargeType;
use Illuminate\Database\Seeder;

class GovernmentChargeTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Stamp Duty', 'Registration Fee', 'Application Fee', 'Measurement / Mapani Fee', 'Mutation / Revenue Fee', 'Token / Portal Fee', 'Other'] as $order => $name) {
            GovernmentChargeType::query()->firstOrCreate(['name_en' => $name], ['default_amount' => 0, 'is_active' => true, 'sort_order' => $order + 1]);
        }
    }
}
