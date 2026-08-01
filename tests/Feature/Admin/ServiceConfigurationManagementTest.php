<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\User;
use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceConfigurationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_complete_service_configuration_with_validation(): void
    {
        $service = $this->service();
        $payload = [
            'name_en' => 'Configured Service', 'name_gu' => 'રૂપરેખાંકિત સેવા',
            'description_gu' => 'ગુજરાતી વિગત', 'description_en' => 'English details',
            'customer_instructions' => 'Bring original records.', 'important_notes' => 'Appointments recommended.',
            'disclaimer' => 'Final timing depends on authority availability.', 'service_fee' => 2500,
            'estimated_days' => 3, 'processing_time_label' => '1-3 Days', 'sort_order' => 4,
            'is_active' => 1, 'available_online' => 1, 'available_offline' => 0,
            'requires_payment_before_processing' => 0, 'requires_dispatch' => 0,
            'requires_property_documents' => 1,
        ];

        $this->actingAs(User::factory()->create())->put(route('admin.services.update', $service), $payload)
            ->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseHas('services', [
            'id' => $service->id, 'description_en' => 'English details', 'description_gu' => 'ગુજરાતી વિગત',
            'customer_instructions' => 'Bring original records.', 'important_notes' => 'Appointments recommended.',
            'disclaimer' => 'Final timing depends on authority availability.', 'processing_time_label' => '1-3 Days',
            'service_fee' => 2500, 'estimated_days' => 3, 'available_online' => true,
            'available_offline' => false, 'requires_payment_before_processing' => false, 'requires_dispatch' => false,
        ]);
        $this->assertSame('English details', $service->fresh()->description);
        $this->assertSame('Bring original records.', $service->fresh()->notes);

        $this->put(route('admin.services.update', $service), [...$payload, 'processing_time_label' => str_repeat('x', 101), 'disclaimer' => str_repeat('x', 5001)])
            ->assertSessionHasErrors(['processing_time_label', 'disclaimer']);
    }

    public function test_public_service_uses_configuration_and_separates_documents(): void
    {
        $service = $this->service([
            'description_gu' => 'ગુજરાતી સેવા વર્ણન', 'description_en' => 'English service description',
            'customer_instructions' => 'Customer instructions here.', 'important_notes' => 'Important note here.',
            'disclaimer' => 'Optional disclaimer here.', 'service_fee' => 1250, 'estimated_days' => 3,
            'processing_time_label' => '1-3 Days', 'available_online' => false, 'available_offline' => true,
        ]);
        $service->requiredDocuments()->create(['name_en' => 'Mandatory Copy', 'name_gu' => 'જરૂરી નકલ', 'is_mandatory' => true, 'is_active' => true, 'sort_order' => 1]);
        $service->requiredDocuments()->create(['name_en' => 'Optional Copy', 'name_gu' => 'વૈકલ્પિક નકલ', 'is_mandatory' => false, 'is_active' => true, 'sort_order' => 2]);

        $this->get(route('services.index'))->assertOk()->assertSee($service->name_en);
        $this->get(route('services.show', $service->slug))->assertOk()
            ->assertSee('ગુજરાતી સેવા વર્ણન')->assertSee('English service description')
            ->assertSee('₹1,250.00')->assertSee('1-3 Days')->assertSee('3 estimated day(s)')
            ->assertSee('Required Documents')->assertSee('Mandatory Copy')
            ->assertSee('Optional Documents')->assertSee('Optional Copy')
            ->assertSee('Customer instructions here.')->assertSee('Important note here.')
            ->assertSee('Optional disclaimer here.')->assertSee('Offline service available.')
            ->assertDontSee(route('request.create', ['service' => $service->id]), false);
    }

    public function test_dashboard_shows_service_availability_and_status_widgets(): void
    {
        $this->service(['name_en' => 'Online Active', 'slug' => 'online-active', 'available_online' => true, 'available_offline' => false]);
        $this->service(['name_en' => 'Offline Active', 'slug' => 'offline-active', 'available_online' => false, 'available_offline' => true]);
        $this->service(['name_en' => 'Disabled', 'slug' => 'disabled', 'is_active' => false]);

        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Total Online Services')->assertSee('Total Offline Services')
            ->assertSee('Total Active Services')->assertSee('Disabled Services');
    }

    public function test_payment_is_not_a_dispatch_block_when_service_does_not_require_advance_payment(): void
    {
        $service = $this->service(['requires_payment_before_processing' => false, 'requires_dispatch' => true]);
        $request = $service->requests()->create([
            'reference_no' => 'SC/2026/700001', 'file_number' => 'SC/2026/F700001',
            'name' => 'Customer', 'mobile' => '9999999999', 'status' => 'ready_for_registration', 'payment_status' => 'not_required',
        ]);

        $this->assertContains('dispatched', app(RequestWorkflowService::class)->transitions($request));
    }

    private function service(array $attributes = []): Service
    {
        return Service::query()->create([
            'name_en' => 'Configurable Service '.fake()->unique()->numberBetween(1, 999999),
            'name_gu' => 'રૂપરેખાંકિત સેવા '.fake()->unique()->numberBetween(1, 999999),
            'slug' => 'configurable-service-'.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true, 'available_online' => true, 'available_offline' => true,
            'requires_payment_before_processing' => true, 'requires_dispatch' => true,
            ...$attributes,
        ]);
    }
}
