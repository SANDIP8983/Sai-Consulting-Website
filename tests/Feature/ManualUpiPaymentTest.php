<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\RequestPaymentSubmission;
use App\Models\Service;
use App\Models\User;
use App\Services\PaymentSettingsService;
use App\Services\PaymentSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualUpiPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_admin_can_configure_upi_settings_and_private_static_qr(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.settings.payments.update'), [
            'enabled' => '1',
            'upi_id' => 'configured@sai-bank',
            'payee_name' => 'Sai Consulting Chanasma',
            'qr_code' => UploadedFile::fake()->image('upi-qr.png'),
            'instructions' => 'Use the exact amount and submit the UTR.',
            'proof_upload_allowed' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', ['setting_key' => 'payments.upi.id', 'setting_value' => 'configured@sai-bank', 'is_public' => false]);
        $this->actingAs($admin)->get(route('admin.settings.payments'))->assertOk()->assertSee('configured@sai-bank');
        $this->get(route('payments.upi-qr'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_upi_section_uses_frozen_total_only_for_eligible_payment_pending_request(): void
    {
        $this->enableUpi();
        $eligible = $this->eligibleRequest(3486);

        $this->post(route('request.track.lookup'), ['reference_no' => $eligible->reference_no, 'mobile' => $eligible->mobile])
            ->assertOk()
            ->assertSee('Pay via UPI')
            ->assertSee('3,486.00')
            ->assertSee('configured@sai-bank')
            ->assertSee(route('request.track.payment-submission', $eligible), false);

        foreach (['payment_received', 'completed', 'closed'] as $index => $status) {
            $ineligible = $this->eligibleRequest(1000, ['reference_no' => 'SC/2026/00'.(910 + $index), 'status' => $status, 'payment_status' => 'received']);
            $this->post(route('request.track.lookup'), ['reference_no' => $ineligible->reference_no, 'mobile' => $ineligible->mobile])
                ->assertOk()->assertDontSee('Submit Payment Details');
        }
    }

    public function test_verified_customer_can_submit_utr_and_optional_private_proof_without_becoming_paid(): void
    {
        $this->enableUpi();
        $request = $this->eligibleRequest(1250);
        $this->verifyTracking($request);

        $this->post(route('request.track.payment-submission', $request), [
            'utr_reference' => 'UTR-123456789',
            'proof' => UploadedFile::fake()->image('payment.jpg'),
            'declaration' => '1',
        ])->assertRedirect(route('request.track'));

        $submission = $request->paymentSubmission()->firstOrFail();
        $this->assertSame('pending', $submission->status);
        $this->assertSame('1250.00', $submission->amount);
        $this->assertStringStartsWith('payment-proofs/'.$request->id.'/', $submission->proof_path);
        $this->assertNotSame('payment.jpg', basename($submission->proof_path));
        Storage::disk('local')->assertExists($submission->proof_path);
        $this->assertSame('pending', $request->fresh()->payment_status);
        $this->assertSame('payment_pending', $request->fresh()->status);
        $this->assertDatabaseCount('request_payments', 0);
    }

    public function test_invalid_and_oversized_payment_proofs_are_rejected(): void
    {
        $this->enableUpi();
        $request = $this->eligibleRequest();
        $this->verifyTracking($request);

        $this->post(route('request.track.payment-submission', $request), [
            'utr_reference' => 'UTR-INVALID-1',
            'proof' => UploadedFile::fake()->create('proof.exe', 10, 'application/x-msdownload'),
            'declaration' => '1',
        ])->assertSessionHasErrors('proof');

        $this->post(route('request.track.payment-submission', $request), [
            'utr_reference' => 'UTR-OVERSIZE-1',
            'proof' => UploadedFile::fake()->create('proof.pdf', PaymentSubmissionService::MAX_PROOF_KB + 1, 'application/pdf'),
            'declaration' => '1',
        ])->assertSessionHasErrors('proof');

        $this->assertDatabaseCount('request_payment_submissions', 0);
    }

    public function test_duplicate_paid_and_cross_customer_submissions_are_blocked(): void
    {
        $this->enableUpi();
        $request = $this->eligibleRequest();
        $this->verifyTracking($request);
        $payload = ['utr_reference' => 'UTR-DUPLICATE-1', 'declaration' => '1'];

        $this->post(route('request.track.payment-submission', $request), $payload)->assertSessionHasNoErrors();
        $this->post(route('request.track.payment-submission', $request), $payload)->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('request_payment_submissions', 1);

        $other = $this->eligibleRequest(500, ['reference_no' => 'SC/2026/000999']);
        $this->post(route('request.track.payment-submission', $other), ['utr_reference' => 'UTR-OTHER-1', 'declaration' => '1'])->assertForbidden();

        $request->update(['status' => 'payment_received', 'payment_status' => 'received']);
        $request->paymentSubmission->update(['status' => 'rejected']);
        $this->post(route('request.track.payment-submission', $request), ['utr_reference' => 'UTR-PAID-1', 'declaration' => '1'])->assertSessionHasErrors('payment');
    }

    public function test_only_authorized_admin_can_download_proof_for_the_matching_request(): void
    {
        $request = $this->eligibleRequest();
        $other = $this->eligibleRequest(500, ['reference_no' => 'SC/2026/000998']);
        $submission = $this->submissionWithProof($request);
        $admin = User::factory()->create();

        $this->get(route('admin.requests.payment-submission.proof', $request))->assertRedirect(route('login'));
        $this->actingAs($admin)->get(route('admin.requests.payment-submission.proof', $other))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.requests.payment-submission.proof', $request))
            ->assertOk()
            ->assertDownload('payment-proof.pdf')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        Storage::disk('local')->assertExists($submission->proof_path);
    }

    public function test_existing_admin_mark_paid_flow_verifies_submission_and_keeps_payment_audit(): void
    {
        $request = $this->eligibleRequest(500);
        $submission = RequestPaymentSubmission::query()->create(['request_id' => $request->id, 'utr_reference' => 'UTR-VERIFY-1', 'amount' => 500, 'status' => 'pending', 'submitted_at' => now()]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), [
            'amount' => 500,
            'payment_status' => 'received',
            'payment_method' => 'upi',
            'transaction_reference' => 'UTR-VERIFY-1',
            'received_at' => '2026-08-17 12:00:00',
        ])->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame('verified', $submission->status);
        $this->assertSame($admin->id, $submission->reviewed_by);
        $this->assertNotNull($submission->payment_id);
        $this->assertDatabaseHas('request_payments', ['request_id' => $request->id, 'payment_status' => 'received', 'transaction_reference' => 'UTR-VERIFY-1']);
        $this->assertSame('received', $request->fresh()->payment_status);
        $this->assertSame('awaiting_staff_assignment', $request->fresh()->status);
    }

    public function test_admin_can_reject_pending_submission_and_customer_can_correct_it(): void
    {
        $this->enableUpi();
        $request = $this->eligibleRequest();
        $submission = RequestPaymentSubmission::query()->create(['request_id' => $request->id, 'utr_reference' => 'UTR-WRONG-1', 'amount' => 500, 'status' => 'pending', 'submitted_at' => now()]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.requests.payment-submission.reject', $request), ['review_note' => 'UTR could not be matched.'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $submission->fresh()->status);

        $this->verifyTracking($request);
        $this->post(route('request.track.payment-submission', $request), ['utr_reference' => 'UTR-CORRECT-2', 'declaration' => '1'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('request_payment_submissions', ['request_id' => $request->id, 'utr_reference' => 'UTR-CORRECT-2', 'status' => 'pending']);
        $this->assertDatabaseCount('request_payment_submissions', 1);
    }

    private function enableUpi(bool $proofAllowed = true): void
    {
        app(PaymentSettingsService::class)->update([
            'enabled' => true,
            'upi_id' => 'configured@sai-bank',
            'payee_name' => 'Sai Consulting',
            'qr_code' => UploadedFile::fake()->image('qr.png'),
            'instructions' => 'Pay the exact amount and submit UTR.',
            'proof_upload_allowed' => $proofAllowed,
        ]);
    }

    private function verifyTracking(CustomerRequest $request): void
    {
        $this->withSession(['public_tracking.verified_requests.'.$request->id => now()->timestamp]);
    }

    private function eligibleRequest(float $total = 500, array $attributes = []): CustomerRequest
    {
        $service = Service::query()->firstOrCreate(['slug' => 'upi-payment-test'], ['name_en' => 'UPI Payment Test', 'name_gu' => 'UPI Payment Test', 'is_active' => true]);
        $reference = $attributes['reference_no'] ?? 'SC/2026/000950';
        $digits = substr(preg_replace('/\D/', '', $reference), -6);
        $request = CustomerRequest::query()->create([
            'reference_no' => $reference,
            'file_number' => $attributes['file_number'] ?? 'SC/2026/F'.$digits,
            'request_origin' => 'online',
            'service_id' => $service->id,
            'name' => 'UPI Customer',
            'mobile' => '9999999999',
            'status' => 'payment_pending',
            'payment_status' => 'pending',
            'amount_due' => $total,
            'amount_paid' => 0,
            ...$attributes,
        ]);
        $request->billing()->create(['total_original_professional_fee' => $total, 'discount_type' => 'none', 'discount_value' => 0, 'discount_amount' => 0, 'net_professional_fee' => $total, 'gst_rate' => 0, 'gst_amount' => 0, 'government_charges_total' => 0, 'grand_total' => $total, 'pricing_locked_at' => now()]);

        return $request;
    }

    private function submissionWithProof(CustomerRequest $request): RequestPaymentSubmission
    {
        $path = 'payment-proofs/'.$request->id.'/opaque-proof.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');

        return RequestPaymentSubmission::query()->create(['request_id' => $request->id, 'utr_reference' => 'UTR-PROOF-1', 'amount' => 500, 'proof_path' => $path, 'proof_original_name' => 'payment-proof.pdf', 'proof_mime_type' => 'application/pdf', 'proof_file_size' => 13, 'status' => 'pending', 'submitted_at' => now()]);
    }
}
