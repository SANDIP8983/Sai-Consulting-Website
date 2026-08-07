<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyDetailsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_and_offline_forms_group_location_under_property_details(): void
    {
        $service = $this->service(true);
        $this->get(route('request.create'))->assertOk()->assertDontSee('name="village"', false)->assertDontSee('name="taluka"', false)->assertDontSee('name="district"', false)->assertSee('Property Details')->assertSee('name="property_village"', false)->assertSee('name="tp_number"', false)->assertSee('name="final_plot_number"', false)->assertSee('#property-details-section{display:block!important}', false)->assertSee("value('survey_numbers')", false)->assertSee("value('khata_number')", false);
        $this->actingAs(User::factory()->create())->get(route('admin.requests.create'))->assertOk()->assertSee('name="property_village"', false)->assertSee('name="property_taluka"', false)->assertSee('name="property_district"', false)->assertSee('Property Address / Remarks');
    }

    public function test_property_fields_are_required_for_property_services_online_and_offline(): void
    {
        $service = $this->service(true);
        $payload = $this->payload($service);
        $this->post(route('request.store'), $payload)->assertSessionHasErrors(['property_village', 'property_taluka', 'property_district']);
        $this->actingAs(User::factory()->create())->post(route('admin.requests.store'), $payload)->assertSessionHasErrors(['property_village', 'property_taluka', 'property_district']);
    }

    public function test_non_property_services_accept_both_online_and_offline_requests_without_property_location(): void
    {
        $online = $this->service(false);
        $payload = $this->payload($online) + ['declaration' => '1'];
        $this->post(route('request.store'), $payload)->assertRedirect(route('request.success'));
        $offline = $this->service(false);
        $payload = $this->payload($offline);
        $this->actingAs(User::factory()->create())->post(route('admin.requests.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('requests', 2);
    }

    public function test_property_values_are_persisted_and_admin_detail_displays_them(): void
    {
        $service = $this->service(true);
        $payload = $this->payload($service) + ['property_village' => 'Santej', 'property_taluka' => 'Kalol', 'property_district' => 'Gandhinagar', 'survey_numbers' => '12/1, Block 15', 'khata_number' => 'KH-100', 'property_address_remarks' => 'Near village lake', 'tp_number' => 'TP-4', 'final_plot_number' => 'FP-20', 'revenue_village' => 'Santej', 'declaration' => '1'];
        $this->post(route('request.store'), $payload)->assertRedirect(route('request.success'));
        $request = CustomerRequest::query()->sole();
        $this->assertSame('Santej', $request->property_village);
        $this->assertNull($request->village);
        $this->actingAs(User::factory()->create())->get(route('admin.requests.show', $request))->assertOk()->assertSee('Santej, Kalol, Gandhinagar')->assertSee('12/1, Block 15')->assertSee('KH-100')->assertSee('Near village lake')->assertSee('TP-4')->assertSee('FP-20');
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertSee('Property Details')->assertSee('Santej, Kalol, Gandhinagar')->assertSee('12/1, Block 15')->assertSee('KH-100');
    }

    private function service(bool $property): Service
    {
        return Service::query()->create(['name_en' => ($property ? 'Property Service ' : 'General Service ').fake()->unique()->numberBetween(100000, 999999), 'name_gu' => 'Service '.fake()->unique()->numberBetween(100000, 999999), 'slug' => ($property ? 'property' : 'general').'-'.fake()->unique()->numberBetween(1, 99999), 'service_fee' => 500, 'gst_rate' => 18, 'estimated_days' => 3, 'is_active' => true, 'available_online' => true, 'available_offline' => true, 'requires_property_documents' => $property]);
    }

    private function payload(Service $service): array
    {
        return ['service_id' => $service->id, 'service_ids' => [$service->id], 'name' => 'Test Customer', 'mobile' => '9999999999', 'address' => 'Customer home address', 'details' => 'Request details'];
    }
}
