<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManualPaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_payments(): void
    {
        $request = $this->request(['status' => 'approved', 'file_number' => 'SC/2026/F000001']);
        $this->post(route('admin.requests.payments.store', $request), $this->paymentPayload())->assertRedirect(route('login'));
        $this->patch(route('admin.requests.fee.update', $request), ['final_fee' => 1000])->assertRedirect(route('login'));
    }

    public function test_payment_and_fee_cannot_be_recorded_before_approval_or_file_number(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), $this->paymentPayload())->assertSessionHasErrors('payment');
        $this->actingAs($admin)->patch(route('admin.requests.fee.update', $request), ['final_fee' => 1000])->assertSessionHasErrors('final_fee');
        $this->assertDatabaseCount('request_payments', 0);
    }

    public function test_admin_can_set_fee_and_append_pending_then_received_payment_history(): void
    {
        $admin = User::factory()->create();
        $request = $this->request(['status' => 'approved', 'file_number' => 'SC/2026/F000001']);
        $this->actingAs($admin)->patch(route('admin.requests.fee.update', $request), ['final_fee' => 1250])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), $this->paymentPayload(['payment_status' => 'pending']))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), $this->paymentPayload(['payment_status' => 'received', 'customer_remark' => 'Payment confirmed.']))->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('1250.00', $request->amount_due);
        $this->assertSame($admin->id, $request->fee_updated_by);
        $this->assertSame('payment_received', $request->status);
        $this->assertSame('received', $request->payment_status);
        $this->assertDatabaseCount('request_payments', 2);
    }

    public function test_invalid_status_and_method_are_rejected(): void
    {
        $admin = User::factory()->create();
        $request = $this->request(['status' => 'approved', 'file_number' => 'SC/2026/F000001']);
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), $this->paymentPayload(['payment_status' => 'unknown']))->assertSessionHasErrors('payment_status');
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), $this->paymentPayload(['payment_method' => 'card']))->assertSessionHasErrors('payment_method');
    }

    public function test_cash_is_rejected_for_online_requests_and_hidden_in_admin_form(): void
    {
        $admin = User::factory()->create();
        $request = $this->request(['status' => 'approved', 'file_number' => 'SC/2026/F000001', 'request_origin' => 'online']);
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), $this->paymentPayload(['payment_method' => 'cash']))->assertSessionHasErrors('payment_method');
        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertOk()->assertSee('Online / Public')->assertDontSee('<option value="cash"', false)->assertDontSee('<option value="cheque"', false);
    }

    public function test_service_layer_also_rejects_cash_for_online_requests(): void
    {
        $request = $this->request(['status' => 'approved', 'file_number' => 'SC/2026/F000001', 'request_origin' => 'online']);

        $this->expectException(ValidationException::class);
        app(RequestWorkflowService::class)->recordPayment($request, $this->paymentPayload(['payment_method' => 'cash']), User::factory()->create());
    }

    public function test_cash_is_allowed_only_for_an_offline_request(): void
    {
        $admin = User::factory()->create();
        $request = $this->request(['status' => 'approved', 'file_number' => 'SC/2026/F000001', 'request_origin' => 'offline']);
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), $this->paymentPayload(['payment_method' => 'cash']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('request_payments', ['request_id' => $request->id, 'payment_method' => 'cash']);
    }

    public function test_public_tracking_exposes_only_safe_received_payment_information(): void
    {
        $admin = User::factory()->create(['name' => 'Private Admin']);
        $request = $this->request(['status' => 'payment_received', 'file_number' => 'SC/2026/F000001', 'amount_due' => 1500, 'payment_status' => 'received']);
        $request->payments()->create(['amount' => 1500, 'payment_status' => 'received', 'payment_method' => 'upi', 'transaction_reference' => 'SECRET-TXN-123', 'received_at' => '2026-08-01 12:00:00', 'received_by' => $admin->id, 'notes' => 'Private internal payment note.', 'customer_remark' => 'Your payment is confirmed.']);

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()
            ->assertSee('1,500.00')->assertSee('Upi')->assertSee('01 Aug 2026')->assertSee('Your payment is confirmed.')
            ->assertDontSee('SECRET-TXN-123')->assertDontSee('Private internal payment note.')->assertDontSee('Private Admin');
    }

    private function paymentPayload(array $attributes = []): array
    {
        return ['amount' => 500, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => '2026-08-01 11:00:00', ...$attributes];
    }

    private function request(array $attributes = []): CustomerRequest
    {
        $service = Service::query()->firstOrCreate(['slug' => 'payment-test'], ['name_en' => 'Payment Test', 'name_gu' => 'ચુકવણી ટેસ્ટ', 'is_active' => true, 'sort_order' => 1]);
        return CustomerRequest::query()->create(['reference_no' => 'SC/2026/000901', 'request_origin' => 'online', 'service_id' => $service->id, 'name' => 'Payment Customer', 'mobile' => '9999999999', 'address' => 'Patan, Gujarat', 'status' => 'received', 'payment_status' => 'not_required', 'last_status_changed_at' => now(), ...$attributes]);
    }
}
