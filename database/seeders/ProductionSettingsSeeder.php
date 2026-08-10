<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class ProductionSettingsSeeder extends Seeder
{
    /** @var array<string, array{value: ?string, group: string, public: bool}> */
    private const SETTINGS = [
        'website.name' => ['value' => 'Sai Consulting', 'group' => 'website', 'public' => true],
        'website.status' => ['value' => 'maintenance', 'group' => 'website', 'public' => true],
        'website.maintenance_message' => ['value' => null, 'group' => 'website', 'public' => true],
        'business.tagline' => ['value' => null, 'group' => 'company_branding', 'public' => true],
        'business.website_url' => ['value' => null, 'group' => 'company_branding', 'public' => true],
        'business.gstin' => ['value' => null, 'group' => 'company_branding', 'public' => false],
        'office.name' => ['value' => 'Sai Consulting', 'group' => 'office', 'public' => true],
        'office.address_line_1' => ['value' => null, 'group' => 'office', 'public' => true],
        'office.address_line_2' => ['value' => null, 'group' => 'office', 'public' => true],
        'office.city' => ['value' => null, 'group' => 'office', 'public' => true],
        'office.state' => ['value' => null, 'group' => 'office', 'public' => true],
        'office.postal_code' => ['value' => null, 'group' => 'office', 'public' => true],
        'office.timezone' => ['value' => 'Asia/Kolkata', 'group' => 'office', 'public' => false],
        'contact.phone' => ['value' => null, 'group' => 'contact', 'public' => true],
        'contact.whatsapp_number' => ['value' => null, 'group' => 'contact', 'public' => true],
        'contact.email' => ['value' => null, 'group' => 'contact', 'public' => true],
        'branding.primary_logo_path' => ['value' => null, 'group' => 'company_branding', 'public' => false],
        'branding.dark_logo_path' => ['value' => null, 'group' => 'company_branding', 'public' => false],
        'branding.favicon_path' => ['value' => null, 'group' => 'company_branding', 'public' => false],
        'branding.pdf_logo_path' => ['value' => null, 'group' => 'company_branding', 'public' => false],
        'branding.stamp_path' => ['value' => null, 'group' => 'company_branding', 'public' => false],
        'branding.signature_path' => ['value' => null, 'group' => 'company_branding', 'public' => false],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $key => $definition) {
            Setting::query()->firstOrCreate(
                ['setting_key' => $key],
                [
                    'setting_value' => $definition['value'],
                    'value_type' => 'string',
                    'setting_group' => $definition['group'],
                    'is_public' => $definition['public'],
                ],
            );
        }
    }
}
