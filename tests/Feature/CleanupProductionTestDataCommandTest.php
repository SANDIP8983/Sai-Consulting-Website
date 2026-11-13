<?php

namespace Tests\Feature;

use App\Jobs\SendCustomerNotificationJob;
use App\Jobs\SendFinalDocumentDeliveryJob;
use App\Mail\AdminNewCustomerRequestMail;
use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\CleanupInventory;
use App\Services\CleanupQueueInspector;
use App\Services\CleanupSafety;
use App\Services\FileNumberService;
use App\Services\ReferenceNumberService;
use Database\Seeders\NotificationSettingsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Queue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CleanupProductionTestDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.cleanup-tests-do-not-load');
        $app->make(Kernel::class)->bootstrap();
        // Before RefreshDatabase can migrate anything, remove every real connection.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections', ['sqlite' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database.connection', 'sqlite');
        $app['config']->set('queue.connections.database.table', 'jobs');
        $app['config']->set('queue.failed', ['driver' => 'database-uuids', 'database' => 'sqlite', 'table' => 'failed_jobs']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('mail.default', 'array');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00'));
        Http::preventStrayRequests();
        Mail::fake();
        $maintenance = \Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturn(true)->byDefault();
        $this->app->instance(MaintenanceMode::class, $maintenance);
    }

    private function fingerprint(): string
    {
        $exit = Artisan::call('sai:cleanup-all-test-requests');
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);
        $this->assertSame(1, preg_match('/Approval fingerprint: ([a-f0-9]{64})/', $output, $matches), $output);

        return $matches[1];
    }

    private function executeCleanup(): int
    {
        $fingerprint = $this->fingerprint();
        $this->app->detectEnvironment(fn () => 'production');

        return Artisan::call('sai:cleanup-all-test-requests', [
            '--execute' => true, '--confirm' => self::CONFIRMATION,
            '--approve' => $fingerprint, '--workers-stopped' => true, '--scheduler-stopped' => true,
        ]);
    }

    private function payload(object $job): string
    {
        return (new \ReflectionMethod(Queue::class, 'createPayload'))
            ->invoke(app('queue')->connection('database'), $job, 'customer-notifications');
    }

    private const CONFIRMATION = 'DELETE-ALL-INVENTORIED-TEST-REQUESTS';

    public function test_dry_run_reports_targets_and_changes_nothing(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();

        $exit = Artisan::call('sai:cleanup-all-test-requests');
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
        Storage::disk('local')->assertExists($fixture['historical_file']);
    }

    public function test_execute_removes_all_inventoried_requests_and_resets_numbering(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $this->app->detectEnvironment(fn (): string => 'production');

        $exit = $this->executeCleanup();

        $this->assertSame(0, $exit, Artisan::output());
        foreach (['SC/2026/000001', 'SC/2026/000002', 'SC/2026/000003'] as $reference) {
            $this->assertDatabaseMissing('requests', ['reference_no' => $reference]);
        }
        $this->assertDatabaseMissing('requests', ['id' => $fixture['historical']->id]);
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
        Storage::disk('local')->assertMissing($fixture['historical_file']);

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

        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', [
            '--execute' => true,
            '--confirm' => self::CONFIRMATION,
        ]));
        $this->assertDatabaseCount('requests', 4);

        $this->app->detectEnvironment(fn (): string => 'production');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', [
            '--execute' => true,
            '--confirm' => 'wrong-token',
        ]));
        $this->assertDatabaseCount('requests', 4);
    }

    public function test_all_references_are_discovered_even_with_gaps_and_missing_old_targets(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $fixture['targets'][2]->delete();
        CustomerRequest::query()->create(['reference_no' => 'SC/2024/009999', 'service_id' => $fixture['service']->id,
            'name' => 'Older test', 'mobile' => '9888888888', 'created_at' => '2024-08-16 12:00:00']);
        $this->assertSame(0, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertStringContainsString('SC/2024/009999', Artisan::output());
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_shared_files_block_cleanup_even_when_counter_has_gaps(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        DB::table('request_documents')->insert([
            'request_id' => $fixture['historical']->id,
            'file_name' => 'shared.pdf',
            'file_path' => $fixture['target_file'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('file_number_sequences')->where('year', 2026)->update(['last_number' => 4]);

        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $output = Artisan::output();
        $this->assertStringContainsString('Shared file', $output);
        $this->assertDatabaseCount('requests', 4);
        Storage::disk('local')->assertExists($fixture['target_file']);
    }

    public function test_dry_run_does_not_write_journal_or_change_rows_or_files(): void
    {
        Storage::fake('local');
        $this->completeFixture();
        $before = $this->snapshot();
        $files = Storage::disk('local')->allFiles();
        $this->fingerprint();
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($files, Storage::disk('local')->allFiles());
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
    }

    public function test_admin_mail_and_failed_jobs_are_scoped_and_revenue_data_is_removed(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $target = $fixture['targets'][0];
        $payload = $this->payload(new SendQueuedMailable(new AdminNewCustomerRequestMail($target)));
        $job = DB::table('jobs')->insertGetId(['queue' => 'customer-notifications', 'payload' => $payload, 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $failed = DB::table('failed_jobs')->insertGetId(['uuid' => 'cleanup-test-failed', 'connection' => 'database', 'queue' => 'customer-notifications', 'payload' => $payload, 'exception' => 'test']);
        $keepPayload = $this->payload(new SendQueuedMailable(new AdminNewCustomerRequestMail($fixture['historical'])));
        $keep = DB::table('jobs')->insertGetId(['queue' => 'customer-notifications', 'payload' => $keepPayload, 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $entry = DB::table('request_revenue_entries')->insertGetId(['request_id' => $target->id, 'status' => 'certified']);
        $path = "customer-requests/{$target->id}/revenue-entries/$entry/final.pdf";
        Storage::disk('local')->put($path, '%PDF-revenue');
        DB::table('request_revenue_entry_documents')->insert(['request_revenue_entry_id' => $entry, 'document_type' => 'other', 'original_name' => 'final.pdf', 'file_path' => $path, 'mime_type' => 'application/pdf', 'file_size' => 12]);
        DB::table('request_revenue_entry_histories')->insert(['request_id' => $target->id, 'request_revenue_entry_id' => $entry, 'action' => 'created']);
        $settings = DB::table('settings')->orderBy('id')->get()->toJson();
        $users = DB::table('users')->orderBy('id')->get()->toJson();
        $services = DB::table('services')->orderBy('id')->get()->toJson();
        $preserved = $this->preservedSnapshot();
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        foreach (CleanupSafety::TABLES as $table) {
            if (! in_array($table, ['requests', 'request_documents'], true)) {
                $this->assertDatabaseCount($table, 0);
            }
        }
        $this->assertDatabaseMissing('jobs', ['id' => $job]);
        $this->assertDatabaseMissing('failed_jobs', ['id' => $failed]);
        $this->assertDatabaseMissing('jobs', ['id' => $keep]);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame($settings, DB::table('settings')->orderBy('id')->get()->toJson());
        $this->assertSame($users, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertSame($services, DB::table('services')->orderBy('id')->get()->toJson());
        $this->assertSame($preserved, $this->preservedSnapshot());
        $this->assertDatabaseHas('file_number_sequences', ['year' => 2025, 'last_number' => 1]);
    }

    public function test_safe_missing_reference_is_reported_and_empty_cleanup_is_idempotent(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $fixture['targets'][2]->delete();
        DB::table('file_number_sequences')->where('year', 2026)->update(['last_number' => 2]);
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertSame('SC/2026/000001', app(ReferenceNumberService::class)->generate());
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_partial_file_failure_resumes_from_signed_journal(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $safety = new class extends CleanupSafety
        {
            public bool $fail = true;

            public function deleteFile(string $path): bool
            {
                if ($this->fail) {
                    $this->fail = false;

                    return false;
                }

                return parent::deleteFile($path);
            }
        };
        $this->app->instance(CleanupSafety::class, $safety);
        $this->assertSame(1, $this->executeCleanup());
        $this->assertStringContainsString('Database cleanup committed', Artisan::output());
        $this->assertDatabaseCount('requests', 0);
        $this->assertFalse($safety->readJournal()['completed']);
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertTrue($safety->readJournal()['completed']);
        Storage::disk('local')->assertMissing($fixture['historical_file']);
    }

    public function test_database_failure_rolls_back_and_keeps_files_for_retry(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $before = $this->snapshot();
        DB::unprepared("CREATE TRIGGER refuse_cleanup BEFORE DELETE ON requests BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
        $this->assertSame(1, $this->executeCleanup());
        $this->assertSame($before, $this->snapshot());
        Storage::disk('local')->assertExists($fixture['target_file']);
        DB::unprepared('DROP TRIGGER refuse_cleanup');
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
    }

    public function test_journal_failure_prevents_database_deletion(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $this->app->instance(CleanupSafety::class, new class extends CleanupSafety
        {
            public function writeJournal(array $journal): void
            {
                throw new \RuntimeException('Simulated journal failure');
            }
        });
        $this->assertSame(1, $this->executeCleanup());
        $this->assertDatabaseCount('requests', 4);
        Storage::disk('local')->assertExists($fixture['target_file']);
    }

    public function test_stale_approval_and_missing_worker_attestation_are_rejected(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $fingerprint = $this->fingerprint();
        $fixture['targets'][0]->update(['name' => 'Changed customer']);
        $this->app->detectEnvironment(fn () => 'production');
        $options = ['--execute' => true, '--confirm' => self::CONFIRMATION, '--approve' => $fingerprint, '--workers-stopped' => true, '--scheduler-stopped' => true];
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', $options));
        $options['--approve'] = $this->fingerprint();
        $options['--workers-stopped'] = false;
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', $options));
        $this->assertDatabaseCount('requests', 4);
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
    }

    public function test_new_real_request_reusing_reference_is_never_cleaned_again(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $this->assertSame(0, $this->executeCleanup());
        $real = CustomerRequest::query()->create(['reference_no' => app(ReferenceNumberService::class)->generate(), 'service_id' => $fixture['service']->id, 'name' => 'Real customer', 'mobile' => '9999999999']);
        $this->assertGreaterThan($fixture['historical']->id, $real->id);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertDatabaseHas('requests', ['id' => $real->id]);
    }

    public function test_unsafe_shared_and_settings_paths_are_refused(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $document = DB::table('request_documents')->where('request_id', $fixture['targets'][0]->id);
        $document->update(['file_path' => 'settings/branding/logo.png']);
        Storage::disk('local')->put('settings/branding/logo.png', 'preserve');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $document->update(['file_path' => $fixture['target_file']]);
        DB::table('request_documents')->where('request_id', $fixture['historical']->id)->update(['file_path' => $fixture['target_file']]);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        DB::table('request_documents')->where('request_id', $fixture['historical']->id)->update(['file_path' => $fixture['historical_file']]);
        Setting::query()->create(['setting_key' => 'preserve.file', 'setting_value' => $fixture['target_file']]);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        Storage::disk('local')->assertExists('settings/branding/logo.png');
    }

    public function test_cross_request_dependency_is_refused(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $service = DB::table('request_services')->where('request_id', $fixture['targets'][0]->id)->value('id');
        DB::table('request_service_approval_histories')->where('request_service_id', $service)->update(['request_id' => $fixture['historical']->id]);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertStringContainsString('Cross-request', Artisan::output());
    }

    public function test_unexpected_transitive_foreign_key_blocks_cleanup(): void
    {
        Storage::fake('local');
        $this->completeFixture();
        Schema::create('unexpected_delivery_audit', function ($table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('request_final_document_deliveries')->cascadeOnDelete();
        });
        try {
            $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
            $this->assertStringContainsString('Unexpected dependency', Artisan::output());
        } finally {
            Schema::drop('unexpected_delivery_audit');
        }
    }

    public function test_unsupported_queue_reserved_jobs_and_invalid_payloads_block_cleanup(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        config(['queue.default' => 'redis']);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        config(['queue.default' => 'database']);
        DB::table('jobs')->where('id', $fixture['target_job_id'])->update(['reserved_at' => now()->timestamp]);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        DB::table('jobs')->where('id', $fixture['target_job_id'])->update(['reserved_at' => null, 'payload' => 'invalid']);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertDatabaseCount('requests', 4);
    }

    public function test_other_year_is_refused_and_reference_clock_is_consistent(): void
    {
        Storage::fake('local');
        $this->completeFixture();
        $this->travelTo(Carbon::parse('2027-01-01'));
        $this->assertSame('SC/2027/000001', app(ReferenceNumberService::class)->generate());
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
    }

    public function test_untracked_private_uploads_are_counted_and_removed_only_for_target_ids(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $orphan = 'customer-requests/'.$fixture['targets'][0]->id.'/orphan.pdf';
        Storage::disk('local')->put($orphan, '%PDF-orphan');
        Storage::disk('local')->put('settings/payments/keep.png', 'preserved');
        $this->assertSame(0, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertStringContainsString('Private files recorded: 6', Artisan::output());
        Storage::disk('local')->assertExists($orphan);
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        Storage::disk('local')->assertMissing($orphan);
        Storage::disk('local')->assertExists('settings/payments/keep.png');
        Storage::disk('local')->assertMissing($fixture['historical_file']);
    }

    public function test_missing_files_and_all_missing_targets_are_safe_no_ops(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        Storage::disk('local')->delete($fixture['target_file']);
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertSame(0, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertStringContainsString('Target requests: 0', Artisan::output());
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
    }

    public function test_empty_database_with_zero_sequence_can_be_cleaned(): void
    {
        Storage::fake('local');
        DB::table('file_number_sequences')->insert(['year' => 2026, 'last_number' => 0]);
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertSame('SC/2026/000001', app(ReferenceNumberService::class)->generate());
    }

    public function test_tampered_journal_is_never_trusted(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        Storage::disk('local')->put(CleanupSafety::JOURNAL, '{"payload":"{}","mac":"invalid"}');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertDatabaseCount('requests', 4);
        Storage::disk('local')->assertExists($fixture['target_file']);
    }

    public function test_changed_recovery_file_is_not_deleted(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $safety = new class extends CleanupSafety
        {
            public function deleteFile(string $path): bool
            {
                return false;
            }
        };
        $this->app->instance(CleanupSafety::class, $safety);
        $this->assertSame(1, $this->executeCleanup());
        Storage::disk('local')->put($fixture['target_file'], 'replacement content');
        $this->app->instance(CleanupSafety::class, new CleanupSafety);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertSame('replacement content', Storage::disk('local')->get($fixture['target_file']));
    }

    public function test_maintenance_is_required_even_with_valid_approval(): void
    {
        Storage::fake('local');
        $this->completeFixture();
        $maintenance = \Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturn(false);
        $this->app->instance(MaintenanceMode::class, $maintenance);
        $this->assertSame(1, $this->executeCleanup());
        $this->assertStringContainsString('maintenance mode', Artisan::output());
        $this->assertDatabaseCount('requests', 4);
    }

    public function test_unknown_request_table_without_foreign_key_is_refused(): void
    {
        Storage::fake('local');
        $this->completeFixture();
        Schema::create('unexpected_request_metadata', function ($table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
        });
        try {
            $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        } finally {
            Schema::drop('unexpected_request_metadata');
        }
    }

    public function test_path_traversal_is_refused_before_any_deletion(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        DB::table('request_documents')->where('request_id', $fixture['targets'][0]->id)->update(['file_path' => 'customer-requests/'.$fixture['targets'][0]->id.'/../../settings/payments/keep.png']);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertDatabaseCount('requests', 4);
    }

    public function test_multiple_years_more_than_three_requests_and_unrelated_counters(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        foreach ([2023, 2024, 2027] as $year) {
            CustomerRequest::query()->create(['reference_no' => "SC/$year/000099", 'file_number' => "SC/$year/F000099",
                'service_id' => $fixture['service']->id, 'name' => 'Test', 'mobile' => '9999999999', 'created_at' => "$year-01-01 00:00:00"]);
            DB::table('file_number_sequences')->insert(['year' => $year, 'last_number' => 99]);
        }
        DB::table('file_number_sequences')->insert(['year' => 2040, 'last_number' => 50]);
        $before = $this->preservedSnapshot();
        $this->assertSame(0, Artisan::call('sai:cleanup-all-test-requests'));
        $output = Artisan::output();
        $this->assertStringContainsString('Target requests: 7', $output);
        foreach ([2023, 2024, 2027] as $year) {
            $this->assertStringContainsString("SC/$year/000099", $output);
        }
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertDatabaseCount('requests', 0);
        $this->assertDatabaseCount('file_number_sequences', 5);
        $this->assertDatabaseHas('file_number_sequences', ['year' => 2040, 'last_number' => 50]);
        $this->assertSame($before, $this->preservedSnapshot());
        $this->assertSame('SC/2026/000001', app(ReferenceNumberService::class)->generate());
    }

    public function test_approval_fingerprint_is_stable_but_new_requests_invalidate_it(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $approved = $this->fingerprint();
        $this->assertSame($approved, $this->fingerprint());
        $new = CustomerRequest::query()->create(['reference_no' => 'SC/2022/000777', 'service_id' => $fixture['service']->id, 'name' => 'Newly arrived', 'mobile' => '9999999999']);
        $this->assertNotSame($approved, $this->fingerprint());
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', $this->executionOptions($approved)));
        $this->assertDatabaseCount('requests', 5);
        $this->assertDatabaseHas('requests', ['id' => $new->id]);
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
    }

    public function test_new_request_arriving_during_lock_acquisition_is_not_included(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $approved = $this->fingerprint();
        $inventory = new class(app(CleanupSafety::class), app(CleanupQueueInspector::class)) extends CleanupInventory
        {
            public int $calls = 0;

            public int $serviceId;

            public function inspect(): array
            {
                if (++$this->calls === 2) {
                    DB::table('requests')->insert(['reference_no' => 'SC/2026/000555', 'service_id' => $this->serviceId, 'name' => 'Concurrent test', 'mobile' => '9999999999', 'created_at' => now(), 'updated_at' => now()]);
                }

                return parent::inspect();
            }
        };
        $inventory->serviceId = $fixture['service']->id;
        $this->app->instance(CleanupInventory::class, $inventory);
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', $this->executionOptions($approved)));
        $this->assertDatabaseCount('requests', 5);
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
    }

    public function test_change_after_journal_creation_prevents_deletion(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $safety = new class extends CleanupSafety
        {
            public function writeJournal(array $journal): void
            {
                parent::writeJournal($journal);
                DB::table('requests')->where('reference_no', 'SC/2026/000001')->update(['name' => 'Changed after approval']);
            }
        };
        $this->app->instance(CleanupSafety::class, $safety);
        $this->assertSame(1, $this->executeCleanup());
        $this->assertDatabaseCount('requests', 4);
        Storage::disk('local')->assertExists($fixture['target_file']);
    }

    public function test_appointment_notifications_jobs_and_history_are_preserved(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $appointmentId = DB::table('appointments')->insertGetId(['reference_no' => 'APT/2026/000001', 'customer_name' => 'Appointment', 'mobile' => '9999999999', 'service_id' => $fixture['service']->id, 'scheduled_at' => now()]);
        $eventId = DB::table('customer_notification_events')->insertGetId(['appointment_id' => $appointmentId, 'source_type' => 'appointment', 'source_id' => $appointmentId, 'milestone' => 'appointment_confirmed', 'event_key' => 'preserve-appointment', 'occurred_at' => now()]);
        $deliveryId = DB::table('customer_notification_deliveries')->insertGetId(['notification_event_id' => $eventId, 'channel' => 'email', 'status' => 'pending', 'template_key' => 'appointment']);
        DB::table('appointment_histories')->insert(['appointment_id' => $appointmentId, 'action' => 'created']);
        $jobId = DB::table('jobs')->insertGetId(['queue' => 'default', 'payload' => $this->payload(new SendCustomerNotificationJob($deliveryId)), 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $before = $this->preservedSnapshot();
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertDatabaseHas('customer_notification_events', ['id' => $eventId]);
        $this->assertDatabaseHas('customer_notification_deliveries', ['id' => $deliveryId]);
        $this->assertDatabaseHas('jobs', ['id' => $jobId]);
        $this->assertSame($before, $this->preservedSnapshot());
    }

    public function test_complete_dry_run_reports_independent_blockers_and_files(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        Storage::disk('local')->put('customer-requests/99999/orphan.pdf', 'unknown owner');
        Setting::query()->create(['setting_key' => 'shared.asset', 'setting_value' => $fixture['target_file']]);
        DB::table('jobs')->where('id', $fixture['target_job_id'])->update(['payload' => 'uninspectable']);
        Schema::create('unknown_polymorphic_history', function ($table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
        });
        try {
            $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
            $output = Artisan::output();
            foreach (['SC/2025/000001', 'SC/2026/000003', 'Shared file', 'Orphan file', 'Uninspectable queue', 'Unreviewed polymorphic', 'request_payments', 'Private files recorded'] as $text) {
                $this->assertStringContainsString($text, $output);
            }
            $this->assertDatabaseCount('requests', 4);
            Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
        } finally {
            Schema::drop('unknown_polymorphic_history');
        }
    }

    public function test_polymorphic_source_mismatch_and_unknown_source_are_blockers(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        DB::table('customer_notification_events')->update(['source_type' => 'request', 'source_id' => $fixture['historical']->id]);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertStringContainsString('cross-owner notification source', Artisan::output());
        DB::table('customer_notification_events')->update(['source_type' => 'new_unknown_source']);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
    }

    public function test_legacy_journal_requires_explicit_reconciliation(): void
    {
        Storage::fake('local');
        $this->completeFixture();
        Storage::disk('local')->put('maintenance/test-data-cleanup-2026.json', '{}');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertStringContainsString('Legacy cleanup journal', Artisan::output());
    }

    public function test_scheduler_attestation_is_required(): void
    {
        Storage::fake('local');
        $this->completeFixture();
        $options = $this->executionOptions($this->fingerprint());
        unset($options['--scheduler-stopped']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', $options));
        $this->assertDatabaseCount('requests', 4);
    }

    private function executionOptions(string $fingerprint): array
    {
        return ['--execute' => true, '--confirm' => self::CONFIRMATION, '--approve' => $fingerprint,
            '--workers-stopped' => true, '--scheduler-stopped' => true];
    }

    public function test_known_polymorphic_sources_are_inventoried(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $requestId = $fixture['targets'][0]->id;
        $entry = DB::table('request_revenue_entries')->insertGetId(['request_id' => $requestId, 'status' => 'not_started']);
        foreach (['request' => $requestId, 'request_status' => $requestId,
            'billing' => DB::table('request_billings')->value('id'), 'payment' => DB::table('request_payments')->value('id'),
            'processing_stage' => DB::table('request_processing_details')->value('id'), 'revenue_entry' => $entry] as $type => $id) {
            DB::table('customer_notification_events')->update(['source_type' => $type, 'source_id' => $id]);
            $this->fingerprint();
        }
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertDatabaseCount('request_revenue_entries', 0);
        $this->assertDatabaseCount('customer_notification_events', 0);
    }

    public function test_new_file_during_recovery_is_never_silently_included(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $this->app->instance(CleanupSafety::class, new class extends CleanupSafety
        {
            public function deleteFile(string $path): bool
            {
                return false;
            }
        });
        $this->assertSame(1, $this->executeCleanup());
        $newPath = 'customer-requests/'.$fixture['targets'][0]->id.'/new-unapproved.pdf';
        Storage::disk('local')->put($newPath, 'not approved');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertStringContainsString('New file appeared during recovery', Artisan::output());
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_orphan_notification_and_orphan_job_are_reported(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        DB::table('customer_notification_events')->insert(['milestone' => 'request_received', 'event_key' => 'orphan-event', 'occurred_at' => now()]);
        DB::table('jobs')->where('id', $fixture['target_job_id'])->update(['payload' => $this->payload(new SendCustomerNotificationJob(99999))]);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $output = Artisan::output();
        $this->assertStringContainsString('Orphan notification event', $output);
        $this->assertStringContainsString('Orphan notification job', $output);
    }

    public function test_chunked_ids_support_larger_request_inventory(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $rows = [];
        for ($number = 1; $number <= 410; $number++) {
            $rows[] = ['reference_no' => sprintf('SC/2024/%06d', $number), 'service_id' => $fixture['service']->id,
                'name' => 'Disposable test', 'mobile' => '9999999999', 'created_at' => '2024-01-01 00:00:00'];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('requests')->insert($chunk);
        }
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertDatabaseCount('requests', 0);
        $this->assertSame('SC/2026/000001', app(ReferenceNumberService::class)->generate());
    }

    public function test_legacy_reference_with_missing_timestamp_is_explicitly_inventoried(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        DB::table('requests')->where('id', $fixture['targets'][0]->id)->update(['reference_no' => 'LEGACY-TEST', 'created_at' => null]);
        $this->assertSame(0, Artisan::call('sai:cleanup-all-test-requests'));
        $output = Artisan::output();
        $this->assertStringContainsString('LEGACY-TEST', $output);
        $this->assertStringContainsString('unknown', $output);
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
    }

    public function test_private_paths_and_document_names_are_never_reported(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $this->assertSame(0, Artisan::call('sai:cleanup-all-test-requests'));
        $output = Artisan::output();
        $this->assertStringNotContainsString($fixture['target_file'], $output);
        $this->assertStringNotContainsString('test.pdf', $output);
        $this->assertStringContainsString(hash('sha256', $fixture['target_file']), $output);
    }

    public function test_original_command_remains_limited_and_refuses_additional_requests(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        CustomerRequest::query()->create(['reference_no' => 'SC/2026/000019',
            'service_id' => $fixture['service']->id, 'name' => 'Extra test', 'mobile' => '9999999999']);
        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data'));
        $output = Artisan::output();
        $this->assertStringContainsString('must be preserved', $output);
        $this->assertStringNotContainsString($fixture['target_file'], $output);
        $this->assertDatabaseCount('requests', 5);
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
    }

    public function test_unknown_numbering_storage_blocks_cleanup(): void
    {
        Storage::fake('local');
        $this->completeFixture();
        Schema::create('external_number_archive', function ($table): void {
            $table->id();
            $table->string('reference_no');
        });
        try {
            $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
            $this->assertStringContainsString('Unreviewed business numbering storage', Artisan::output());
            $this->assertDatabaseCount('requests', 4);
        } finally {
            Schema::drop('external_number_archive');
        }
    }

    public function test_schema_change_during_recovery_requires_manual_review(): void
    {
        Storage::fake('local');
        $this->completeFixture();
        $this->app->instance(CleanupSafety::class, new class extends CleanupSafety
        {
            public function deleteFile(string $path): bool
            {
                return false;
            }
        });
        $this->assertSame(1, $this->executeCleanup());
        Schema::create('new_unrelated_table', fn ($table) => $table->id());
        try {
            $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
            $this->assertStringContainsString('Schema differs from the signed recovery journal', Artisan::output());
        } finally {
            Schema::drop('new_unrelated_table');
        }
    }

    public function test_counter_mismatch_blocks_but_consumed_number_gaps_are_allowed(): void
    {
        Storage::fake('local');
        $this->completeFixture();
        DB::table('file_number_sequences')->where('year', 2026)->update(['last_number' => 2]);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertStringContainsString('File counter is below an assigned number', Artisan::output());
        DB::table('file_number_sequences')->where('year', 2026)->update(['last_number' => 15]);
        $this->assertSame(0, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertDatabaseCount('requests', 4);
    }

    #[TestWith(['jobs', true])]
    #[TestWith(['jobs', false])]
    #[TestWith(['failed_jobs', true])]
    #[TestWith(['failed_jobs', false])]
    public function test_chained_jobs_block_cleanup_in_both_ownership_directions(string $table, bool $targetHead): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $targetDelivery = (int) DB::table('customer_notification_deliveries')->value('id');
        $appointment = DB::table('appointments')->insertGetId(['reference_no' => 'APT/2026/000010',
            'customer_name' => 'Preserved appointment', 'mobile' => '9999999999',
            'service_id' => $fixture['service']->id, 'scheduled_at' => now()]);
        $event = DB::table('customer_notification_events')->insertGetId(['appointment_id' => $appointment,
            'source_type' => 'appointment', 'source_id' => $appointment, 'milestone' => 'appointment_confirmed',
            'event_key' => 'preserved-chain-event', 'occurred_at' => now()]);
        $preservedDelivery = DB::table('customer_notification_deliveries')->insertGetId([
            'notification_event_id' => $event, 'channel' => 'email', 'status' => 'pending', 'template_key' => 'appointment']);
        $job = new SendCustomerNotificationJob($targetHead ? $targetDelivery : $preservedDelivery);
        $job->chain([new SendCustomerNotificationJob($targetHead ? $preservedDelivery : $targetDelivery)]);
        $payload = $this->payload($job);
        if ($table === 'jobs') {
            DB::table($table)->where('id', $fixture['target_job_id'])->update(['payload' => $payload]);
        } else {
            DB::table($table)->insert(['uuid' => 'chain-test', 'connection' => 'database',
                'queue' => 'default', 'payload' => $payload, 'exception' => 'test']);
        }
        $before = $this->snapshot();
        $preserved = $this->preservedSnapshot();
        $files = Storage::disk('local')->allFiles();
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertStringContainsString('Chained queue work requires explicit review', Artisan::output());
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', $this->executionOptions(str_repeat('0', 64))));
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($preserved, $this->preservedSnapshot());
        $this->assertSame($files, Storage::disk('local')->allFiles());
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
    }

    public function test_legacy_command_successfully_cleans_only_three_targets_and_preserves_populated_business_data(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $this->populatePreservedBusinessData();
        $targetMail = $this->payload(new SendQueuedMailable(new AdminNewCustomerRequestMail($fixture['targets'][0])));
        $adminJob = DB::table('jobs')->insertGetId(['queue' => 'default', 'payload' => $targetMail,
            'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $adminFailure = DB::table('failed_jobs')->insertGetId(['uuid' => 'legacy-admin-failure',
            'connection' => 'database', 'queue' => 'default', 'payload' => $targetMail, 'exception' => 'test']);
        $historicalJob = DB::table('jobs')->insertGetId(['queue' => 'default',
            'payload' => $this->payload(new SendQueuedMailable(new AdminNewCustomerRequestMail($fixture['historical']))),
            'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $preserved = $this->preservedSnapshot();
        $before = $this->snapshot();
        $this->assertSame(0, Artisan::call('sai:cleanup-production-test-data'), Artisan::output());
        $this->assertSame($before, $this->snapshot());
        Storage::disk('local')->assertExists($fixture['target_file']);
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame(0, Artisan::call('sai:cleanup-production-test-data', [
            '--execute' => true, '--confirm' => 'DELETE-SC-2026-000001-000003',
        ]), Artisan::output());
        $this->assertDatabaseCount('requests', 1);
        $this->assertDatabaseHas('requests', ['id' => $fixture['historical']->id]);
        $this->assertDatabaseHas('request_documents', ['request_id' => $fixture['historical']->id]);
        $this->assertDatabaseHas('file_number_sequences', ['year' => 2025, 'last_number' => 1]);
        $this->assertDatabaseMissing('jobs', ['id' => $fixture['target_job_id']]);
        $this->assertDatabaseMissing('jobs', ['id' => $fixture['target_final_job_id']]);
        $this->assertDatabaseHas('jobs', ['id' => $fixture['unrelated_job_id']]);
        $this->assertDatabaseHas('jobs', ['id' => $historicalJob]);
        $this->assertDatabaseMissing('jobs', ['id' => $adminJob]);
        $this->assertDatabaseMissing('failed_jobs', ['id' => $adminFailure]);
        foreach (CleanupSafety::TABLES as $table) {
            if (! in_array($table, ['requests', 'request_documents'], true)) {
                $this->assertDatabaseCount($table, 0);
            }
        }
        $this->assertSame($preserved, $this->preservedSnapshot());
        Storage::disk('local')->assertMissing($fixture['target_file']);
        Storage::disk('local')->assertMissing($fixture['final_file']);
        Storage::disk('local')->assertMissing($fixture['proof_file']);
        Storage::disk('local')->assertMissing($fixture['payment_proof_file']);
        Storage::disk('local')->assertExists($fixture['historical_file']);
        $this->assertSame('SC/2026/000001', app(ReferenceNumberService::class)->generate());
        $new = CustomerRequest::query()->create(['reference_no' => 'SC/2026/000001',
            'service_id' => $fixture['service']->id, 'name' => 'After limited cleanup', 'mobile' => '9999999999']);
        $this->assertSame('SC/2026/F000001', app(FileNumberService::class)->assign($new));
    }

    #[TestWith(['completed'])]
    #[TestWith(['incomplete'])]
    #[TestWith(['invalid'])]
    public function test_legacy_command_cannot_bypass_all_scope_journal(string $state): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        if ($state === 'incomplete') {
            $safety = app(CleanupSafety::class);
            $journal = $safety->readJournal();
            $journal['completed'] = false;
            $safety->writeJournal($journal);
        } elseif ($state === 'invalid') {
            Storage::disk('local')->put(CleanupSafety::JOURNAL, 'invalid');
        }
        foreach ([1, 2, 3] as $number) {
            CustomerRequest::query()->create(['reference_no' => sprintf('SC/2026/%06d', $number),
                'file_number' => sprintf('SC/2026/F%06d', $number), 'service_id' => $fixture['service']->id,
                'name' => 'New real request', 'mobile' => '9999999999']);
        }
        DB::table('file_number_sequences')->insert(['year' => 2026, 'last_number' => 3]);
        $before = $this->snapshot();
        $files = Storage::disk('local')->allFiles();
        $journalBytes = Storage::disk('local')->get(CleanupSafety::JOURNAL);
        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data'));
        $this->assertStringContainsString('All-scope cleanup journal exists', Artisan::output());
        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data', [
            '--execute' => true, '--confirm' => 'DELETE-SC-2026-000001-000003',
        ]));
        $this->assertStringContainsString('All-scope cleanup journal exists', Artisan::output());
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($files, Storage::disk('local')->allFiles());
        $this->assertSame($journalBytes, Storage::disk('local')->get(CleanupSafety::JOURNAL));
    }

    public function test_all_scope_cleanup_preserves_populated_calendar_templates_settings_services_and_admins(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $this->populatePreservedBusinessData();
        // Templates are application views/configuration, not a database table.
        config(['msg91-whatsapp.templates.request_received' => ['name' => 'preserved_request_template',
            'parameter_count' => 3, 'languages' => ['gu' => true, 'en' => true]]]);
        $templates = config('msg91-whatsapp.templates');
        $templateFiles = [];
        foreach (glob(resource_path('views/emails/*.blade.php')) as $path) {
            $templateFiles[$path] = hash_file('sha256', $path);
        }
        $this->assertNotEmpty($templates);
        $this->assertNotEmpty($templateFiles);
        $this->assertDatabaseCount('holidays', 2);
        $this->assertDatabaseCount('services', 2);
        $this->assertDatabaseCount('users', 2);
        $this->assertGreaterThan(1, DB::table('settings')->where('setting_group', 'customer_notifications')->count());
        $before = $this->preservedSnapshot();
        $this->fingerprint();
        $this->assertSame($before, $this->preservedSnapshot());
        $this->assertSame(0, $this->executeCleanup(), Artisan::output());
        $this->assertSame($before, $this->preservedSnapshot());
        $this->assertSame($templates, config('msg91-whatsapp.templates'));
        foreach ($templateFiles as $path => $checksum) {
            $this->assertSame($checksum, hash_file('sha256', $path));
        }
        $this->assertDatabaseHas('settings', ['setting_key' => 'notifications.request_received.email', 'setting_value' => '1']);
        $this->assertDatabaseHas('services', ['slug' => 'unrelated-inactive', 'is_active' => false]);
        $this->assertDatabaseHas('users', ['id' => $fixture['user']->id, 'role' => 'admin']);
        $this->assertDatabaseCount('requests', 0);
        $this->assertTrue(app(CleanupSafety::class)->readJournal()['completed']);
        $this->assertSame(0, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertSame($before, $this->preservedSnapshot());
    }

    #[TestWith(['environment'])]
    #[TestWith(['maintenance'])]
    #[TestWith(['confirmation'])]
    #[TestWith(['approval'])]
    #[TestWith(['workers'])]
    #[TestWith(['scheduler'])]
    public function test_each_execution_guard_refuses_without_mutating_rows_files_or_journal(string $guard): void
    {
        Storage::fake('local');
        $this->completeFixture();
        $this->populatePreservedBusinessData();
        $options = $this->executionOptions($this->fingerprint());
        $this->app->detectEnvironment(fn () => $guard === 'environment' ? 'testing' : 'production');
        if ($guard === 'maintenance') {
            $maintenance = \Mockery::mock(MaintenanceMode::class);
            $maintenance->shouldReceive('active')->andReturn(false);
            $this->app->instance(MaintenanceMode::class, $maintenance);
        } elseif ($guard === 'confirmation') {
            $options['--confirm'] = 'DELETE-SC-2026-000001-000003';
        } elseif ($guard === 'approval') {
            $options['--approve'] = str_repeat('0', 64);
        } elseif ($guard === 'workers') {
            unset($options['--workers-stopped']);
        } elseif ($guard === 'scheduler') {
            unset($options['--scheduler-stopped']);
        }
        $before = $this->snapshot();
        $preserved = $this->preservedSnapshot();
        $files = Storage::disk('local')->allFiles();
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', $options));
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($preserved, $this->preservedSnapshot());
        $this->assertSame($files, Storage::disk('local')->allFiles());
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_legacy_execution_still_requires_production_and_its_own_token(bool $production): void
    {
        Storage::fake('local');
        $this->completeFixture();
        $before = $this->snapshot();
        $this->app->detectEnvironment(fn () => $production ? 'production' : 'testing');
        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data', ['--execute' => true,
            '--confirm' => $production ? self::CONFIRMATION : 'DELETE-SC-2026-000001-000003']));
        $this->assertSame($before, $this->snapshot());
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
    }

    public function test_queue_chains_nested_inside_mailables_also_block_legacy_and_all_scope_cleanup(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $mail = new AdminNewCustomerRequestMail($fixture['targets'][0]);
        $mail->chain([new SendCustomerNotificationJob((int) DB::table('customer_notification_deliveries')->value('id'))]);
        DB::table('jobs')->where('id', $fixture['unrelated_job_id'])->update([
            'payload' => $this->payload(new SendQueuedMailable($mail)),
        ]);
        $before = $this->snapshot();
        foreach (['sai:cleanup-all-test-requests', 'sai:cleanup-production-test-data'] as $command) {
            $this->assertSame(1, Artisan::call($command));
            $this->assertStringContainsString('Chained queue work requires explicit review', Artisan::output());
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_preserved_counter_change_during_recovery_blocks_further_deletion(): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $this->app->instance(CleanupSafety::class, new class extends CleanupSafety
        {
            public function deleteFile(string $path): bool
            {
                return false;
            }
        });
        $this->assertSame(1, $this->executeCleanup());
        DB::table('file_number_sequences')->where('year', 2025)->update(['last_number' => 2]);
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertStringContainsString('Preserved numbering state changed during recovery', Artisan::output());
        Storage::disk('local')->assertExists($fixture['target_file']);
        $this->assertFalse(app(CleanupSafety::class)->readJournal()['completed']);
    }

    #[TestWith(['batchId', 'Target job belongs to a batch'])]
    #[TestWith(['chainCatchCallbacks', 'Chained queue work requires explicit review'])]
    public function test_unreviewed_queue_metadata_blocks_execution_before_journal_creation(string $field, string $message): void
    {
        Storage::fake('local');
        $fixture = $this->completeFixture();
        $job = new SendCustomerNotificationJob((int) DB::table('customer_notification_deliveries')->value('id'));
        if ($field === 'chainCatchCallbacks') {
            $job->chainCatchCallbacks = ['unreviewed-callback'];
        }
        $payload = json_decode($this->payload($job), true, flags: JSON_THROW_ON_ERROR);
        if ($field === 'batchId') {
            // Current jobs do not use Batchable; inject future/legacy batch metadata
            // into the serialized envelope without instantiating a queued job.
            $serialized = preg_replace_callback('/^(O:\d+:"[^"]+":)(\d+)(:\{)/',
                fn ($matches) => $matches[1].((int) $matches[2] + 1).$matches[3], $payload['data']['command']);
            $payload['data']['command'] = substr($serialized, 0, -1).serialize('batchId').serialize('unreviewed-batch').'}';
        }
        DB::table('jobs')->where('id', $fixture['target_job_id'])->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        $before = $this->snapshot();
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', $this->executionOptions(str_repeat('0', 64))));
        $this->assertStringContainsString($message, Artisan::output());
        $this->assertSame($before, $this->snapshot());
        Storage::disk('local')->assertExists($fixture['target_file']);
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
    }

    private function populatePreservedBusinessData(): void
    {
        DB::table('holidays')->insert([
            ['holiday_date' => '2026-12-25', 'title' => 'Office holiday', 'description' => 'Keep calendar', 'is_recurring' => true, 'is_closed' => true],
            ['holiday_date' => '2026-12-31', 'title' => 'Working day exception', 'description' => 'Keep exception', 'is_recurring' => false, 'is_closed' => false],
        ]);
        $this->seed(NotificationSettingsSeeder::class);
        DB::table('settings')->where('setting_key', 'notifications.request_received.email')->update(['setting_value' => '1']);
        Setting::query()->create(['setting_key' => 'office.name', 'setting_value' => 'Preserved office',
            'value_type' => 'string', 'setting_group' => 'office', 'is_public' => true]);
        Service::query()->create(['name_en' => 'Unrelated inactive service', 'name_gu' => 'Preserved',
            'slug' => 'unrelated-inactive', 'is_active' => false]);
        User::factory()->create(['role' => 'admin', 'is_active' => false]);
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach ([...CleanupSafety::TABLES, 'jobs', 'failed_jobs', 'file_number_sequences', 'users', 'settings', 'services'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }

    private function preservedSnapshot(): array
    {
        $snapshot = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (! in_array($name, [...CleanupSafety::TABLES, 'jobs', 'failed_jobs', 'file_number_sequences'], true)) {
                $snapshot[$name] = hash('sha256', DB::table($name)->get()->toJson());
            }
        }

        return $snapshot;
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
        $historical = CustomerRequest::query()->create([
            'reference_no' => 'SC/2025/000001',
            'file_number' => 'SC/2025/F000001',
            'service_id' => $service->id,
            'name' => 'Older Test Customer',
            'mobile' => '9888888888',
            'status' => 'completed',
        ]);
        $historical->forceFill(['created_at' => '2025-08-16 10:00:00'])->saveQuietly();
        DB::table('file_number_sequences')->insert(['year' => 2026, 'last_number' => 3, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('file_number_sequences')->insert(['year' => 2025, 'last_number' => 1, 'created_at' => now(), 'updated_at' => now()]);

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
        $targetJobId = DB::table('jobs')->insertGetId(['queue' => 'default', 'payload' => $this->payload(new SendCustomerNotificationJob($deliveryId)), 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $finalFile = 'customer-requests/'.$target->id.'/final-documents/final.pdf';
        Storage::disk('local')->put($finalFile, '%PDF-final');
        $finalDocumentId = DB::table('request_final_documents')->insertGetId(['request_id' => $target->id, 'original_name' => 'final.pdf', 'storage_path' => $finalFile, 'mime_type' => 'application/pdf', 'file_size' => 10, 'uploaded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $finalDeliveryId = DB::table('request_final_document_deliveries')->insertGetId(['request_id' => $target->id, 'channel' => 'email', 'status' => 'pending', 'recipient_masked' => 't***@example.com', 'recipient_hash' => hash('sha256', 'test@example.com'), 'idempotency_key' => hash('sha256', 'cleanup-final-delivery'), 'initiated_by' => $user->id, 'queued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('request_final_document_delivery_items')->insert(['delivery_id' => $finalDeliveryId, 'final_document_id' => $finalDocumentId, 'created_at' => now(), 'updated_at' => now()]);
        $targetFinalJobId = DB::table('jobs')->insertGetId(['queue' => 'customer-notifications', 'payload' => $this->payload(new SendFinalDocumentDeliveryJob($finalDeliveryId)), 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);
        $unrelatedJobId = DB::table('jobs')->insertGetId(['queue' => 'default', 'payload' => $this->payload(new \stdClass), 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

        $historicalFile = 'customer-requests/'.$historical->id.'/keep.pdf';
        Storage::disk('local')->put($historicalFile, '%PDF-keep');
        DB::table('request_documents')->insert(['request_id' => $historical->id, 'file_name' => 'keep.pdf', 'file_path' => $historicalFile, 'file_type' => 'application/pdf', 'created_at' => now(), 'updated_at' => now()]);

        return compact('service', 'user', 'targets', 'historical', 'targetFile', 'proofFile', 'finalFile', 'historicalFile') + [
            'target_file' => $targetFile,
            'proof_file' => $proofFile,
            'final_file' => $finalFile,
            'payment_proof_file' => $paymentProofFile,
            'historical_file' => $historicalFile,
            'target_job_id' => $targetJobId,
            'target_final_job_id' => $targetFinalJobId,
            'unrelated_job_id' => $unrelatedJobId,
        ];
    }
}
