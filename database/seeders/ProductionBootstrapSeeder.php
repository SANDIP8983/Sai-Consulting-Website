<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ServiceSeeder::class,
            SubRegistrarTokenBookingServiceSeeder::class,
            ServiceCommercialConfigurationSeeder::class,
            CentralRequiredDocumentsSeeder::class,
            ProductionSettingsSeeder::class,
            OfficeTimingsSeeder::class,
            NotificationSettingsSeeder::class,
        ]);
    }
}
