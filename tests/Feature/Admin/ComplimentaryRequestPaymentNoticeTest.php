<?php

namespace Tests\Feature\Admin;

use App\Enums\NotificationMilestone;
use App\Mail\CustomerMilestoneMail;
use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkScopeItem;
use App\Services\Notifications\CustomerMessageFactory;
use App\Services\ProcessingChecklistService;
use App\Services\RequestBillingCalculator;
use App\Services\RequestBillingStateResolver;
use Database\Seeders\WorkScopeItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplimentaryRequestPaymentNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorkScopeItemSeeder::class);
    }

    public function test_zero_request_fee_is_reasoned_audited_frozen_and_processable_without_a_payment(): void
    {
        [$request, $row, $service] = $this->requestCase();
        $admin = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $row]), [
            'professional_fee' => -0.01,
            'internal_note' => 'Invalid negative fee',
        ])->assertSessionHasErrors('professional_fee');
        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $row]), [
            'professional_fee' => 0,
        ])->assertSessionHasErrors('internal_note');
        $this->actingAs($admin)->patch(route('admin.requests.services.fee.update', [$request, $row]), [
            'professional_fee' => 0,
            'internal_note' => 'Complimentary Service / નિઃશુલ્ક સેવા',
        ])->assertSessionHasNoErrors();

        $row->refresh();
        $this->assertSame(0.0, $row->billingProfessionalFee());
        $this->assertSame(3500.0, (float) $row->original_professional_fee);
        $this->assertSame(3500.0, (float) $service->fresh()->service_fee);
        $this->assertDatabaseHas('request_service_approval_histories', [
            'request_service_id' => $row->id,
            'request_id' => $request->id,
            'approved_by' => $admin->id,
            'action' => 'fee_updated',
            'note' => 'Complimentary Service / નિઃશુલ્ક સેવા',
        ]);

        $scope = WorkScopeItem::query()->where('name_en', 'Drafting')->sole();
        $this->actingAs($admin)->patch(route('admin.requests.case-planning.save', $request), ['services' => [
            $row->id => ['decision' => 'approved', 'work_scope_ids' => [$scope->id]],
        ]])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.billing.finalize', $request), [
            'discount_type' => 'none', 'discount_value' => 0, 'gst_rate' => 18, 'government_charges' => [],
        ])->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame(0.0, (float) $request->billing->total_original_professional_fee);
        $this->assertSame(0.0, (float) $request->billing->gst_amount);
        $this->assertSame(0.0, (float) $request->billing->grand_total);
        $this->assertSame('not_required', $request->payment_status);
        $this->assertSame('awaiting_staff_assignment', $request->status);
        $this->assertDatabaseCount('request_payments', 0);
        $this->assertDatabaseCount('request_payment_submissions', 0);
        $this->assertDatabaseHas('request_billing_histories', ['request_id' => $request->id, 'action' => 'frozen', 'changed_by' => $admin->id]);
        $this->assertDatabaseCount('customer_notification_events', 1);
        $this->assertDatabaseHas('customer_notification_events', ['request_id' => $request->id, 'milestone' => 'accepted']);
        $this->assertDatabaseMissing('customer_notification_events', ['request_id' => $request->id, 'milestone' => 'payment_pending']);

        $state = app(RequestBillingStateResolver::class)->resolve($request);
        $this->assertSame('frozen_no_payment', $state->lifecycle);
        $this->assertSame(0.0, $state->balanceDue);
        $this->assertFalse($state->paymentRequired);

        $request->update(['assigned_user_id' => $admin->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);
        $eligibility = app(ProcessingChecklistService::class)->eligibility($request->fresh());
        $this->assertTrue($eligibility['eligible'], implode(' ', $eligibility['reasons']));
        $this->assertFalse($eligibility['payment_pending']);

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()->assertSee('No payment is required')->assertDontSee('Submit Payment Details')->assertDontSee('UTR / Transaction ID');

        $message = app(CustomerMessageFactory::class)->make($request, NotificationMilestone::Accepted);
        $html = (new CustomerMilestoneMail($message))->render();
        $this->assertSame('Sai Consulting — Accepted — '.$request->reference_no, $message['subject']);
        $this->assertStringContainsString('No payment is required', $html);
        $this->assertStringContainsString(route('request.track'), $html);
        $this->assertStringNotContainsString('Payment Pending', $html);
        $this->assertStringNotContainsString('UTR', $html);
    }

    public function test_government_charges_and_chargeable_add_ons_remain_payable_with_zero_base_fee(): void
    {
        [$request, $base] = $this->requestCase('SC/2026/COMP002');
        $base->update(['professional_fee' => 0, 'internal_note' => 'Complimentary Service']);
        $base->update(['status' => 'approved']);
        $addOnService = $this->service('Chargeable Add-on', 500);
        $addOn = $request->requestServices()->create([
            'service_id' => $addOnService->id, 'is_admin_added' => true, 'professional_fee' => 500,
            'original_professional_fee' => 500, 'gst_rate' => 18, 'status' => 'approved',
        ]);

        $calculation = app(RequestBillingCalculator::class)->calculate([$base->fresh(), $addOn], 'none', 0, 18, [
            ['name' => 'Government Charge', 'amount' => 1500],
        ]);

        $this->assertSame(500.0, $calculation->totalProfessionalFee);
        $this->assertSame(90.0, $calculation->gstAmount);
        $this->assertSame(1500.0, $calculation->governmentChargesTotal);
        $this->assertSame(2090.0, $calculation->grandTotal);
        $this->assertSame(2090.0, $calculation->balanceDue);
        $this->assertTrue($calculation->paymentRequired);
    }

    public function test_accepted_email_uses_authoritative_remaining_balance_after_confirmed_partial_payment(): void
    {
        [$request, $row] = $this->requestCase('SC/2026/COMP003');
        $row->update(['status' => 'approved']);
        $request->update(['file_number' => 'SC/2026/F-COMP003', 'status' => 'payment_pending', 'payment_status' => 'partial', 'amount_due' => 4130, 'amount_paid' => 1000]);
        $request->billing()->create([
            'total_original_professional_fee' => 3500, 'discount_type' => 'none', 'discount_value' => 0,
            'discount_amount' => 0, 'net_professional_fee' => 3500, 'gst_rate' => 18, 'gst_amount' => 630,
            'government_charges_total' => 0, 'grand_total' => 4130, 'pricing_locked_at' => now(),
        ]);
        $request->payments()->create(['amount' => 1000, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => now()]);

        $message = app(CustomerMessageFactory::class)->make($request->fresh(), NotificationMilestone::Accepted);
        $html = (new CustomerMilestoneMail($message))->render();

        $this->assertSame(3130.0, $message['outstanding_amount']);
        $this->assertSame('Sai Consulting — Accepted — Payment Pending — '.$request->reference_no, $message['subject']);
        $this->assertStringContainsString('Payment Pending', $html);
        $this->assertStringContainsString('₹3,130.00', $html);
        $this->assertStringContainsString('Payment is required before normal processing proceeds.', $html);
        $this->assertStringContainsString(route('request.track'), $html);
    }

    private function requestCase(string $reference = 'SC/2026/COMP001'): array
    {
        $service = $this->service('Base Professional Service', 3500);
        $request = CustomerRequest::query()->create([
            'reference_no' => $reference, 'service_id' => $service->id, 'name' => 'Complimentary Customer',
            'mobile' => '9999999999', 'email' => 'customer@example.test', 'request_origin' => 'online',
            'status' => 'under_review', 'payment_status' => 'not_required', 'amount_due' => 0, 'last_status_changed_at' => now(),
        ]);
        $row = $request->requestServices()->create([
            'service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en,
            'service_name_gu_snapshot' => $service->name_gu, 'professional_fee' => 3500,
            'original_professional_fee' => 3500, 'gst_rate' => 18, 'status' => 'under_review',
        ]);

        return [$request, $row, $service];
    }

    private function service(string $name, float $fee): Service
    {
        return Service::query()->create([
            'name_en' => $name, 'name_gu' => $name, 'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 999999),
            'service_fee' => $fee, 'gst_rate' => 18, 'estimated_days' => 5, 'requires_payment_before_processing' => true,
            'is_active' => true, 'available_online' => true, 'available_offline' => true,
        ]);
    }
}
