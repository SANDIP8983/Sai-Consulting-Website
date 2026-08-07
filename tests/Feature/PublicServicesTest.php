<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Setting;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_active_services_and_hides_inactive_services(): void
    {
        $active = $this->service(['name_en' => 'Active Drafting', 'name_gu' => 'સક્રિય ડ્રાફ્ટિંગ', 'slug' => 'active-drafting']);
        $inactive = $this->service(['name_en' => 'Hidden Drafting', 'name_gu' => 'છુપાયેલી ડ્રાફ્ટિંગ', 'slug' => 'hidden-drafting', 'is_active' => false]);

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee($active->name_gu)
            ->assertSee($active->name_en)
            ->assertDontSee($inactive->name_en);
    }

    public function test_active_service_detail_loads_by_slug_with_documents_and_actions(): void
    {
        $service = $this->service([
            'name_en' => 'Property Verification',
            'name_gu' => 'મિલકત ચકાસણી',
            'slug' => 'property-verification',
            'service_fee' => 1250,
            'advance_percentage' => 50,
            'estimated_days' => 7,
            'notes' => 'Original records may be requested after review.',
        ]);
        $service->requiredDocuments()->create(['name_en' => 'Property Card', 'name_gu' => 'પ્રોપર્ટી કાર્ડ', 'sort_order' => 1]);
        $this->publicSetting('contact.whatsapp_number', '9687621876');

        $response = $this->get(route('services.show', $service->slug))
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('Services')
            ->assertSee($service->name_gu)
            ->assertSee($service->name_en)
            ->assertSee('સેવા વિશે')
            ->assertSee(config('public-service-pages.fallback_description'))
            ->assertSee('Property Card')
            ->assertSee('પ્રોપર્ટી કાર્ડ')
            ->assertSee(route('request.create', ['service' => $service->id]), false)
            ->assertSee(route('request.track'), false)
            ->assertSee(route('services.index'), false)
            ->assertSee('https://wa.me/9687621876', false)
            ->assertSee('ઓનલાઇન અરજી કરો')
            ->assertSee('અરજી ટ્રેક કરો')
            ->assertDontSee('₹1,250.00')
            ->assertDontSee('Professional Fee')
            ->assertDontSee('Advance')
            ->assertDontSee('Estimated Completion')
            ->assertDontSee('Original records may be requested')
            ->assertDontSee('How It Works')
            ->assertDontSee('Service Availability')
            ->assertDontSee('Related Services')
            ->assertDontSee('tel:');

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_invalid_or_inactive_service_slug_returns_not_found(): void
    {
        $inactive = $this->service(['slug' => 'inactive-service', 'is_active' => false]);

        $this->get(route('services.show', 'missing-service'))->assertNotFound();
        $this->get(route('services.show', $inactive->slug))->assertNotFound();
    }

    public function test_missing_fee_and_timeline_fields_are_hidden(): void
    {
        $service = $this->service(['slug' => 'no-commercial-details', 'service_fee' => null, 'estimated_days' => null]);

        $this->get(route('services.show', $service->slug))
            ->assertOk()
            ->assertDontSee('Service Fee')
            ->assertDontSee('Estimated Completion')
            ->assertDontSee('Advance');
    }

    public function test_all_thirteen_stable_slugs_use_the_shared_gujarati_detail_presentation(): void
    {
        $this->seed(ServiceSeeder::class);

        $services = Service::query()->where('is_active', true)->orderBy('sort_order')->get();
        $this->assertCount(13, $services);

        foreach ($services as $service) {
            $response = $this->get(route('services.show', $service->slug))
                ->assertOk()
                ->assertSee($service->name_gu)
                ->assertSee($service->name_en)
                ->assertSee(config("public-service-pages.descriptions.{$service->slug}"))
                ->assertSee('સેવા વિશે')
                ->assertSee('જરૂરી દસ્તાવેજો')
                ->assertDontSee('Professional Fee')
                ->assertDontSee('How It Works')
                ->assertDontSee('Related Services');

            $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        }
    }

    public function test_service_detail_uses_gujarati_fallbacks_without_documents_or_configured_slug_copy(): void
    {
        $service = $this->service([
            'name_en' => 'Future Documentation Service',
            'name_gu' => 'નવી દસ્તાવેજ સેવા',
            'slug' => 'future-documentation-service',
        ]);

        $this->get(route('services.show', $service->slug))
            ->assertOk()
            ->assertSee(config('public-service-pages.fallback_description'))
            ->assertSee('આ સેવા માટે જરૂરી દસ્તાવેજોની યાદી હાલમાં ઉપલબ્ધ નથી')
            ->assertDontSee('WhatsApp')
            ->assertDontSee('tel:');
    }

    public function test_service_search_matches_bilingual_names_and_validates_length(): void
    {
        $service = $this->service(['name_en' => 'Token Booking', 'name_gu' => 'ગરવી ટોકન બુકિંગ', 'slug' => 'token-booking']);
        $this->service(['name_en' => 'Sale Deed', 'name_gu' => 'વેચાણ દસ્તાવેજ', 'slug' => 'sale-deed']);

        $this->get(route('services.index', ['q' => 'ગરવી']))
            ->assertOk()
            ->assertSee($service->name_en)
            ->assertDontSee('Sale Deed');
        $this->get(route('services.index', ['q' => str_repeat('a', 101)]))
            ->assertSessionHasErrors('q');
    }

    public function test_service_seeder_restores_all_approved_services_idempotently(): void
    {
        $existing = $this->service([
            'name_en' => 'Sale Deed Drafting',
            'name_gu' => 'જૂનું વેચાણ નામ',
            'slug' => 'sale-deed-drafting',
            'service_fee' => 2500,
            'estimated_days' => 5,
            'notes' => 'Preserve this note.',
            'is_active' => false,
        ]);
        $document = $existing->requiredDocuments()->create([
            'name_en' => 'Property Card',
            'name_gu' => 'પ્રોપર્ટી કાર્ડ',
            'sort_order' => 1,
        ]);

        $this->seed(ServiceSeeder::class);
        $this->seed(ServiceSeeder::class);

        $existing->refresh();
        $this->assertSame('Sale Deed', $existing->name_en);
        $this->assertSame('વેચાણ દસ્તાવેજ', $existing->name_gu);
        $this->assertSame('sale-deed', $existing->slug);
        $this->assertTrue($existing->is_active);
        $this->assertSame('2500.00', $existing->service_fee);
        $this->assertSame(5, $existing->estimated_days);
        $this->assertSame('Preserve this note.', $existing->notes);
        $this->assertDatabaseHas('service_required_documents', ['id' => $document->id, 'service_id' => $existing->id]);
        $this->assertDatabaseCount('services', 13);
        $this->assertSame(13, Service::query()->where('is_active', true)->count());
        $this->assertSame(13, Service::query()->distinct('slug')->count('slug'));

        $approvedNames = Service::query()->orderBy('sort_order')->pluck('name_en');
        $servicesPage = $this->get(route('services.index'))->assertOk();
        $requestPage = $this->get(route('request.create'))->assertOk();
        foreach ($approvedNames as $name) {
            $servicesPage->assertSee($name);
            $requestPage->assertSee($name);
        }
    }

    private function service(array $attributes = []): Service
    {
        return Service::query()->create([
            'name_en' => 'Documentation Service',
            'name_gu' => 'દસ્તાવેજ સેવા',
            'slug' => 'documentation-service-'.fake()->unique()->numberBetween(1, 999999),
            'description' => 'Professional documentation assistance.',
            'is_active' => true,
            'sort_order' => 1,
            ...$attributes,
        ]);
    }

    private function publicSetting(string $key, string $value): void
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
