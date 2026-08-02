<?php

namespace App\Services\Pdf;

use App\Models\Setting;

class PdfCompanyContext
{
    public function get(): array
    {
        $settings = Setting::query()->whereIn('setting_key', [
            'website.name', 'office.name', 'office.address_line_1', 'office.address_line_2',
            'office.city', 'office.state', 'office.postal_code', 'office.timezone',
            'contact.email', 'contact.phone', 'contact.whatsapp_number',
        ])->pluck('setting_value', 'setting_key');

        $logoPath = config('pdf.logo_path');
        $logo = is_string($logoPath) && is_file($logoPath)
            ? 'data:'.(mime_content_type($logoPath) ?: 'image/png').';base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return [
            'name' => $settings->get('office.name') ?: $settings->get('website.name') ?: config('pdf.company_fallback'),
            'tagline' => config('pdf.tagline'),
            'address' => collect(['office.address_line_1', 'office.address_line_2', 'office.city', 'office.state', 'office.postal_code'])->map(fn ($key) => $settings->get($key))->filter()->implode(', '),
            'email' => $settings->get('contact.email'),
            'phone' => $settings->get('contact.phone'),
            'whatsapp' => $settings->get('contact.whatsapp_number'),
            'timezone' => $settings->get('office.timezone') ?: config('pdf.timezone'),
            'gst_number' => null,
            'logo' => $logo,
        ];
    }
}
