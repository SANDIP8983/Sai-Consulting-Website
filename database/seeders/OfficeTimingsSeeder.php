<?php

namespace Database\Seeders;

use App\Models\OfficeTiming;
use Illuminate\Database\Seeder;

class OfficeTimingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(0, 6) as $dayOfWeek) {
            OfficeTiming::query()->firstOrCreate(
                ['day_of_week' => $dayOfWeek],
                ['opens_at' => null, 'closes_at' => null, 'is_closed' => true, 'notes' => null],
            );
        }
    }
}
