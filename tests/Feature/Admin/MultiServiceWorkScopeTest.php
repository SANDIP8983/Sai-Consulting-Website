<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkScopeItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiServiceWorkScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_services_with_the_same_default_scope_are_valid_per_service(): void
    {
        [$request, $rows, $scopes] = $this->case(2, 1, true);
        $admin = User::factory()->create();
        $payload = $this->payload($rows, fn () => [$scopes[0]->id]);

        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), $payload)->assertSessionHasNoErrors();
        foreach ($rows as $row) {
            $this->assertDatabaseHas('request_service_work_scopes', ['request_service_id' => $row->id, 'work_scope_item_id' => $scopes[0]->id]);
            $this->assertSame(1, $row->workScopes()->count());
        }
    }

    public function test_two_services_keep_different_manual_scope_selections_isolated(): void
    {
        [$request, $rows, $scopes] = $this->case(2, 2);
        $admin = User::factory()->create();
        $payload = $this->payload($rows, fn ($index) => [$scopes[$index]->id]);

        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), $payload)->assertSessionHasNoErrors();
        $this->assertSame([$scopes[0]->id], $rows[0]->workScopes()->pluck('work_scope_item_id')->all());
        $this->assertSame([$scopes[1]->id], $rows[1]->workScopes()->pluck('work_scope_item_id')->all());
        $this->assertDatabaseMissing('request_service_work_scopes', ['request_service_id' => $rows[0]->id, 'work_scope_item_id' => $scopes[1]->id]);
        $this->assertDatabaseMissing('request_service_work_scopes', ['request_service_id' => $rows[1]->id, 'work_scope_item_id' => $scopes[0]->id]);
    }

    public function test_three_services_can_each_approve_a_scope(): void
    {
        [$request, $rows, $scopes] = $this->case(3, 3);
        $this->actingAs(User::factory()->create())->patch(route('admin.requests.case-planning.save', $request), $this->payload($rows, fn ($index) => [$scopes[$index]->id]))->assertSessionHasNoErrors();

        foreach ($rows as $index => $row) {
            $this->assertSame([$scopes[$index]->id], $row->workScopes()->pluck('work_scope_item_id')->all());
        }
    }

    public function test_duplicate_scope_within_one_service_is_rejected(): void
    {
        [$request, $rows, $scopes] = $this->case(2, 1);
        $payload = $this->payload($rows, fn () => [$scopes[0]->id]);
        $payload['services'][$rows[0]->id]['work_scope_ids'] = [$scopes[0]->id, $scopes[0]->id];

        $this->actingAs(User::factory()->create())->patch(route('admin.requests.case-planning.save', $request), $payload)
            ->assertSessionHasErrors("services.{$rows[0]->id}.work_scope_ids.0");
        $this->assertDatabaseCount('request_service_work_scopes', 0);
    }

    public function test_old_duplicate_input_renders_each_scope_checkbox_only_once(): void
    {
        [$request, $rows, $scopes] = $this->case(2, 1);
        $old = $this->payload($rows, fn () => [$scopes[0]->id, $scopes[0]->id]);
        $response = $this->actingAs(User::factory()->create())->withSession(['_old_input' => $old])->get(route('admin.requests.show', $request))->assertOk();

        foreach ($rows as $row) {
            $needle = 'services['.$row->id.'][work_scope_ids][]';
            $this->assertSame(1, substr_count($response->getContent(), $needle));
        }
    }

    public function test_valid_two_service_scopes_freeze_billing_and_remain_payment_eligible(): void
    {
        [$request, $rows, $scopes] = $this->case(2, 1);
        $admin = User::factory()->create();
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), $this->payload($rows, fn () => [$scopes[0]->id]))->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), ['discount_type' => 'none', 'discount_value' => 0, 'gst_rate' => 18, 'government_charges' => []])->assertSessionHasNoErrors();

        $this->assertSame('payment_pending', $request->fresh()->status);
        $this->assertSame(3000.0, (float) $request->fresh()->billing->net_professional_fee);
        $this->assertSame(540.0, (float) $request->fresh()->billing->gst_amount);
        $this->assertSame(3540.0, (float) $request->fresh()->billing->grand_total);
        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertOk()->assertSee('Save Payment Record');
    }

    private function case(int $serviceCount, int $scopeCount, bool $defaults = false): array
    {
        $suffix = fake()->unique()->numerify('######');
        $scopes = collect(range(1, $scopeCount))->map(fn ($index) => WorkScopeItem::query()->create(['name_en' => "Scope {$suffix}-{$index}", 'name_gu' => "Scope {$suffix}-{$index}", 'normalized_name' => "scope-{$suffix}-{$index}", 'is_active' => true]))->values();
        $services = collect(range(1, $serviceCount))->map(function ($index) use ($suffix, $scopes, $defaults) {
            $service = Service::query()->create(['name_en' => "Service {$suffix}-{$index}", 'name_gu' => "Service {$suffix}-{$index}", 'slug' => "service-{$suffix}-{$index}", 'service_fee' => $index * 1000, 'gst_rate' => 18, 'is_active' => true]);
            if ($defaults) {
                $service->defaultWorkScopes()->attach($scopes[0]->id, ['is_default' => true, 'display_order' => 0]);
            }

            return $service;
        })->values();
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/'.$suffix, 'case_planning_version' => CustomerRequest::CURRENT_CASE_PLANNING_VERSION, 'service_id' => $services[0]->id, 'name' => 'Scope Customer', 'mobile' => '9999999999', 'status' => 'received', 'payment_status' => 'not_required']);
        $rows = $services->map(fn ($service) => $request->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en, 'professional_fee' => $service->service_fee, 'gst_rate' => 18, 'status' => 'received']))->values();

        return [$request, $rows, $scopes];
    }

    private function payload($rows, callable $scopeIds): array
    {
        return ['services' => $rows->mapWithKeys(fn ($row, $index) => [$row->id => ['decision' => 'approved', 'work_scope_ids' => $scopeIds($index)]])->all()];
    }
}
