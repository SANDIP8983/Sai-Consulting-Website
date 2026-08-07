<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\OfficeTiming;
use App\Models\Service;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class HomepageService
{
    private const FEATURED_SERVICE_SLUGS = [
        'sale-deed',
        'relinquishment-deed',
        'partition-deed',
        'banakhat-agreement-to-sell',
        'property-title-verification',
        'power-of-attorney',
    ];

    /** @return array<string, mixed> */
    public function data(): array
    {
        $activeServicesCount = Service::query()
            ->where('is_active', true)
            ->where('available_online', true)
            ->count();

        $services = Service::query()
            ->where('is_active', true)
            ->where('available_online', true)
            ->whereIn('slug', self::FEATURED_SERVICE_SLUGS)
            ->withCount(['activeRequiredDocuments as required_documents_count'])
            ->get()
            ->sortBy(fn (Service $service): int => array_search($service->slug, self::FEATURED_SERVICE_SLUGS, true))
            ->values();

        return [
            ...$this->publicSiteData(),
            'services' => $services,
            'statistics' => [
                ['value' => config('homepage.statistics.documents_prepared'), 'suffix' => '+', 'label_gu' => 'તૈયાર કરેલ દસ્તાવેજો'],
                ['value' => config('homepage.statistics.happy_clients'), 'suffix' => '+', 'label_gu' => 'સંતુષ્ટ ગ્રાહકો'],
                ['value' => 20, 'suffix' => '+ વર્ષ', 'label_gu' => 'અનુભવ'],
                ['value' => $activeServicesCount, 'suffix' => '+', 'label_gu' => 'સેવાઓ'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function publicSiteData(): array
    {
        $settings = Setting::query()
            ->where('is_public', true)
            ->pluck('setting_value', 'setting_key');
        $branding = Setting::query()->whereIn('setting_key', ['branding.primary_logo_path', 'branding.dark_logo_path', 'branding.favicon_path'])->pluck('setting_value', 'setting_key');
        $timezone = Setting::query()->where('setting_key', 'office.timezone')->value('setting_value') ?: config('app.timezone');
        $now = CarbonImmutable::now($timezone);
        $timings = OfficeTiming::query()->orderBy('day_of_week')->get();
        $holidays = Holiday::query()->where('is_closed', true)->orderBy('holiday_date')->get();

        return [
            'businessName' => $settings->get('website.name') ?: 'Sai Consulting',
            'tagline' => $settings->get('business.tagline') ?: 'Documentation & Consulting',
            'email' => $settings->get('contact.email') ?: null,
            'whatsappUrl' => $this->whatsappUrl($settings->get('contact.whatsapp_number')),
            'whatsappNumber' => $this->whatsappNumber($settings->get('contact.whatsapp_number')),
            'address' => $this->address($settings),
            'timings' => $timings,
            'workingHoursLabel' => $this->workingHoursLabel($timings),
            'holidayNotice' => $this->holidayNotice($now, $holidays),
            'primaryLogoUrl' => $branding->get('branding.primary_logo_path') ? route('branding.asset', 'primary-logo') : null,
            'darkLogoUrl' => $branding->get('branding.dark_logo_path') ? route('branding.asset', 'dark-logo') : null,
            'faviconUrl' => $branding->get('branding.favicon_path') ? route('branding.asset', 'favicon') : null,
        ];
    }

    /** @param Collection<string, string|null> $settings */
    private function address(Collection $settings): string
    {
        return collect([
            $settings->get('office.address_line_1'),
            $settings->get('office.address_line_2'),
            $settings->get('office.city'),
            $settings->get('office.state'),
            $settings->get('office.postal_code'),
        ])->filter()->implode(', ');
    }

    private function whatsappUrl(?string $number): ?string
    {
        $digits = $this->whatsappNumber($number);

        return $digits ? "https://wa.me/{$digits}" : null;
    }

    private function whatsappNumber(?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number);

        return $digits ?: null;
    }

    /** @param Collection<int, OfficeTiming> $timings */
    private function workingHoursLabel(Collection $timings): ?string
    {
        $timing = $timings
            ->filter(fn (OfficeTiming $timing) => ! $timing->is_closed && $timing->opens_at && $timing->closes_at)
            ->groupBy(fn (OfficeTiming $timing) => "{$timing->opens_at}|{$timing->closes_at}")
            ->sortByDesc(fn (Collection $group) => $group->count())
            ->first()?->first();

        if (! $timing) {
            return null;
        }

        $opensAt = CarbonImmutable::parse($timing->opens_at)->format('g:i A');
        $closesAt = CarbonImmutable::parse($timing->closes_at)->format('g:i A');

        return "Working Hours: {$opensAt} - {$closesAt}";
    }

    /** @param Collection<int, Holiday> $holidays */
    private function holidayNotice(CarbonImmutable $now, Collection $holidays): ?array
    {
        return $holidays->map(function (Holiday $holiday) use ($now): array {
            $date = CarbonImmutable::parse($holiday->holiday_date, $now->timezone);
            if ($holiday->is_recurring) {
                $date = $date->setYear($now->year);
                if ($date->isBefore($now->startOfDay())) {
                    $date = $date->addYear();
                }
            }

            return ['holiday' => $holiday, 'date' => $date];
        })->filter(fn (array $notice) => ! $notice['date']->isBefore($now->startOfDay()))
            ->sortBy('date')
            ->map(fn (array $notice) => [
                'title' => $notice['holiday']->title,
                'date' => $notice['date']->format('d M Y'),
                'description' => $notice['holiday']->description,
            ])->first();
    }
}
