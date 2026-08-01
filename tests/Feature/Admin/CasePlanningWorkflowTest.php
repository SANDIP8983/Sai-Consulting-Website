<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkScopeItem;
use Database\Seeders\WorkScopeItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasePlanningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorkScopeItemSeeder::class);
    }

    public function test_new_requests_begin_received_and_never_archived(): void
    {
        $service = $this->service('Initial');
        $this->post(route('request.store'), $this->requestPayload($service))->assertRedirect(route('request.success'));
        $this->assertSame('received', CustomerRequest::query()->sole()->status);
    }

    public function test_admin_accepts_one_service_rejects_another_and_rejection_reason_is_required(): void
    {
        $admin = User::factory()->create();
        [$request,$first,$second] = $this->case(2);
        $drafting = WorkScopeItem::where('name_en', 'Drafting')->sole();
        $payload = ['services' => [$first->id => ['decision' => 'approved', 'work_scope_ids' => [$drafting->id]], $second->id => ['decision' => 'rejected', 'decision_notes' => '']]];
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), $payload)->assertSessionHasErrors("services.{$second->id}.decision_notes");
        $payload['services'][$second->id]['decision_notes'] = 'Outside requested engagement';
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), $payload)->assertSessionHasNoErrors();
        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('rejected', $second->fresh()->status);
    }

    public function test_accepted_service_requires_scope_and_drafting_only_is_valid(): void
    {
        $admin = User::factory()->create();
        [$request,$service] = $this->case();
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), ['services' => [$service->id => ['decision' => 'approved']]])->assertSessionHasErrors("services.{$service->id}.work_scope_ids");
        $drafting = WorkScopeItem::where('name_en', 'Drafting')->sole();
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), ['services' => [$service->id => ['decision' => 'approved', 'work_scope_ids' => [$drafting->id]]]])->assertSessionHasNoErrors();
        $this->assertSame(['Drafting'], $service->fresh()->workScopes->pluck('name_en_snapshot')->all());
        $this->assertDatabaseCount('request_service_work_scopes', 1);
    }

    public function test_admin_adds_active_service_without_changing_reference(): void
    {
        $admin = User::factory()->create();
        [$request] = $this->case();
        $added = $this->service('Added');
        $reference = $request->reference_no;
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $added->id])->assertSessionHasNoErrors();
        $this->assertSame($reference, $request->fresh()->reference_no);
        $this->assertDatabaseHas('request_services', ['request_id' => $request->id, 'service_id' => $added->id, 'status' => 'under_review']);
    }

    public function test_only_accepted_services_affect_bill_and_file_number_is_generated_once(): void
    {
        $admin = User::factory()->create();
        [$request,$accepted,$rejected,$review] = $this->case(3);
        $scope = WorkScopeItem::where('name_en', 'Drafting')->sole();
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), ['services' => [$accepted->id => ['decision' => 'approved', 'work_scope_ids' => [$scope->id]], $rejected->id => ['decision' => 'rejected', 'decision_notes' => 'Excluded'], $review->id => ['decision' => 'under_review']]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $request->refresh();
        $file = $request->file_number;
        $this->assertSame('1000.00', $request->billing->total_original_professional_fee);
        $this->assertNull($request->billing->pricing_locked_at);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $this->assertSame($file, $request->fresh()->file_number);
    }

    public function test_all_rejected_case_has_no_file_number(): void
    {
        $admin = User::factory()->create();
        [$request,$first,$second] = $this->case(2);
        $payload = ['services' => [$first->id => ['decision' => 'rejected', 'decision_notes' => 'Not accepted'], $second->id => ['decision' => 'rejected', 'decision_notes' => 'Not accepted']]];
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.reject', $request))->assertSessionHasNoErrors();
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertNull($request->fresh()->file_number);
    }

    public function test_payment_confirmed_case_protects_planning_and_internal_notes_are_private(): void
    {
        $admin = User::factory()->create();
        [$request,$service] = $this->case();
        $scope = WorkScopeItem::where('name_en', 'Drafting')->sole();
        $payload = ['services' => [$service->id => ['decision' => 'approved', 'decision_notes' => 'Private rejection or negotiation note', 'customer_decision_message' => 'Service accepted', 'work_scope_ids' => [$scope->id], 'internal_note' => 'Private scope note']]];
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $request->billing()->update(['pricing_locked_at' => now()]);
        $request->update(['payment_status' => 'received']);
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), $payload)->assertSessionHasErrors('case');
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertSee('Drafting')->assertDontSee('Private scope note')->assertDontSee('Private rejection or negotiation note')->assertDontSee($admin->name);
    }

    public function test_case_completes_only_after_every_selected_scope_is_finished(): void
    {
        $admin = User::factory()->create();
        [$request, $service] = $this->case();
        $drafting = WorkScopeItem::where('name_en', 'Drafting')->sole();
        $review = WorkScopeItem::where('name_en', 'Document Review')->sole();
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), [
            'services' => [$service->id => [
                'decision' => 'approved',
                'work_scope_ids' => [$drafting->id, $review->id],
            ]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('admin.requests.case-planning.complete', $request))
            ->assertSessionHasErrors('work_scopes');

        foreach ($service->fresh()->workScopes as $index => $scope) {
            $this->actingAs($admin)->patch(route('admin.requests.work-scopes.update', [$request, $scope]), [
                'status' => $index === 0 ? 'completed' : 'cancelled',
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($admin)->patch(route('admin.requests.case-planning.complete', $request))
            ->assertSessionHasNoErrors();
        $this->assertSame('completed', $request->fresh()->status);
        $this->actingAs($admin)->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Case Planning and normal processing are locked')
            ->assertDontSee('Save Case Planning');
    }

    private function case(int $count = 1): array
    {
        $service = $this->service('Primary');
        $request = CustomerRequest::create(['reference_no' => 'SC/2026/'.fake()->unique()->numerify('######'), 'service_id' => $service->id, 'name' => 'Case Customer', 'mobile' => '9999999999', 'request_origin' => 'online', 'status' => 'under_review', 'payment_status' => 'not_required', 'amount_due' => 0, 'last_status_changed_at' => now()]);
        $rows = collect(range(1, $count))->map(function ($index) use ($request, $service) {
            $current = $index === 1 ? $service : $this->service('Service '.$index);

            return $request->requestServices()->create(['service_id' => $current->id, 'service_name_en_snapshot' => $current->name_en, 'service_name_gu_snapshot' => $current->name_gu, 'professional_fee' => 1000 * $index, 'original_professional_fee' => 1000 * $index, 'gst_rate' => 18, 'status' => 'received']);
        });

        return [$request, ...$rows];
    }

    private function service(string $name): Service
    {
        return Service::create(['name_en' => $name.' '.fake()->unique()->numerify('######'), 'name_gu' => 'સેવા '.fake()->unique()->numerify('######'), 'slug' => str($name)->slug().'-'.fake()->unique()->numerify('######'), 'service_fee' => 1000, 'gst_rate' => 18, 'estimated_days' => 3, 'is_active' => true, 'available_online' => true, 'available_offline' => true]);
    }

    private function requestPayload(Service $service): array
    {
        return [
            'service_ids' => [$service->id],
            'name' => 'New Customer',
            'mobile' => '9999999999',
            'property_village' => 'Chanasma',
            'property_taluka' => 'Chanasma',
            'property_district' => 'Patan',
            'declaration' => '1',
        ];
    }

    private function billingPayload(): array
    {
        return ['discount_type' => 'none', 'discount_value' => 0, 'gst_rate' => 18, 'government_charges' => []];
    }
}
