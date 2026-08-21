<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkScopeItem;
use App\Services\RequestBillingCalculator;
use Database\Seeders\WorkScopeItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAddedServiceFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorkScopeItemSeeder::class);
    }

    public function test_admin_adds_service_with_default_or_overridden_request_fee_without_changing_master_or_reference(): void
    {
        [$request] = $this->requestCase();
        $admin = User::factory()->create();
        $default = $this->service('Default Fee', 2000);
        $override = $this->service('Override Fee', 3000);
        $reference = $request->reference_no;

        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $default->id, 'professional_fee' => 2000])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $override->id, 'professional_fee' => 1750, 'internal_note' => 'Agreed scope'])->assertSessionHasNoErrors();

        $defaultRow = $request->requestServices()->where('service_id', $default->id)->sole();
        $overrideRow = $request->requestServices()->where('service_id', $override->id)->sole();
        $this->assertSame(2000.0, $defaultRow->billingProfessionalFee());
        $this->assertSame(3000.0, (float) $overrideRow->original_professional_fee);
        $this->assertSame(1750.0, $overrideRow->billingProfessionalFee());
        $this->assertSame($admin->id, $overrideRow->added_by);
        $this->assertSame('Agreed scope', $overrideRow->internal_note);
        $this->assertSame(3000.0, (float) $override->fresh()->service_fee);
        $this->assertSame($reference, $request->fresh()->reference_no);
        $this->assertDatabaseHas('request_service_approval_histories', ['request_service_id' => $overrideRow->id, 'action' => 'added', 'approved_by' => $admin->id]);
    }

    public function test_only_accepted_admin_added_fees_contribute_to_discount_gst_and_grand_total(): void
    {
        [$request, $primary] = $this->requestCase();
        $admin = User::factory()->create();
        $acceptedService = $this->service('Accepted Added', 2500);
        $rejectedService = $this->service('Rejected Added', 4000);
        $reviewService = $this->service('Review Added', 5000);
        foreach ([[$acceptedService, 2000], [$rejectedService, 3750], [$reviewService, 4500]] as [$service, $fee]) {
            $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $service->id, 'professional_fee' => $fee, 'internal_note' => 'Request-specific scope'])->assertSessionHasNoErrors();
        }
        $accepted = $request->requestServices()->where('service_id', $acceptedService->id)->sole();
        $rejected = $request->requestServices()->where('service_id', $rejectedService->id)->sole();
        $review = $request->requestServices()->where('service_id', $reviewService->id)->sole();
        $primaryScope = WorkScopeItem::query()->where('name_en', 'Drafting')->sole();
        $addedScope = WorkScopeItem::query()->where('name_en', 'Document Review')->sole();
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), ['services' => [
            $primary->id => ['decision' => 'approved', 'work_scope_ids' => [$primaryScope->id]],
            $accepted->id => ['decision' => 'approved', 'work_scope_ids' => [$addedScope->id]],
            $rejected->id => ['decision' => 'rejected', 'decision_notes' => 'Excluded'],
            $review->id => ['decision' => 'under_review'],
        ]])->assertSessionHasNoErrors();

        $calculation = app(RequestBillingCalculator::class)->calculate($request->fresh()->requestServices, 'fixed', 500, 18, [['name' => 'Stamp Duty', 'amount' => 300]]);
        $this->assertSame(5500.0, $calculation->totalProfessionalFee);
        $this->assertSame(5000.0, $calculation->netProfessionalFee);
        $this->assertSame(900.0, $calculation->gstAmount);
        $this->assertSame(6200.0, $calculation->grandTotal);

        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), ['services' => [
            $primary->id => ['decision' => 'approved', 'work_scope_ids' => [$primaryScope->id]],
            $accepted->id => ['decision' => 'approved', 'work_scope_ids' => [$addedScope->id]],
            $rejected->id => ['decision' => 'rejected', 'decision_notes' => 'Excluded'],
            $review->id => ['decision' => 'rejected', 'decision_notes' => 'Not finalized'],
        ]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), ['discount_type' => 'fixed', 'discount_value' => 500, 'discount_reason' => 'management_approval', 'gst_rate' => 18, 'government_charges' => [['name' => 'Stamp Duty', 'amount' => 300]]])->assertSessionHasNoErrors();
        $billing = $request->fresh()->billing;
        $this->assertSame(5500.0, (float) $billing->total_original_professional_fee);
        $this->assertSame(900.0, (float) $billing->gst_amount);
        $this->assertSame(6200.0, (float) $billing->grand_total);
    }

    public function test_freeze_blocks_fee_edit_and_audited_unlock_allows_adjustment_and_refreeze(): void
    {
        [$request, $primary] = $this->requestCase();
        $admin = User::factory()->create();
        $addedService = $this->service('Unlock Fee', 2000);
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $addedService->id, 'professional_fee' => 2000])->assertSessionHasNoErrors();
        $added = $request->requestServices()->where('service_id', $addedService->id)->sole();
        $this->decideForFreeze($admin, $request, $primary, $added);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $added]), ['professional_fee' => 2250])->assertSessionHasErrors('case');
        $this->assertSame(2000.0, (float) $added->fresh()->professional_fee);
        $this->actingAs($admin)->patch(route('admin.requests.billing.unlock', $request), ['unlock_reason' => 'Customer approved revised scope'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $added]), ['professional_fee' => 2250, 'internal_note' => 'Revised scope'])->assertSessionHasNoErrors();
        $this->assertSame(2250.0, (float) $added->fresh()->professional_fee);
        $this->assertDatabaseHas('request_billing_histories', ['request_id' => $request->id, 'action' => 'unlocked', 'reason' => 'Customer approved revised scope', 'changed_by' => $admin->id]);
        $this->assertDatabaseHas('request_service_approval_histories', ['request_service_id' => $added->id, 'action' => 'fee_updated', 'approved_by' => $admin->id]);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $this->assertSame(5750.0, (float) $request->fresh()->billing->total_original_professional_fee);
    }

    public function test_paid_request_blocks_normal_fee_edit_but_existing_audited_unlock_is_the_exception(): void
    {
        [$request, $primary] = $this->requestCase();
        $admin = User::factory()->create();
        $addedService = $this->service('Paid Fee', 1500);
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $addedService->id, 'professional_fee' => 1500])->assertSessionHasNoErrors();
        $added = $request->requestServices()->where('service_id', $addedService->id)->sole();
        $this->decideForFreeze($admin, $request, $primary, $added);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $total = (float) $request->fresh()->billing->grand_total;
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), ['amount' => $total, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $added]), ['professional_fee' => 1600])->assertSessionHasErrors('case');
        $this->actingAs($admin)->patch(route('admin.requests.billing.unlock', $request), ['unlock_reason' => 'Director-approved paid-case correction'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $added]), ['professional_fee' => 1600, 'internal_note' => 'Paid-case correction'])->assertSessionHasNoErrors();
        $this->assertSame(1600.0, (float) $added->fresh()->professional_fee);
        $this->assertDatabaseCount('request_payments', 1);
    }

    public function test_customer_tracking_shows_only_accepted_request_fee_and_no_admin_metadata_or_default_fee(): void
    {
        [$request, $primary] = $this->requestCase();
        $admin = User::factory()->create(['name' => 'Private Pricing Admin']);
        $addedService = $this->service('Customer Safe Added', 1777);
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $addedService->id, 'professional_fee' => 1234, 'internal_note' => 'Private override rationale'])->assertSessionHasNoErrors();
        $added = $request->requestServices()->where('service_id', $addedService->id)->sole();
        $this->decideForFreeze($admin, $request, $primary, $added);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()
            ->assertSee('Customer Safe Added')->assertSee('1,234.00')->assertDontSee('1,777.00')
            ->assertDontSee('Private override rationale')->assertDontSee('Private Pricing Admin')->assertDontSee('Default Fee');
    }

    public function test_duplicate_inactive_and_cross_request_edits_are_rejected_but_unfrozen_add_on_can_be_removed(): void
    {
        [$request] = $this->requestCase();
        [$otherRequest] = $this->requestCase('SC/2026/810002', 'Other Primary Service');
        $admin = User::factory()->create();
        $service = $this->service('Unique Added', 1000);
        $inactive = $this->service('Inactive Added', 1000, false);
        $payload = ['service_id' => $service->id, 'professional_fee' => 1000];
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), $payload)->assertSessionHasErrors('service_id');
        $this->actingAs($admin)->post(route('admin.requests.services.add', $request), ['service_id' => $inactive->id, 'professional_fee' => 1000])->assertSessionHasErrors('service_id');
        $added = $request->requestServices()->where('service_id', $service->id)->sole();
        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$otherRequest, $added]), ['professional_fee' => 900])->assertNotFound();
        $added->update(['status' => 'approved']);
        $this->actingAs($admin)->delete(route('admin.requests.services.remove', [$request, $added]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('request_services', ['id' => $added->id]);
    }

    public function test_base_service_fee_is_request_specific_reasoned_audited_and_uses_final_fee_for_billing(): void
    {
        [$request, $primary] = $this->requestCase();
        $admin = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $primary]), [
            'professional_fee' => 4000,
        ])->assertSessionHasErrors('internal_note');

        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $primary]), [
            'professional_fee' => 'invalid',
            'internal_note' => 'Complex title review',
        ])->assertSessionHasErrors('professional_fee');

        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $primary]), [
            'professional_fee' => 4000,
            'internal_note' => 'Additional Survey Numbers',
        ])->assertSessionHasNoErrors();

        $primary->refresh();
        $this->assertSame(3500.0, (float) $primary->original_professional_fee);
        $this->assertSame(4000.0, (float) $primary->professional_fee);
        $this->assertSame(3500.0, (float) $primary->service->fresh()->service_fee);
        $this->assertDatabaseHas('request_service_approval_histories', [
            'request_service_id' => $primary->id,
            'request_id' => $request->id,
            'approved_by' => $admin->id,
            'action' => 'fee_updated',
            'note' => 'Additional Survey Numbers',
        ]);

        $scope = WorkScopeItem::query()->where('name_en', 'Drafting')->sole();
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), ['services' => [
            $primary->id => ['decision' => 'approved', 'work_scope_ids' => [$scope->id]],
        ]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), [
            'discount_type' => 'none',
            'discount_value' => 0,
            'gst_rate' => 18,
            'government_charges' => [['name' => 'Stamp Duty', 'amount' => 500]],
        ])->assertSessionHasNoErrors();

        $billing = $request->fresh()->billing;
        $this->assertSame(4000.0, (float) $billing->total_original_professional_fee);
        $this->assertSame(720.0, (float) $billing->gst_amount);
        $this->assertSame(500.0, (float) $billing->government_charges_total);
        $this->assertSame(5220.0, (float) $billing->grand_total);
    }

    public function test_base_fee_equal_to_snapshot_needs_no_reason_and_staff_cannot_change_pricing(): void
    {
        [$request, $primary] = $this->requestCase();
        $admin = User::factory()->create();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $primary]), [
            'professional_fee' => 3500,
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->patch(route('admin.requests.services.fee.update', [$request, $primary]), [
            'professional_fee' => 4000,
            'internal_note' => 'Unauthorized attempt',
        ])->assertForbidden();
        $this->assertSame(3500.0, (float) $primary->fresh()->professional_fee);
    }

    private function requestCase(string $reference = 'SC/2026/810001', string $primaryName = 'Primary Service'): array
    {
        $service = $this->service($primaryName, 3500);
        $request = CustomerRequest::query()->create(['reference_no' => $reference, 'service_id' => $service->id, 'name' => 'Fee Customer', 'mobile' => '9999999999', 'request_origin' => 'online', 'status' => 'under_review', 'payment_status' => 'not_required', 'amount_due' => 0, 'last_status_changed_at' => now()]);
        $row = $request->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en, 'service_name_gu_snapshot' => $service->name_gu, 'professional_fee' => 3500, 'original_professional_fee' => 3500, 'gst_rate' => 18, 'status' => 'under_review']);

        return [$request, $row];
    }

    private function decideForFreeze(User $admin, CustomerRequest $request, $primary, $added): void
    {
        $primaryScope = WorkScopeItem::query()->where('name_en', 'Drafting')->sole();
        $addedScope = WorkScopeItem::query()->where('name_en', 'Document Review')->sole();
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), ['services' => [
            $primary->id => ['decision' => 'approved', 'work_scope_ids' => [$primaryScope->id]],
            $added->id => ['decision' => 'approved', 'work_scope_ids' => [$addedScope->id]],
        ]])->assertSessionHasNoErrors();
    }

    private function billingPayload(): array
    {
        return ['discount_type' => 'none', 'discount_value' => 0, 'gst_rate' => 18, 'government_charges' => []];
    }

    private function service(string $name, float $fee, bool $active = true): Service
    {
        $service = Service::query()->create(['name_en' => $name, 'name_gu' => $name, 'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999), 'service_fee' => $fee, 'gst_rate' => 18, 'estimated_days' => 5, 'is_active' => $active, 'available_online' => true, 'available_offline' => true]);
        Service::query()->whereKeyNot($service->id)->get()->each(fn (Service $base) => $base->availableAddOns()->syncWithoutDetaching($service->id));

        return $service;
    }
}
