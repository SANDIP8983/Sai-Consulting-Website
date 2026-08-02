<?php

namespace App\Services\Pdf;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class PdfCompanyContext
{
    public function get(): array
    {
        $settings = Setting::query()->whereIn('setting_key', [
            'website.name', 'office.name', 'office.address_line_1', 'office.address_line_2',
            'office.city', 'office.state', 'office.postal_code', 'office.timezone',
            'contact.email', 'contact.phone', 'contact.whatsapp_number',
            'business.tagline', 'business.website_url', 'business.gstin',
            'branding.pdf_logo_path', 'branding.stamp_path', 'branding.signature_path',
        ])->pluck('setting_value', 'setting_key');

        $configuredLogo = $this->privateImage($settings->get('branding.pdf_logo_path'));
        $logoPath = config('pdf.logo_path');
        $logo = $configuredLogo ?: (is_string($logoPath) && is_file($logoPath)
            ? 'data:'.(mime_content_type($logoPath) ?: 'image/png').';base64,'.base64_encode((string) file_get_contents($logoPath))
            : null);

        return [
            'name' => $settings->get('office.name') ?: $settings->get('website.name') ?: config('pdf.company_fallback'),
            'tagline' => $settings->get('business.tagline') ?: config('pdf.tagline'),
            'address' => collect(['office.address_line_1', 'office.address_line_2', 'office.city', 'office.state', 'office.postal_code'])->map(fn ($key) => $settings->get($key))->filter()->implode(', '),
            'email' => $settings->get('contact.email'),
            'phone' => $settings->get('contact.phone'),
            'whatsapp' => $settings->get('contact.whatsapp_number'),
            'timezone' => $settings->get('office.timezone') ?: config('pdf.timezone'),
            'website' => $settings->get('business.website_url'),
            'gst_number' => $settings->get('business.gstin'),
            'logo' => $logo,
            'stamp' => $this->privateImage($settings->get('branding.stamp_path')),
            'signature' => $this->privateImage($settings->get('branding.signature_path')),
        ];
    }

    private function privateImage(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $absolute = Storage::disk('local')->path($path);

        return 'data:'.(mime_content_type($absolute) ?: 'image/png').';base64,'.base64_encode((string) file_get_contents($absolute));
    }
}
