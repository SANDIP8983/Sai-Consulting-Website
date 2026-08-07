<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\RequestBillingStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalPaymentFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_request_discount_gst_and_government_charges_are_calculated_at_bill_level(): void
    {
        [$request, $items] = $this->requestWithServices();
        $admin = User::factory()->create();
        $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['discount_type' => 'fixed', 'discount_value' => 300]))->assertSessionHasNoErrors();

        $billing = $request->fresh()->billing;
        $this->assertSame(3000.0, (float) $billing->total_original_professional_fee);
        $this->assertSame(300.0, (float) $billing->discount_amount);
        $this->assertSame(2700.0, (float) $billing->net_professional_fee);
        $this->assertSame(486.0, (float) $billing->gst_amount);
        $this->assertSame(300.0, (float) $billing->government_charges_total);
        $this->assertSame(3486.0, (float) $billing->grand_total);
        $this->assertSame(3486.0, (float) $request->fresh()->amount_due);
        $this->assertSame(1000.0, (float) $items[0]->fresh()->professional_fee);
        $this->assertSame('payment_pending', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->file_number);
    }

    public function test_percentage_and_zero_discounts_are_supported(): void
    {
        [$request, $items] = $this->requestWithServices();
        $admin = User::factory()->create();
        $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $this->assertSame(300.0, (float) $request->fresh()->billing->discount_amount);
        $this->actingAs($admin)->patch(route('admin.requests.billing.unlock', $request), ['unlock_reason' => 'Approved correction'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['discount_type' => 'none', 'discount_value' => 0, 'discount_reason' => null]))->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) $request->fresh()->billing->discount_amount);
    }

    public function test_excessive_discounts_are_rejected_server_side(): void
    {
        [$request, $items] = $this->requestWithServices();
        $admin = User::factory()->create();
        $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['discount_type' => 'fixed', 'discount_value' => 3001]))->assertSessionHasErrors('discount_value');
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['discount_value' => 101]))->assertSessionHasErrors('discount_value');
        $this->assertDatabaseCount('request_billings', 0);
    }

    public function test_pricing_freeze_requires_explicit_audited_unlock_and_payment_relocks_it(): void
    {
        [$request, $items] = $this->requestWithServices();
        $admin = User::factory()->create();
        $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['discount_value' => 20]))->assertSessionHasErrors('pricing');
        $this->actingAs($admin)->patch(route('admin.requests.billing.unlock', $request), ['unlock_reason' => 'Management correction approved'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), ['amount' => 3486, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasErrors('payment');
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), ['amount' => 3486, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();
        $this->assertTrue($request->fresh()->billing->isLocked());
        $this->assertDatabaseHas('request_billing_histories', ['request_id' => $request->id, 'action' => 'unlocked', 'reason' => 'Management correction approved']);
    }

    public function test_customer_tracking_uses_request_summary_without_private_metadata(): void
    {
        [$request, $items] = $this->requestWithServices();
        $admin = User::factory()->create();
        $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload(['internal_note' => 'Private negotiation note']))->assertSessionHasNoErrors();
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertSee('Payment Summary')->assertSee('Total Professional Fee')->assertSee('GST')->assertSee('Stamp Duty')->assertSee('Grand Total')->assertDontSee('Regular Customer')->assertDontSee('Private negotiation note')->assertDontSee($admin->name);
    }

    public function test_legacy_frozen_totals_remain_unchanged_and_render_without_new_billing(): void
    {
        $service = $this->service('Legacy', 1000);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/999901', 'service_id' => $service->id, 'name' => 'Legacy Customer', 'mobile' => '9999999999', 'status' => 'payment_received', 'payment_status' => 'received', 'amount_due' => 1234, 'amount_paid' => 1234]);
        $request->requestServices()->create(['service_id' => $service->id, 'professional_fee' => 1000, 'net_professional_fee' => 900, 'gst_rate' => 18, 'gst_amount' => 162, 'government_charges' => 172, 'government_charges_snapshot' => [['name' => 'Legacy Charge', 'amount' => 172]], 'final_total' => 1234, 'pricing_locked_at' => now(), 'status' => 'approved']);
        $this->assertNull($request->fresh()->billing);
        $this->assertSame(1234.0, (float) $request->fresh()->amount_due);
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertSee('Payment Summary')->assertSee('1,234.00');
    }

    public function test_historical_paid_saved_billing_is_legacy_locked_without_backfill(): void
    {
        $service = $this->service('Historical Saved Billing', 3500);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/999904', 'file_number' => 'SC/2026/F999904', 'service_id' => $service->id, 'name' => 'Historical Paid Customer', 'mobile' => '9999999999', 'status' => 'completed', 'payment_status' => 'received', 'amount_due' => 4130, 'amount_paid' => 4330]);
        $request->requestServices()->create(['service_id' => $service->id, 'professional_fee' => 3500, 'original_professional_fee' => 3500, 'gst_rate' => 18, 'status' => 'approved']);
        $billing = $request->billing()->create(['total_original_professional_fee' => 3500, 'discount_type' => 'none', 'discount_value' => 0, 'discount_amount' => 0, 'net_professional_fee' => 3500, 'gst_rate' => 18, 'gst_amount' => 630, 'government_charges_total' => 0, 'grand_total' => 4130]);
        $billing->history()->create(['request_id' => $request->id, 'changed_by' => null, 'action' => 'saved', 'pricing_snapshot' => ['grand_total' => 4130]]);
        $request->payments()->create(['amount' => 4130, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()]);
        $request->payments()->create(['amount' => 200, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()]);

        $state = app(RequestBillingStateResolver::class)->resolve($request->fresh());

        $this->assertSame('legacy_paid', $state->lifecycle);
        $this->assertTrue($state->legacy);
        $this->assertTrue($state->pricingLocked);
        $this->assertSame('paid', $state->paymentStatus);
        $this->assertSame(4330.0, $state->confirmedPaidAmount);
        $this->assertNull($billing->fresh()->pricing_locked_at);
        $this->assertDatabaseHas('request_billing_histories', ['request_id' => $request->id, 'action' => 'saved']);
    }

    public function test_current_unfrozen_billing_cannot_accept_payment_or_masquerade_as_legacy(): void
    {
        $service = $this->service('Current Unfrozen Billing', 3500);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/999905', 'file_number' => 'SC/2026/F999905', 'service_id' => $service->id, 'name' => 'Current Customer', 'mobile' => '9999999999', 'status' => 'payment_pending', 'payment_status' => 'pending', 'amount_due' => 4130, 'amount_paid' => 0]);
        $request->requestServices()->create(['service_id' => $service->id, 'professional_fee' => 3500, 'original_professional_fee' => 3500, 'gst_rate' => 18, 'status' => 'approved']);
        $billing = $request->billing()->create(['total_original_professional_fee' => 3500, 'discount_type' => 'none', 'discount_value' => 0, 'discount_amount' => 0, 'net_professional_fee' => 3500, 'gst_rate' => 18, 'gst_amount' => 630, 'government_charges_total' => 0, 'grand_total' => 4130]);
        $billing->history()->create(['request_id' => $request->id, 'changed_by' => null, 'action' => 'frozen', 'pricing_snapshot' => ['grand_total' => 4130]]);

        $state = app(RequestBillingStateResolver::class)->resolve($request->fresh());
        $this->assertFalse($state->legacy);
        $this->assertFalse($state->pricingLocked);
        $this->actingAs(User::factory()->create())->post(route('admin.requests.payments.store', $request), ['amount' => 4130, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('request_payments', 0);
    }

    public function test_late_full_payment_relocks_current_billing_without_rewinding_processing_status(): void
    {
        [$request, $items] = $this->requestWithServices();
        $admin = User::factory()->create();
        $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();
        $request->update(['status' => 'draft_in_progress']);
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), ['amount' => 3486, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();

        $this->assertSame('draft_in_progress', $request->fresh()->status);
        $this->assertTrue($request->fresh()->billing->isLocked());
        $this->assertNotNull($items[0]->fresh()->pricing_locked_at);
    }

    public function test_original_service_snapshot_fee_is_used_when_legacy_professional_fee_is_zero(): void
    {
        $service = $this->service('Snapshot Fee Service', 3500);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/999902', 'service_id' => $service->id, 'name' => 'Snapshot Customer', 'mobile' => '9999999999', 'status' => 'under_review', 'payment_status' => 'not_required', 'amount_due' => 0, 'last_status_changed_at' => now()]);
        $request->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en, 'professional_fee' => 0, 'original_professional_fee' => 3500, 'gst_rate' => 18, 'status' => 'approved']);
        $admin = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();

        $billing = $request->fresh()->billing;
        $this->assertSame(3500.0, (float) $billing->total_original_professional_fee);
        $this->assertSame(350.0, (float) $billing->discount_amount);
        $this->assertSame(3150.0, (float) $billing->net_professional_fee);
        $this->assertSame(567.0, (float) $billing->gst_amount);
        $this->assertSame(300.0, (float) $billing->government_charges_total);
        $this->assertSame(4017.0, (float) $billing->grand_total);
        $this->assertSame(4017.0, (float) $request->fresh()->amount_due);
        $this->assertTrue($billing->isLocked());
    }

    public function test_payment_uses_billing_snapshot_and_rejects_zero_grand_total_for_a_paid_service(): void
    {
        $service = $this->service('Zero Billing Guard', 3500);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/999903', 'file_number' => 'SC/2026/F999903', 'service_id' => $service->id, 'name' => 'Guard Customer', 'mobile' => '9999999999', 'status' => 'payment_pending', 'payment_status' => 'pending', 'amount_due' => 999, 'last_status_changed_at' => now()]);
        $request->requestServices()->create(['service_id' => $service->id, 'professional_fee' => 0, 'original_professional_fee' => 3500, 'gst_rate' => 18, 'status' => 'approved']);
        $request->billing()->create(['total_original_professional_fee' => 0, 'discount_type' => 'none', 'discount_value' => 0, 'discount_amount' => 0, 'net_professional_fee' => 0, 'gst_rate' => 18, 'gst_amount' => 0, 'government_charges_total' => 0, 'grand_total' => 0, 'pricing_locked_at' => now()]);
        $admin = User::factory()->create();
        $message = 'Payment cannot be recorded because the frozen billing Grand Total is zero while an accepted service has a professional fee. Approve and freeze the request billing again.';

        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertOk()->assertSee($message)->assertDontSee('Save Payment Record');
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), ['amount' => 500, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasErrors(['payment' => $message]);

        $this->assertDatabaseCount('request_payments', 0);
        $this->assertSame('999.00', $request->fresh()->amount_due);
    }

    public function test_partial_payment_stays_pending_and_overpayment_is_rejected(): void
    {
        [$request, $items] = $this->requestWithServices();
        $admin = User::factory()->create();
        $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();

        $payment = ['payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()->format('Y-m-d H:i:s')];
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), [...$payment, 'amount' => 1000])->assertSessionHasNoErrors();
        $this->assertSame('partial', $request->fresh()->payment_status);
        $this->assertSame('payment_pending', $request->fresh()->status);
        $this->assertTrue($request->fresh()->billing->isLocked());

        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), [...$payment, 'amount' => 2486.01])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('request_payments', 1);

        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), [...$payment, 'amount' => 2486])->assertSessionHasNoErrors();
        $this->assertSame('received', $request->fresh()->payment_status);
        $this->assertSame('payment_received', $request->fresh()->status);
        $this->assertTrue($request->fresh()->billing->isLocked());
    }

    public function test_tracking_derives_pending_and_paid_from_frozen_billing_and_payment_history(): void
    {
        [$request, $items] = $this->requestWithServices();
        $admin = User::factory()->create();
        $this->approveAll($admin, $request, $items);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), $this->billingPayload())->assertSessionHasNoErrors();

        $request->update(['payment_status' => 'not_required']);
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()->assertSee('Payment Pending')->assertDontSee('Not Required');

        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), ['amount' => 3486, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()->assertSee('ચુકવણી · Payment Status')->assertSee('<em>Paid</em>', false);
    }

    public function test_request_approval_does_not_fake_frozen_billing_and_service_acceptance_is_explicit(): void
    {
        $service = $this->service('Browser Verification Service', 3500);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/777001', 'service_id' => $service->id, 'name' => 'Browser Customer', 'mobile' => '9999999999', 'status' => 'under_review', 'payment_status' => 'not_required', 'amount_due' => 4130, 'last_status_changed_at' => now()]);
        $item = $request->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en, 'professional_fee' => 3500, 'original_professional_fee' => 3500, 'gst_rate' => 18, 'status' => 'received']);
        $admin = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.requests.transition', $request), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertOk()
            ->assertSee('Billing not frozen.')->assertSee('Service decision required.')
            ->assertSee('Billing Pending')->assertDontSee('Grand Total</small><strong>₹4,130.00', false);
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), ['discount_type' => 'none', 'discount_value' => 0, 'gst_rate' => 18])->assertSessionHasErrors('pricing');

        $this->actingAs($admin)->patch(route('admin.requests.services.decision', [$request, $item]), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), ['discount_type' => 'none', 'discount_value' => 0, 'gst_rate' => 18])->assertSessionHasNoErrors();

        $billing = $request->fresh()->billing;
        $this->assertSame(3500.0, (float) $billing->total_original_professional_fee);
        $this->assertSame(630.0, (float) $billing->gst_amount);
        $this->assertSame(4130.0, (float) $billing->grand_total);
        $this->assertTrue($billing->isLocked());
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertSee('Payment Pending');
    }

    private function requestWithServices(): array
    {
        $first = $this->service('First Service', 1000);
        $second = $this->service('Second Service', 2000);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/900001', 'service_id' => $first->id, 'name' => 'Approval Customer', 'mobile' => '9999999999', 'status' => 'under_review', 'payment_status' => 'not_required', 'amount_due' => 0, 'last_status_changed_at' => now()]);
        $items = collect([$first, $second])->map(fn ($service) => $request->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en, 'professional_fee' => $service->service_fee, 'gst_rate' => $service->gst_rate, 'government_charges' => 0, 'status' => 'under_review']))->all();

        return [$request, $items];
    }

    private function approveAll(User $admin, CustomerRequest $request, array $items): void
    {
        foreach ($items as $item) {
            $this->actingAs($admin)->patch(route('admin.requests.services.decision', [$request, $item]), ['decision' => 'approved'])->assertSessionHasNoErrors();
        }
    }

    private function billingPayload(array $overrides = []): array
    {
        return array_replace_recursive(['discount_type' => 'percentage', 'discount_value' => 10, 'discount_reason' => 'regular_customer', 'internal_note' => null, 'gst_rate' => 18, 'government_charges' => [['name' => 'Stamp Duty', 'amount' => 200, 'note' => 'As applicable', 'display_order' => 0], ['name' => 'Registration Fee', 'amount' => 100, 'display_order' => 1]]], $overrides);
    }

    private function service(string $name, float $fee): Service
    {
        return Service::query()->create(['name_en' => $name, 'name_gu' => $name, 'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999), 'service_fee' => $fee, 'gst_rate' => 18, 'estimated_days' => 5, 'is_active' => true]);
    }
}
