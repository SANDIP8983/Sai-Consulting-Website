<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\GovernmentChargeType;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkScopeItem;
use Database\Seeders\CentralRequiredDocumentsSeeder;
use Database\Seeders\GovernmentChargeTypeSeeder;
use Database\Seeders\SaleDeedServiceConfigurationSeeder;
use Database\Seeders\ServiceCommercialConfigurationSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurableServiceBillingArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_deed_replacement_is_configuration_driven_and_historical_service_remains(): void
    {
        $this->seed([ServiceSeeder::class, ServiceCommercialConfigurationSeeder::class, CentralRequiredDocumentsSeeder::class, SaleDeedServiceConfigurationSeeder::class, GovernmentChargeTypeSeeder::class]);

        $old = Service::query()->where('slug', 'sale-deed')->sole();
        $agricultural = Service::query()->where('slug', 'agricultural-land-sale-deed')->sole();
        $nonAgricultural = Service::query()->where('slug', 'non-agricultural-property-sale-deed')->sole();

        $this->assertFalse($old->is_active);
        $this->assertFalse($old->available_online);
        $this->assertTrue($agricultural->is_active);
        $this->assertTrue($nonAgricultural->is_active);
        $this->assertSame(['7-12-extract', '8-a-extract'], $agricultural->activeRequiredDocuments()->where('requirement_type', 'any_one_required')->with('commonDocument')->get()->pluck('commonDocument.code')->sort()->values()->all());
        $this->assertSame(['assessment-register-village-form-2', 'property-card'], $nonAgricultural->activeRequiredDocuments()->where('requirement_type', 'any_one_required')->with('commonDocument')->get()->pluck('commonDocument.code')->sort()->values()->all());

        $historical = CustomerRequest::query()->create(['reference_no' => 'SC/2026/900001', 'service_id' => $old->id, 'name' => 'Historical', 'mobile' => '9999999999', 'status' => 'closed']);
        $this->assertSame('Sale Deed', $historical->service->name_en);
        $this->get(route('request.create'))->assertOk()->assertSee('Agricultural Land Sale Deed')->assertSee('Non-Agricultural Property Sale Deed')->assertDontSee('>Sale Deed<', false);
    }

    public function test_admin_can_configure_future_service_scope_documents_and_applicable_add_ons(): void
    {
        $admin = User::factory()->create();
        $scope = WorkScopeItem::query()->create(['name_en' => 'Future Work', 'name_gu' => 'Future Work', 'normalized_name' => 'future-work', 'is_active' => true]);
        $addOn = Service::query()->create(['name_en' => 'Future Add-on', 'name_gu' => 'Future Add-on', 'slug' => 'future-add-on', 'service_fee' => 500, 'gst_rate' => 18, 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.services.store'), [
            'name_en' => 'Future Arbitrary Service', 'name_gu' => 'ભાવિ સેવા', 'service_fee' => 1000, 'gst_rate' => 18,
            'estimated_days' => 6, 'sort_order' => 1, 'is_active' => 1, 'available_online' => 1,
            'work_scope_item_ids' => [$scope->id], 'add_on_service_ids' => [$addOn->id],
            'documents' => [['name_en' => 'Future Property Record', 'name_gu' => 'ભાવિ મિલકત રેકોર્ડ', 'requirement_type' => 'any_one_required', 'sort_order' => 1]],
        ])->assertSessionHasNoErrors();

        $service = Service::query()->where('name_en', 'Future Arbitrary Service')->sole();
        $this->assertTrue($service->defaultWorkScopes()->whereKey($scope->id)->exists());
        $this->assertTrue($service->activeAvailableAddOns()->whereKey($addOn->id)->exists());
        $this->assertSame('any_one_required', $service->activeRequiredDocuments()->sole()->requirement_type);
        $this->get(route('request.create'))->assertSee('Future Arbitrary Service');
    }

    public function test_government_charge_master_is_manageable_and_request_snapshot_is_immutable(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.government-charge-types.store'), ['name_en' => 'Certified Copy Fee', 'name_gu' => 'પ્રમાણિત નકલ ફી', 'default_amount' => 100, 'sort_order' => 1, 'is_active' => 1])->assertSessionHasNoErrors();
        $type = GovernmentChargeType::query()->where('name_en', 'Certified Copy Fee')->sole();
        $service = Service::query()->create(['name_en' => 'Billing Service', 'name_gu' => 'Billing', 'slug' => 'billing-service', 'service_fee' => 1000, 'gst_rate' => 18, 'is_active' => true]);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/900002', 'service_id' => $service->id, 'name' => 'Billing', 'mobile' => '9999999999', 'status' => 'approved', 'case_planning_version' => CustomerRequest::CURRENT_CASE_PLANNING_VERSION]);
        $row = $request->requestServices()->create(['service_id' => $service->id, 'professional_fee' => 1000, 'original_professional_fee' => 1000, 'gst_rate' => 18, 'status' => 'approved', 'decided_at' => now(), 'approved_at' => now(), 'decided_by' => $admin->id]);
        $scope = WorkScopeItem::query()->create(['name_en' => 'Billing Work', 'name_gu' => 'Billing Work', 'normalized_name' => 'billing-work', 'is_active' => true]);
        $row->workScopes()->create(['work_scope_item_id' => $scope->id, 'name_en_snapshot' => $scope->name_en, 'is_custom' => false, 'status' => 'pending', 'display_order' => 1]);

        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), ['discount_type' => 'none', 'discount_value' => 0, 'gst_rate' => 18, 'government_charges' => [['government_charge_type_id' => $type->id, 'amount' => 100, 'display_order' => 0]]])->assertSessionHasNoErrors();
        $billing = $request->fresh()->billing;
        $this->assertSame(100.0, (float) $billing->government_charges_total);
        $this->assertSame(180.0, (float) $billing->gst_amount);
        $this->assertSame(1280.0, (float) $billing->grand_total);
        $this->assertSame('Certified Copy Fee', $billing->charges()->sole()->name);

        $type->update(['name_en' => 'Renamed Master', 'default_amount' => 999]);
        $this->assertSame('Certified Copy Fee', $billing->charges()->sole()->fresh()->name);
        $this->assertSame('Certified Copy Fee', $billing->history()->where('action', 'frozen')->sole()->pricing_snapshot['government_charges'][0]['name']);
    }
}
