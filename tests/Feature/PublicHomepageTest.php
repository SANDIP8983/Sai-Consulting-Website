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
        Service::query()->create(['name_en' => 'Sub Registrar Office Token Booking', 'name_gu' => 'સબ રજિસ્ટ્રાર ઓફિસ ટોકન બુકિંગ', 'slug' => 'sub-registrar-office-token-booking', 'is_active' => true]);
        Service::query()->create(['name_en' => 'Hidden Service', 'name_gu' => 'છુપાયેલી સેવા', 'slug' => 'hidden-service', 'is_active' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('વિશ્વાસપાત્ર દસ્તાવેજ ડ્રાફ્ટિંગ')
            ->assertSee('Sub Registrar Office Token Booking')
            ->assertSee('સબ રજિસ્ટ્રાર ઓફિસ ટોકન બુકિંગ')
            ->assertSee('office@sai.test')
            ->assertSee('WhatsApp')
            ->assertSee('9687621876')
            ->assertSee('https://wa.me/9687621876', false)
            ->assertSee('Working Hours: 9:00 AM - 6:00 PM')
            ->assertDontSee('Office Open')
            ->assertDontSee('Office Closed')
            ->assertDontSee('Today: Closed')
            ->assertSee('Independence Day')
            ->assertSee('Second and fourth Saturday closed')
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
            ->assertSee('Select service')
            ->assertSee('Receive completed service')
            ->assertSee('Which documents may be uploaded?')
            ->assertSee('stat-counter')
            ->assertSee('20+ Years Experience')
            ->assertSee('12000')
            ->assertSee('Documents Prepared')
            ->assertSee('11200')
            ->assertSee('Happy Clients')
            ->assertDontSee('hello@example.com')
            ->assertSee('does not practice as an advocate');
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
            ->assertSee('Documentation and')
            ->assertSee('Property Consulting')
            ->assertSee('Request Service')
            ->assertSee('Track Request')
            ->assertSee('Professional Services')
            ->assertSee('data-count="13"', false)
            ->assertSee('aria-labelledby="hero-title"', false)
            ->assertSee('aria-label="Service assurances"', false)
            ->assertSee(route('request.create'), false)
            ->assertSee(route('request.track'), false);
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
