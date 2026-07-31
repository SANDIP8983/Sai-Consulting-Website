<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class SubRegistrarTokenBookingServiceSeeder extends Seeder
{
    public function run(): void
    {
        Service::query()->updateOrCreate(
            ['slug' => 'sub-registrar-office-token-booking'],
            [
                'name_en' => 'Sub Registrar Office Token Booking',
                'name_gu' => 'સબ રજિસ્ટ્રાર ઓફિસ ટોકન બુકિંગ',
                'description' => 'Sub Registrar Office appointment token booking assistance.',
                'is_active' => true,
                'sort_order' => 6,
            ],
        );
    }
}
