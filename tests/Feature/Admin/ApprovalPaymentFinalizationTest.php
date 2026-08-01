<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalPaymentFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_finalize_request_scoped_pricing_with_correct_gst_formula(): void
    {
        [$request, $item, $service] = $this->requestWithService();
        $payload = $this->approvalPayload();

        $this->patch(route('admin.requests.services.decision', [$request, $item]), $payload)->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $this->actingAs($admin)->patch(route('admin.requests.services.decision', [$request, $item]), $payload)->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(1000.0, (float) $item->original_professional_fee);
        $this->assertSame(100.0, (float) $item->discount_amount);
        $this->assertSame(900.0, (float) $item->net_professional_fee);
        $this->assertSame(162.0, (float) $item->gst_amount);
        $this->assertSame(300.0, (float) $item->government_charges);
        $this->assertSame(1362.0, (float) $item->final_total);
        $this->assertNotNull($item->pricing_locked_at);
        $this->assertSame(1000.0, (float) $service->fresh()->service_fee);
        $this->assertSame(50.0, (float) $service->fresh()->government_charges);
        $this->assertDatabaseHas('request_service_approval_histories', ['request_service_id' => $item->id, 'request_id' => $request->id, 'approved_by' => $admin->id, 'action' => 'approved']);
        $this->assertSame('payment_pending', $request->fresh()->status);
        $this->assertSame('pending', $request->fresh()->payment_status);
        $this->assertNotNull($request->fresh()->file_number);
    }

    public function test_locked_pricing_requires_explicit_audited_unlock_before_change(): void
    {
        [$request, $item] = $this->requestWithService();
        $admin = User::factory()->create();
        $this->actingAs($admin)->patch(route('admin.requests.services.decision', [$request, $item]), $this->approvalPayload())->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('admin.requests.services.decision', [$request, $item]), $this->approvalPayload(['discount_value' => 20]))->assertSessionHasErrors('pricing');
        $this->get(route('admin.requests.show', $request))->assertOk();
        $this->actingAs($admin)->patch(route('admin.requests.services.pricing.unlock', [$request, $item]), ['unlock_note' => 'Management correction approved.'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.services.decision', [$request, $item]), $this->approvalPayload(['discount_value' => 20]))->assertSessionHasNoErrors();

        $this->assertSame(200.0, (float) $item->fresh()->discount_amount);
        $this->assertDatabaseHas('request_service_approval_histories', ['request_service_id' => $item->id, 'action' => 'unlocked', 'note' => 'Management correction approved.']);
        $this->assertSame(3, $item->approvalHistory()->count());
    }

    public function test_customer_tracking_shows_finalized_summary_without_internal_discount_data(): void
    {
        [$request, $item] = $this->requestWithService();
        $admin = User::factory()->create();
        $this->actingAs($admin)->patch(route('admin.requests.services.decision', [$request, $item]), $this->approvalPayload(['decision_notes' => 'Private negotiation note']))->assertSessionHasNoErrors();

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()->assertSee('Payment Summary')->assertSee('Professional Fee')->assertSee('GST')->assertSee('Stamp Duty')->assertSee('Grand Total')
            ->assertSee('તમારી અરજી મંજૂર થઈ છે.')->assertDontSee('Regular Customer')->assertDontSee('Private negotiation note');
    }

    private function requestWithService(): array
    {
        $service = Service::query()->create(['name_en' => 'Approval Service', 'name_gu' => 'મંજૂરી સેવા', 'slug' => 'approval-service', 'service_fee' => 1000, 'gst_rate' => 18, 'government_charges' => 50, 'estimated_days' => 5, 'is_active' => true]);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/900001', 'service_id' => $service->id, 'name' => 'Approval Customer', 'mobile' => '9999999999', 'status' => 'under_review', 'payment_status' => 'not_required', 'amount_due' => 1230, 'last_status_changed_at' => now()]);
        $item = $request->requestServices()->create(['service_id' => $service->id, 'professional_fee' => 1000, 'gst_rate' => 18, 'government_charges' => 50, 'government_charges_snapshot' => [['name' => 'Legacy Charge', 'amount' => 50]], 'status' => 'under_review']);
        return [$request, $item, $service];
    }

    private function approvalPayload(array $overrides = []): array
    {
        return array_merge(['decision' => 'approved', 'discount_type' => 'percentage', 'discount_value' => 10, 'discount_reason' => 'regular_customer', 'government_charges' => [['name' => 'Stamp Duty', 'amount' => 200, 'note' => 'As applicable'], ['name' => 'Registration Fee', 'amount' => 100]], 'decision_notes' => 'Approved after review.'], $overrides);
    }
}
