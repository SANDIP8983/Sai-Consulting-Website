<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkScopeItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaidAddOnWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_scope_is_included_and_paid_add_on_is_frozen_in_request_billing(): void
    {
        $admin = User::factory()->create();
        $included = $this->scope('Initial Review');
        $addOnWork = $this->scope('Title Verification');
        $base = $this->service('Sale Deed', 1999, [$included]);
        $addOn = $this->service('Title Verification', 2499, [$addOnWork]);
        [$request, $baseRow] = $this->requestWithBase($base);

        $this->approve($admin, $request, $baseRow);
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), [
            'service_id' => $addOn->id,
            'professional_fee' => 2499,
            'internal_note' => 'Request-specific title review',
        ])->assertSessionHasNoErrors();
        $addOnRow = $request->requestServices()->where('service_id', $addOn->id)->sole();
        $this->approve($admin, $request, $addOnRow);

        $this->assertSame(1999.0, $baseRow->fresh()->billingProfessionalFee());
        $this->assertSame(1, $baseRow->workScopes()->count());
        $this->assertSame(2499.0, $addOnRow->fresh()->billingProfessionalFee());

        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), [
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'discount_reason' => 'management_approval',
            'gst_rate' => 18,
            'government_charges' => [['name' => 'Registration Fee', 'amount' => 500, 'display_order' => 0]],
        ])->assertSessionHasNoErrors();

        $billing = $request->fresh()->billing;
        $this->assertSame(4498.0, (float) $billing->total_original_professional_fee);
        $this->assertSame(449.8, (float) $billing->discount_amount);
        $this->assertSame(4048.2, (float) $billing->net_professional_fee);
        $this->assertSame(728.68, (float) $billing->gst_amount);
        $this->assertSame(500.0, (float) $billing->government_charges_total);
        $this->assertSame(5276.88, (float) $billing->grand_total);

        $snapshot = $billing->history()->where('action', 'frozen')->sole()->pricing_snapshot;
        $this->assertSame(['base_service', 'add_on'], array_column($snapshot['services'], 'billing_role'));
        $this->assertSame([1999.0, 2499.0], array_map('floatval', array_column($snapshot['services'], 'professional_fee')));

        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $addOnRow]), [
            'professional_fee' => 1,
        ])->assertSessionHasErrors('case');
        $this->actingAs($admin)->delete(route('admin.requests.services.remove', [$request, $addOnRow]))
            ->assertSessionHasErrors('case');
        $this->assertSame(2499.0, $addOnRow->fresh()->billingProfessionalFee());
    }

    public function test_multiple_add_ons_total_and_an_approved_add_on_can_be_removed_before_freeze(): void
    {
        $admin = User::factory()->create();
        $base = $this->service('Base Service', 1000, [$this->scope('Base Work')]);
        $first = $this->service('GARVI Work', 500);
        $second = $this->service('Registration Preparation', 700);
        [$request, $baseRow] = $this->requestWithBase($base);
        $this->approve($admin, $request, $baseRow);

        foreach ([[$first, 500], [$second, 700]] as [$service, $fee]) {
            $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $service->id, 'professional_fee' => $fee])->assertSessionHasNoErrors();
            $this->approve($admin, $request, $request->requestServices()->where('service_id', $service->id)->sole());
        }

        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $first->id, 'professional_fee' => 500])
            ->assertSessionHasErrors('service_id');

        $firstRow = $request->requestServices()->where('service_id', $first->id)->sole();
        $this->actingAs($admin)->delete(route('admin.requests.services.remove', [$request, $firstRow]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('request_services', ['id' => $firstRow->id]);

        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $this->assertSame(1700.0, (float) $request->fresh()->billing->total_original_professional_fee);
    }

    public function test_operational_add_on_adds_only_unique_checklist_work_and_percentage_remains_correct(): void
    {
        $admin = User::factory()->create();
        $shared = $this->scope('Initial Review');
        $unique = $this->scope('Final Title Report');
        $base = $this->service('Sale Deed', 1999, [$shared]);
        $addOn = $this->service('Title Report', 2499, [$shared, $unique]);
        [$request, $baseRow] = $this->requestWithBase($base);
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $addOn->id, 'professional_fee' => 2499])->assertSessionHasNoErrors();
        $addOnRow = $request->requestServices()->where('service_id', $addOn->id)->sole();
        $this->approve($admin, $request, $addOnRow);
        $this->approve($admin, $request, $baseRow);

        $this->assertSame([$shared->id], $baseRow->workScopes()->pluck('work_scope_item_id')->all());
        $this->assertSame([$unique->id], $addOnRow->workScopes()->pluck('work_scope_item_id')->all());
        $this->assertDatabaseCount('request_service_work_scopes', 2);

        $baseRow->workScopes()->first()->update(['status' => 'completed']);
        $this->actingAs($admin)->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('50%')
            ->assertSee('Title Report')
            ->assertSee('Included in selected service fee')
            ->assertSee('Additional Paid Services')
            ->assertSee('Add-on / Additional Charge');
    }

    public function test_multi_service_request_with_add_on_uses_all_approved_fees_once(): void
    {
        $admin = User::factory()->create();
        $first = $this->service('Sale Deed', 1999, [$this->scope('Draft')]);
        $second = $this->service('Legal Consulting', 1499, [$this->scope('Guidance')]);
        $addOn = $this->service('Token Booking', 399);
        [$request, $firstRow] = $this->requestWithBase($first);
        $secondRow = $request->requestServices()->create($this->serviceSnapshot($second));
        $this->approve($admin, $request, $firstRow);
        $this->approve($admin, $request, $secondRow);
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $addOn->id, 'professional_fee' => 399])->assertSessionHasNoErrors();
        $this->approve($admin, $request, $request->requestServices()->where('service_id', $addOn->id)->sole());

        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $this->assertSame(3897.0, (float) $request->fresh()->billing->total_original_professional_fee);
    }

    public function test_paid_or_closed_historical_request_cannot_gain_or_remove_add_ons(): void
    {
        $admin = User::factory()->create();
        $base = $this->service('Historical Base', 1000);
        $addOn = $this->service('Historical Add-on', 500);
        [$request] = $this->requestWithBase($base, ['status' => 'closed', 'payment_status' => 'received', 'closed_at' => now()]);
        $existing = $request->requestServices()->create([...$this->serviceSnapshot($addOn), 'is_admin_added' => true, 'added_by' => $admin->id, 'status' => 'approved']);

        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $addOn->id, 'professional_fee' => 1])->assertSessionHasErrors('case');
        $this->actingAs($admin)->delete(route('admin.requests.services.remove', [$request, $existing]))->assertSessionHasErrors('case');
        $this->assertDatabaseHas('request_services', ['id' => $existing->id, 'professional_fee' => 500]);
    }

    private function approve(User $admin, CustomerRequest $request, $row): void
    {
        $this->actingAs($admin)->patch(route('admin.requests.services.decision', [$request, $row]), ['decision' => 'approved'])->assertSessionHasNoErrors();
    }

    private function billingPayload(): array
    {
        return ['discount_type' => 'none', 'discount_value' => 0, 'gst_rate' => 18, 'government_charges' => []];
    }

    private function scope(string $name): WorkScopeItem
    {
        $key = str($name)->slug().'-'.fake()->unique()->numerify('######');

        return WorkScopeItem::query()->create(['name_en' => $name, 'name_gu' => $name, 'normalized_name' => $key, 'is_active' => true]);
    }

    private function service(string $name, float $fee, array $scopes = []): Service
    {
        $suffix = fake()->unique()->numerify('######');
        $service = Service::query()->create(['name_en' => $name, 'name_gu' => $name, 'slug' => str($name)->slug().'-'.$suffix, 'service_fee' => $fee, 'gst_rate' => 18, 'estimated_days' => 4, 'is_active' => true]);
        foreach ($scopes as $order => $scope) {
            $service->defaultWorkScopes()->attach($scope->id, ['is_default' => true, 'display_order' => $order + 1]);
        }

        return $service;
    }

    private function requestWithBase(Service $service, array $attributes = []): array
    {
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/'.fake()->unique()->numerify('######'), 'case_planning_version' => CustomerRequest::CURRENT_CASE_PLANNING_VERSION, 'service_id' => $service->id, 'name' => 'Add-on Customer', 'mobile' => '9999999999', 'status' => 'received', 'payment_status' => 'not_required', ...$attributes]);
        $row = $request->requestServices()->create($this->serviceSnapshot($service));

        return [$request, $row];
    }

    private function serviceSnapshot(Service $service): array
    {
        return ['service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en, 'service_name_gu_snapshot' => $service->name_gu, 'professional_fee' => $service->service_fee, 'original_professional_fee' => $service->service_fee, 'gst_rate' => $service->gst_rate, 'estimated_days' => $service->estimated_days, 'status' => 'received'];
    }
}
