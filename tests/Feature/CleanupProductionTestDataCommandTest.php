<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\FileNumberService;
use App\Services\ReferenceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupProductionTestDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRMATION = 'DELETE-SC-2026-000001-000003';

    public function test_dry_run_reports_targets_and_changes_nothing(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();

        $exit = Artisan::call('sai:cleanup-production-test-data');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('DRY-RUN MODE', $output);
        $this->assertStringContainsString('SC/2026/000001', $output);
        $this->assertStringContainsString('request_billing_government_charges', $output);
        $this->assertStringContainsString('customer_notification_deliveries', $output);
        $this->assertStringContainsString('request_final_document_deliveries', $output);
        $this->assertDatabaseCount('requests', 4);
        $this->assertDatabaseHas('request_documents', ['request_id' => $fixture['targets'][0]->id]);
        $this->assertDatabaseHas('services', ['id' => $fixture['service']->id]);
        $this->assertDatabaseHas('settings', ['setting_key' => 'cleanup.preserve']);
        Storage::disk('local')->assertExists($fixture['target_file']);
        Storage::disk('local')->assertExists($fixture['unrelated_file']);
    }

    public function test_execute_removes_only_target_data_files_and_resets_public_numbering(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $this->app->detectEnvironment(fn (): string => 'production');

        $exit = Artisan::call('sai:cleanup-production-test-data', [
            '--execute' => true,
            '--confirm' => self::CONFIRMATION,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
        foreach (['SC/2026/000001', 'SC/2026/000002', 'SC/2026/000003'] as $reference) {
            $this->assertDatabaseMissing('requests', ['reference_no' => $reference]);
        }
        $this->assertDatabaseHas('requests', ['id' => $fixture['unrelated']->id, 'reference_no' => 'SC/2025/000001']);
        $this->assertDatabaseHas('services', ['id' => $fixture['service']->id]);
        $this->assertDatabaseHas('users', ['id' => $fixture['user']->id]);
        $this->assertDatabaseHas('settings', ['setting_key' => 'cleanup.preserve']);
        $this->assertDatabaseMissing('request_documents', ['request_id' => $fixture['targets'][0]->id]);
        $this->assertDatabaseMissing('customer_notification_events', ['request_id' => $fixture['targets'][0]->id]);
        $this->assertDatabaseMissing('request_final_documents', ['request_id' => $fixture['targets'][0]->id]);
        $this->assertDatabaseMissing('request_payment_submissions', ['request_id' => $fixture['targets'][0]->id]);
        $this->assertDatabaseMissing('jobs', ['id' => $fixture['target_job_id']]);
        $this->assertDatabaseMissing('jobs', ['id' => $fixture['target_final_job_id']]);
        $this->assertDatabaseHas('jobs', ['id' => $fixture['unrelated_job_id']]);
        $this->assertDatabaseMissing('file_number_sequences', ['year' => 2026]);
        Storage::disk('local')->assertMissing($fixture['target_file']);
        Storage::disk('local')->assertMissing($fixture['proof_file']);
        Storage::disk('local')->assertMissing($fixture['final_file']);
        Storage::disk('local')->assertMissing($fixture['payment_proof_file']);
        Storage::disk('local')->assertExists($fixture['unrelated_file']);

        $this->assertSame('SC/2026/000001', app(ReferenceNumberService::class)->generate());
        $real = CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/000001',
            'service_id' => $fixture['service']->id,
            'name' => 'First Real Customer',
            'mobile' => '9999999999',
            'status' => 'approved',
            'created_at' => '2026-08-16 12:00:00',
        ]);
        $this->assertSame('SC/2026/F000001', app(FileNumberService::class)->assign($real));
    }

    public function test_execute_requires_production_and_the_exact_confirmation_token(): void
    {
        Storage::fake('local');
        $this->completeFixture();

        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data', [
            '--execute' => true,
            '--confirm' => self::CONFIRMATION,
        ]));
        $this->assertDatabaseCount('requests', 4);

        $this->app->detectEnvironment(fn (): string => 'production');
        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data', [
            '--execute' => true,
            '--confirm' => 'wrong-token',
        ]));
        $this->assertDatabaseCount('requests', 4);
    }

    public function test_command_refuses_missing_targets_or_an_unrelated_2026_request(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $fixture['targets'][2]->delete();

        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data'));
        $this->assertStringContainsString('Missing: SC/2026/000003', Artisan::output());

        CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/009999',
            'service_id' => $fixture['service']->id,
            'name' => 'Preserve Current Customer',
            'mobile' => '9888888888',
            'status' => 'received',
            'created_at' => '2026-08-16 12:00:00',
        ]);
        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data'));
        $this->assertStringContainsString('Other 2026 request', Artisan::output());
        $this->assertDatabaseHas('requests', ['reference_no' => 'SC/2026/009999']);
    }

    public function test_command_refuses_shared_private_file_paths_and_unaccounted_file_sequence(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        DB::table('request_documents')->insert([
            'request_id' => $fixture['unrelated']->id,
            'file_name' => 'shared.pdf',
            'file_path' => $fixture['target_file'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('file_number_sequences')->where('year', 2026)->update(['last_number' => 4]);

        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data'));
        $output = Artisan::output();
        $this->assertStringContainsString('also referenced by unrelated data', $output);
        $this->assertStringContainsString('not fully accounted for', $output);
        $this->assertDatabaseCount('requests', 4);
        Storage::disk('local')->assertExists($fixture['target_file']);
    }

    private function completeFixture(): array
    {
        $service = Service::query()->create([
            'name_en' => 'Preserved Service',
            'name_gu' => 'Preserved Service',
            'slug' => 'preserved-service',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['role' => 'admin']);
        Setting::query()->create(['setting_key' => 'cleanup.preserve', 'setting_value' => 'yes']);
        $targets = collect([1, 2, 3])->map(fn (int $number): CustomerRequest => CustomerRequest::query()->create([
            'reference_no' => sprintf('SC/2026/%06d', $number),
            'file_number' => sprintf('SC/2026/F%06d', $number),
            'service_id' => $service->id,
            'name' => 'Production Test '.$number,
            'mobile' => '999999999'.$number,
            'status' => 'completed',
            'created_at' => "2026-08-1{$number} 10:00:00",
        ]));
        $unrelated = CustomerRequest::query()->create([
            'reference_no' => 'SC/2025/000001',
            'file_number' => 'SC/2025/F000001',
            'service_id' => $service->id,
            'name' => 'Preserved Customer',
            'mobile' => '9888888888',
            'status' => 'completed',
        ]);
        $unrelated->forceFill(['created_at' => '2025-08-16 10:00:00'])->saveQuietly();
        DB::table('file_number_sequences')->insert(['year' => 2026, 'last_number' => 3, 'created_at' => now(), 'updated_at' => now()]);

        $target = $targets[0];
        $requestServiceId = DB::table('request_services')->insertGetId(['request_id' => $target->id, 'service_id' => $service->id, 'professional_fee' => 1000, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $scopeId = DB::table('request_service_work_scopes')->insertGetId(['request_service_id' => $requestServiceId, 'name_en_snapshot' => 'Test work', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_service_work_scope_histories')->insert(['request_service_work_scope_id' => $scopeId, 'request_id' => $target->id, 'action' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_service_approval_histories')->insert(['request_service_id' => $requestServiceId, 'request_id' => $target->id, 'pricing_snapshot' => '{}', 'action' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $billingId = DB::table('request_billings')->insertGetId(['request_id' => $target->id, 'total_original_professional_fee' => 1000, 'discount_type' => 'none', 'discount_value' => 0, 'discount_amount' => 0, 'net_professional_fee' => 1000, 'gst_rate' => 18, 'gst_amount' => 180, 'government_charges_total' => 100, 'grand_total' => 1280, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_billing_government_charges')->insert(['request_billing_id' => $billingId, 'name' => 'Test charge', 'amount' => 100, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_billing_histories')->insert(['request_billing_id' => $billingId, 'request_id' => $target->id, 'action' => 'frozen', 'pricing_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_payments')->insert(['request_id' => $target->id, 'amount' => 1280, 'payment_status' => 'received', 'payment_method' => 'cash', 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $paymentProofFile = 'payment-proofs/'.$target->id.'/proof.pdf';
        Storage::disk('local')->put($paymentProofFile, '%PDF-payment-proof');
        DB::table('request_payment_submissions')->insert(['request_id' => $target->id, 'utr_reference' => 'UTR-CLEANUP-TEST', 'amount' => 1280, 'proof_path' => $paymentProofFile, 'proof_original_name' => 'payment-proof.pdf', 'proof_mime_type' => 'application/pdf', 'proof_file_size' => 18, 'status' => 'pending', 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_status_histories')->insert(['request_id' => $target->id, 'to_status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_processing_details')->insert(['request_id' => $target->id, 'processing_stage' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_processing_histories')->insert(['request_id' => $target->id, 'to_stage' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_case_action_histories')->insert(['request_id' => $target->id, 'action' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_assignment_histories')->insert(['request_id' => $target->id, 'assigned_user_id' => $user->id, 'assigned_by' => $user->id, 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_contact_change_histories')->insert(['request_id' => $target->id, 'changed_by' => $user->id, 'changed_fields' => '[]', 'masked_old_values' => '[]', 'masked_new_values' => '[]', 'changed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $targetFile = 'customer-requests/'.$target->id.'/test.pdf';
        Storage::disk('local')->put($targetFile, '%PDF-test');
        DB::table('request_documents')->insert(['request_id' => $target->id, 'file_name' => 'test.pdf', 'file_path' => $targetFile, 'file_type' => 'application/pdf', 'created_at' => now(), 'updated_at' => now()]);
        $dispatchId = DB::table('request_dispatches')->insertGetId(['request_id' => $target->id, 'dispatch_status' => 'delivered', 'dispatch_method' => 'courier', 'dispatch_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $proofFile = 'request-dispatch-proofs/'.$dispatchId.'/proof.pdf';
        Storage::disk('local')->put($proofFile, '%PDF-proof');
        DB::table('request_dispatch_proofs')->insert(['request_dispatch_id' => $dispatchId, 'proof_type' => 'delivery_receipt', 'file_name' => 'proof.pdf', 'file_path' => $proofFile, 'mime_type' => 'application/pdf', 'file_size' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_dispatch_histories')->insert(['request_id' => $target->id, 'request_dispatch_id' => $dispatchId, 'action' => 'delivered', 'created_at' => now(), 'updated_at' => now()]);
        $eventId = DB::table('customer_notification_events')->insertGetId(['request_id' => $target->id, 'milestone' => 'request_received', 'event_key' => 'cleanup-test-event', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $deliveryId = DB::table('customer_notification_deliveries')->insertGetId(['notification_event_id' => $eventId, 'channel' => 'email', 'status' => 'skipped', 'template_key' => 'test', 'attempt_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $targetJobId = DB::table('jobs')->insertGetId(['queue' => 'default', 'payload' => 'SendCustomerNotificationJob deliveryId";i:'.$deliveryId.';', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $finalFile = 'customer-requests/'.$target->id.'/final-documents/final.pdf';
        Storage::disk('local')->put($finalFile, '%PDF-final');
        $finalDocumentId = DB::table('request_final_documents')->insertGetId(['request_id' => $target->id, 'original_name' => 'final.pdf', 'storage_path' => $finalFile, 'mime_type' => 'application/pdf', 'file_size' => 10, 'uploaded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $finalDeliveryId = DB::table('request_final_document_deliveries')->insertGetId(['request_id' => $target->id, 'channel' => 'email', 'status' => 'pending', 'recipient_masked' => 't***@example.com', 'recipient_hash' => hash('sha256', 'test@example.com'), 'idempotency_key' => hash('sha256', 'cleanup-final-delivery'), 'initiated_by' => $user->id, 'queued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_final_document_delivery_items')->insert(['delivery_id' => $finalDeliveryId, 'final_document_id' => $finalDocumentId, 'created_at' => now(), 'updated_at' => now()]);
        $targetFinalJobId = DB::table('jobs')->insertGetId(['queue' => 'customer-notifications', 'payload' => 'SendFinalDocumentDeliveryJob deliveryId";i:'.$finalDeliveryId.';', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $unrelatedJobId = DB::table('jobs')->insertGetId(['queue' => 'default', 'payload' => 'UnrelatedJob deliveryId";i:'.$deliveryId.';', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

        $unrelatedFile = 'customer-requests/'.$unrelated->id.'/keep.pdf';
        Storage::disk('local')->put($unrelatedFile, '%PDF-keep');
        DB::table('request_documents')->insert(['request_id' => $unrelated->id, 'file_name' => 'keep.pdf', 'file_path' => $unrelatedFile, 'file_type' => 'application/pdf', 'created_at' => now(), 'updated_at' => now()]);

        return compact('service', 'user', 'targets', 'unrelated', 'targetFile', 'proofFile', 'finalFile', 'unrelatedFile') + [
            'target_file' => $targetFile,
            'proof_file' => $proofFile,
            'final_file' => $finalFile,
            'payment_proof_file' => $paymentProofFile,
            'unrelated_file' => $unrelatedFile,
            'target_job_id' => $targetJobId,
            'target_final_job_id' => $targetFinalJobId,
            'unrelated_job_id' => $unrelatedJobId,
        ];
    }
}
