<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\OfficeTiming;
use App\Models\Service;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHomepageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_homepage_loads_dynamic_public_data_without_exposing_phone_number(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 10:30:00');
        $this->setting('website.name', 'Sai Consulting');
        $this->setting('contact.email', 'office@sai.test');
        $this->setting('contact.whatsapp_number', '9687621876');
        $this->setting('contact.phone', '07912345678');
        $this->setting('office.city', 'Chanasma');
        OfficeTiming::query()->create(['day_of_week' => 5, 'opens_at' => '09:00', 'closes_at' => '18:00', 'is_closed' => false]);
        Holiday::query()->create(['holiday_date' => '2026-08-15', 'title' => 'Independence Day', 'is_closed' => true]);
        Service::query()->create(['name_en' => 'Sale Deed', 'name_gu' => 'વેચાણ દસ્તાવેજ', 'slug' => 'sale-deed', 'is_active' => true, 'available_online' => true]);
        Service::query()->create(['name_en' => 'Hidden Service', 'name_gu' => 'છુપાયેલી સેવા', 'slug' => 'hidden-service', 'is_active' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('મિલકત સંબંધિત દસ્તાવેજો')
            ->assertSee('Sale Deed')
            ->assertSee('વેચાણ દસ્તાવેજ')
            ->assertSee('office@sai.test')
            ->assertSee('WhatsApp')
            ->assertSee('9687621876')
            ->assertSee('https://wa.me/9687621876', false)
            ->assertSee('કાર્ય સમય: 9:00 AM - 6:00 PM')
            ->assertDontSee('Office Open')
            ->assertDontSee('Office Closed')
            ->assertDontSee('Today: Closed')
            ->assertSee('Independence Day')
            ->assertSee('બીજો અને ચોથો શનિવાર તથા રવિવાર બંધ')
            ->assertDontSee('Hidden Service')
            ->assertDontSee('07912345678')
            ->assertDontSee('tel:');
    }

    public function test_homepage_contains_existing_request_workflow_links_and_tracking_form(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('request.create'), false)
            ->assertSee(route('request.track'), false)
            ->assertSee(route('request.track.lookup'), false)
            ->assertSee('name="reference_no"', false)
            ->assertSee('name="mobile"', false)
            ->assertSee('સેવા પસંદ કરો')
            ->assertSee('સેવા પૂર્ણ થયા પછી માહિતી મેળવો')
            ->assertSee('કયા દસ્તાવેજો અપલોડ કરી શકાય?')
            ->assertSee('stat-counter')
            ->assertSee('20+ વર્ષ')
            ->assertSee('12000')
            ->assertSee('તૈયાર કરેલ દસ્તાવેજો')
            ->assertSee('11200')
            ->assertSee('સંતુષ્ટ ગ્રાહકો')
            ->assertDontSee('hello@example.com')
            ->assertSee('does not practice as an advocate');
    }

    public function test_homepage_shows_clean_gujarati_office_fallback_and_hides_empty_footer_contact_column(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('ઓફિસનો કાર્ય સમય હાલમાં ઉપલબ્ધ નથી')
            ->assertSee('અઠવાડિયાનો કાર્ય સમય ટૂંક સમયમાં અહીં ઉપલબ્ધ થશે')
            ->assertDontSee('Contact / Office Information')
            ->assertDontSee('Working hours will be updated shortly')
            ->assertDontSee('tel:');
    }

    public function test_homepage_renders_redesigned_customer_journey_and_accessible_branding(): void
    {
        foreach (range(1, 13) as $index) {
            Service::query()->create([
                'name_en' => "Professional Service {$index}",
                'name_gu' => "Service {$index}",
                'slug' => "professional-service-{$index}",
                'is_active' => true,
                'available_online' => true,
            ]);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Trusted Documentation Partner')
            ->assertSee('તમારો વિશ્વસનીય')
            ->assertSee('દસ્તાવેજીકરણ સાથી')
            ->assertSee('ઓનલાઇન અરજી કરો')
            ->assertSee('અરજી ટ્રેક કરો')
            ->assertSee('સેવાઓ')
            ->assertSee('data-count="13"', false)
            ->assertSee('aria-labelledby="hero-title"', false)
            ->assertDontSee('aria-label="Service assurances"', false)
            ->assertSee(route('request.create'), false)
            ->assertSee(route('request.track'), false);
    }

    public function test_homepage_uses_six_featured_slugs_and_hides_service_fees_only_there(): void
    {
        $featured = [
            ['Sale Deed', 'વેચાણ દસ્તાવેજ', 'sale-deed'],
            ['Relinquishment Deed', 'હક્ક કમી લેખ', 'relinquishment-deed'],
            ['Partition Deed', 'વહેંચણી લેખ', 'partition-deed'],
            ['Banakhat (Agreement to Sell)', 'બાનાખત', 'banakhat-agreement-to-sell'],
            ['Property Title Verification', 'મિલકતનું ટાઇટલ ચેકિંગ', 'property-title-verification'],
            ['Power of Attorney', 'પાવર ઓફ એટર્ની', 'power-of-attorney'],
        ];

        foreach ($featured as [$nameEn, $nameGu, $slug]) {
            Service::query()->create([
                'name_en' => $nameEn,
                'name_gu' => $nameGu,
                'slug' => $slug,
                'is_active' => true,
                'available_online' => true,
                'service_fee' => 3500,
            ]);
        }

        Service::query()->create([
            'name_en' => 'Other Active Service',
            'name_gu' => 'અન્ય સક્રિય સેવા',
            'slug' => 'other-active-service',
            'is_active' => true,
            'available_online' => true,
            'service_fee' => 9999,
        ]);

        $homepage = $this->get(route('home'))->assertOk();
        foreach ($featured as [$nameEn]) {
            $homepage->assertSee($nameEn);
        }

        $homepage
            ->assertDontSee('Other Active Service')
            ->assertDontSee('3,500.00')
            ->assertDontSee('9,999.00')
            ->assertSee('વિગતો જુઓ')
            ->assertSee('ઓનલાઇન અરજી કરો');

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('Other Active Service')
            ->assertSee('9,999.00');
    }

    private function setting(string $key, string $value): void
    {
        Setting::query()->create([
            'setting_key' => $key,
            'setting_value' => $value,
            'value_type' => 'string',
            'setting_group' => str($key)->before('.')->toString(),
            'is_public' => true,
        ]);
    }
}
