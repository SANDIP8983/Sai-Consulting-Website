<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            'Sale Deed',
            'હક્ક કમી લેખ',
            'વહેંચણી લેખ',
            'Property Title Verification',
            'Legal Consulting',
            'Other',
        ];

        foreach ($services as $service) {
            Service::create([
                'name' => $service,
            ]);
        }
    }
}