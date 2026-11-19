<?php

namespace Tests\Feature;

use App\Jobs\SendCustomerNotificationJob;
use App\Models\CustomerRequest;
use App\Services\CleanupInventory;
use App\Services\CleanupQueueInspector;
use App\Services\CleanupSafety;
use App\Services\FileNumberService;
use App\Services\ReferenceNumberService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** No RefreshDatabase, migrate:fresh, outer transactions, or foreign-key bypass. */
#[RequiresFunction('cleanupMariaDbConnection')]
class MariaDbCleanupSafetyTest extends TestCase
{
    private static bool $migrated = false;

    private array $inserted = [];

    private array $baseline = [];

    private array $fixture = [];

    public function createApplication()
    {
        if (! defined('CLEANUP_MARIADB_DSN')) {
            throw new \RuntimeException('Use phpunit.cleanup-mariadb.xml only.');
        }
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.cleanup-mariadb-do-not-load');
        $app->useStoragePath(sys_get_temp_dir().'/sai-cleanup-mariadb-'.bin2hex(random_bytes(10)));
        $app->make(Kernel::class)->bootstrap();
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections', ['mysql' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306,
            'database' => CLEANUP_MARIADB_DATABASE, 'username' => 'root', 'password' => '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
        ]]);
        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database.connection', 'mysql');
        $app['config']->set('queue.failed', ['driver' => 'database-uuids', 'database' => 'mysql', 'table' => 'failed_jobs']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('mail.default', 'array');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Reverify the explicit connection before any migrations/fixtures.
        $pdo = cleanupMariaDbConnection();
        $this->assertSame(CLEANUP_MARIADB_DATABASE, DB::connection()->getDatabaseName());
        if (! self::$migrated) {
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), 'Isolated migrations must succeed.');
            $manifest = ['database' => CLEANUP_MARIADB_DATABASE, 'baseline' => cleanupMariaDbBaseline($pdo)];
            file_put_contents(CLEANUP_MARIADB_MANIFEST, json_encode($manifest, JSON_THROW_ON_ERROR));
            self::$migrated = true;
        }
        $currentBaseline = cleanupMariaDbBaseline($pdo);
        $recorded = json_decode(file_get_contents(CLEANUP_MARIADB_MANIFEST), true, flags: JSON_THROW_ON_ERROR);
        if (($recorded['baseline'] ?? null) !== $currentBaseline) {
            throw new \RuntimeException('STOP: test database differs from the recorded baseline. No fixture writes allowed.');
        }
        $this->baseline = $currentBaseline;
        $this->travelTo('2026-11-13 12:00:00');
        Storage::fake('local');
        Http::preventStrayRequests();
        Mail::fake();
        $maintenance = \Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturn(true)->byDefault();
        $this->app->instance(MaintenanceMode::class, $maintenance);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->app && $this->baseline !== []) {
                $this->assertSame(0, DB::connection()->transactionLevel());
                // Delete only individually recorded synthetic fixture IDs, in
                // reverse insertion order. Never drop schemas or reset counters.
                foreach (array_reverse($this->inserted) as [$table, $id]) {
                    DB::table($table)->where('id', $id)->delete();
                }
                $this->assertSame($this->baseline, cleanupMariaDbBaseline(cleanupMariaDbConnection()),
                    'Test must restore the exact post-migration baseline; unexpected data must stop repeat runs.');
            }
        } finally {
            parent::tearDown();
        }
    }

    private function insert(string $table, array $row): int
    {
        $id = DB::table($table)->insertGetId($row);
        $this->inserted[] = [$table, $id];

        return $id;
    }

    private function fixture(bool $requests = true): array
    {
        $service = $this->insert('services', ['name_en' => 'Synthetic cleanup service', 'name_gu' => 'Synthetic',
            'slug' => 'synthetic-cleanup-service', 'is_active' => true]);
        $this->insert('services', ['name_en' => 'Preserved inactive service', 'name_gu' => 'Synthetic inactive',
            'slug' => 'synthetic-inactive-service', 'is_active' => false]);
        $admin = $this->insert('users', ['name' => 'Synthetic admin', 'email' => 'cleanup-admin@example.invalid',
            'username' => 'cleanup-test-admin', 'password' => password_hash('synthetic-only', PASSWORD_BCRYPT),
            'role' => 'admin', 'is_active' => true]);
        $this->insert('holidays', ['holiday_date' => '2026-12-25', 'title' => 'Synthetic recurring holiday', 'is_recurring' => true, 'is_closed' => true]);
        $this->insert('holidays', ['holiday_date' => '2026-12-31', 'title' => 'Synthetic opening exception', 'is_recurring' => false, 'is_closed' => false]);
        $this->insert('settings', ['setting_key' => 'cleanup.synthetic.office', 'setting_value' => 'Preserve office', 'value_type' => 'string', 'setting_group' => 'office']);
        $this->insert('settings', ['setting_key' => 'cleanup.synthetic.notification', 'setting_value' => '1', 'value_type' => 'boolean', 'setting_group' => 'customer_notifications']);
        config(['msg91-whatsapp.templates.request_received' => ['name' => 'synthetic_preserved_template',
            'parameter_count' => 3, 'languages' => ['gu' => true, 'en' => true]]]);
        $ids = [];
        if ($requests) {
            foreach ([1, 2, 3] as $number) {
                $ids[] = $this->insert('requests', ['service_id' => $service, 'name' => 'Synthetic test request',
                    'mobile' => '9999999999', 'reference_no' => sprintf('SC/2026/%06d', $number),
                    'file_number' => sprintf('SC/2026/F%06d', $number), 'created_at' => '2026-11-01 12:00:00']);
            }
            $historical = $this->insert('requests', ['service_id' => $service, 'name' => 'Synthetic historical request',
                'mobile' => '9999999999', 'reference_no' => 'SC/2025/000001',
                'file_number' => 'SC/2025/F000001', 'created_at' => '2025-11-01 12:00:00']);
            $this->insert('file_number_sequences', ['year' => 2026, 'last_number' => 15]);
            $this->insert('file_number_sequences', ['year' => 2025, 'last_number' => 1]);
            $path = 'customer-requests/'.$ids[0].'/synthetic.pdf';
            Storage::disk('local')->put($path, 'synthetic document bytes');
            $this->insert('request_documents', ['request_id' => $ids[0], 'file_name' => 'synthetic.pdf', 'file_path' => $path]);
        }

        return $this->fixture = compact('service', 'admin', 'ids') + ['historical' => $historical ?? null, 'path' => $path ?? null];
    }

    private function approvedScopeFixture(): array
    {
        $fixture = $this->fixture();
        DB::table('requests')->where('id', $fixture['historical'])->update([
            'reference_no' => 'SC/2026/000004', 'file_number' => null, 'created_at' => '2026-11-01 12:00:00',
        ]);
        $targetIds = [...$fixture['ids'], $fixture['historical']];
        foreach (range(5, 19) as $number) {
            $targetIds[] = $this->insert('requests', ['service_id' => $fixture['service'], 'name' => 'Synthetic test request',
                'mobile' => '9999999999', 'reference_no' => sprintf('SC/2026/%06d', $number),
                'created_at' => '2026-11-01 12:00:00']);
        }
        $disk = Storage::disk('local');
        foreach ($targetIds as $id) {
            $disk->put("customer-requests/$id/scope.pdf", 'synthetic scope document');
        }
        $fixture['ids'] = $targetIds;
        $fixture['files'] = $disk->allFiles();
        $this->assertCount(19, $targetIds);
        $this->assertCount(20, $fixture['files']);

        return $fixture;
    }

    private function snapshot(): array
    {
        return cleanupMariaDbBaseline(cleanupMariaDbConnection());
    }

    private function preserved(): array
    {
        $snapshot = $this->snapshot();
        foreach ([...CleanupSafety::TABLES, 'jobs', 'failed_jobs', 'file_number_sequences'] as $table) {
            unset($snapshot[$table]);
        }

        return $snapshot;
    }

    private function approve(): string
    {
        $audit = app(CleanupInventory::class)->inspect();
        $this->assertSame([], $audit['blockers'], implode('; ', $audit['blockers']));

        return $audit['fingerprint'];
    }

    private function execute(?string $approval = null): int
    {
        $approval ??= $this->approve();
        // Simulate the environment guard only. Connection remains the hardcoded
        // local test database, and maintenance/storage/mail are test doubles.
        $this->app->detectEnvironment(fn () => 'production');

        return Artisan::call('sai:cleanup-all-test-requests', ['--execute' => true,
            '--confirm' => 'DELETE-ALL-INVENTORIED-TEST-REQUESTS', '--approve' => $approval,
            '--workers-stopped' => true, '--scheduler-stopped' => true]);
    }

    public function test_all_scope_dry_run_commit_numbering_and_populated_preservation(): void
    {
        $fixture = $this->approvedScopeFixture();
        app(CleanupSafety::class)->assertDatabaseSafety();
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', CLEANUP_MARIADB_DATABASE)->pluck('ENGINE')->all();
        $this->assertNotEmpty($engines);
        $this->assertSame(['InnoDB'], array_values(array_unique($engines)));
        $before = $this->snapshot();
        $preserved = $this->preserved();
        $templates = config('msg91-whatsapp.templates');
        $views = array_map(fn ($path) => hash_file('sha256', $path), glob(resource_path('views/emails/*.blade.php')));
        $this->assertNotEmpty($views);
        $this->assertSame(0, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertSame($before, $this->snapshot());
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
        $this->assertSame(0, $this->execute(), Artisan::output());
        foreach (CleanupSafety::TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame($preserved, $this->preserved());
        $this->assertSame($templates, config('msg91-whatsapp.templates'));
        $this->assertSame($views, array_map(fn ($path) => hash_file('sha256', $path), glob(resource_path('views/emails/*.blade.php'))));
        Storage::disk('local')->assertMissing($fixture['files']);
        $this->assertDatabaseMissing('file_number_sequences', ['year' => 2026]);
        $this->assertDatabaseHas('file_number_sequences', ['year' => 2025, 'last_number' => 1]);
        $this->assertTrue(app(CleanupSafety::class)->readJournal()['completed']);
        $this->assertSame('SC/2026/000001', app(ReferenceNumberService::class)->generate());
        $id = $this->insert('requests', ['reference_no' => 'SC/2026/000001', 'service_id' => $fixture['service'],
            'name' => 'Synthetic next request', 'mobile' => '9999999999', 'created_at' => now()]);
        $this->assertGreaterThan(max($fixture['ids']), $id);
        $this->assertSame('SC/2026/F000001', app(FileNumberService::class)->assign(CustomerRequest::findOrFail($id)));
        $this->inserted[] = ['file_number_sequences', (int) DB::table('file_number_sequences')->where('year', 2026)->value('id')];
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
        $this->assertTrue(DB::table('requests')->where('id', $id)->exists());
        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data', ['--execute' => true, '--confirm' => 'DELETE-SC-2026-000001-000003']));
        $this->assertTrue(DB::table('requests')->where('id', $id)->exists());
    }

    public function test_legacy_command_refuses_execution_and_preserves_requests_and_business_records(): void
    {
        $fixture = $this->fixture();
        DB::table('file_number_sequences')->where('year', 2026)->update(['last_number' => 3]);
        $before = $this->snapshot();
        $this->assertSame(0, Artisan::call('sai:cleanup-production-test-data'));
        $this->assertSame($before, $this->snapshot());
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame(1, Artisan::call('sai:cleanup-production-test-data', ['--execute' => true, '--confirm' => 'DELETE-SC-2026-000001-000003']));
        $this->assertStringContainsString('Historical cleanup execution is disabled', Artisan::output());
        $this->assertSame(4, DB::table('requests')->count());
        $this->assertTrue(DB::table('requests')->where('id', $fixture['historical'])->exists());
        $this->assertSame($before, $this->snapshot());
        Storage::disk('local')->assertExists($fixture['path']);
        Storage::disk('local')->assertMissing('maintenance/test-data-cleanup-2026.json');
    }

    public function test_real_innodb_failure_rolls_back_deleted_rows_before_file_deletion(): void
    {
        $fixture = $this->approvedScopeFixture();
        $before = $this->snapshot();
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && str_starts_with(strtolower($query->sql), 'delete from `requests`')) {
                $armed = false;
                throw new \RuntimeException('Synthetic failure after request DELETE, before commit.');
            }
        });
        try {
            $exit = $this->execute();
            $this->assertFalse($armed, 'Failure must occur after actual DELETE, not merely during preflight.');
        } finally {
            // An unexpected preflight error must not arm the failure injector
            // against the test harness's own exact-ID fixture teardown.
            $armed = false;
        }
        $this->assertSame(1, $exit);
        $this->assertSame($before, $this->snapshot());
        Storage::disk('local')->assertExists($fixture['files']);
        $this->assertFalse(app(CleanupSafety::class)->readJournal()['completed']);
        $this->assertSame(0, $this->execute(), Artisan::output());
        $this->assertTrue(app(CleanupSafety::class)->readJournal()['completed']);
        Storage::disk('local')->assertMissing($fixture['files']);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    #[TestWith([true, 'sync'])]
    public function test_real_second_connection_inserts_and_updates_are_blocked_under_cleanup_range_locks(bool $populatedQueue, string $queue = 'database'): void
    {
        config(['queue.default' => $queue]);
        $fixture = $this->approvedScopeFixture();
        $queued = null;
        if ($populatedQueue) {
            $payload = (new \ReflectionMethod(Queue::class, 'createPayload'))->invoke(app('queue')->connection('database'), new \stdClass, 'default');
            $queued = $this->insert('jobs', ['queue' => 'default', 'payload' => $payload, 'attempts' => 0,
                'available_at' => 1, 'created_at' => 1]);
        }
        $probe = new class(app(CleanupSafety::class), app(CleanupQueueInspector::class)) extends CleanupInventory
        {
            public int $calls = 0;

            public $probe;

            public function inspect(): array
            {
                if (++$this->calls === 3) {
                    ($this->probe)();
                }

                return parent::inspect();
            }
        };
        $secondary = cleanupMariaDbConnection();
        $secondary->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $this->assertNotSame((int) $secondary->query('SELECT CONNECTION_ID()')->fetchColumn(), (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
        $timeouts = [];
        $probe->probe = function () use ($secondary, $fixture, $queued, &$timeouts): void {
            $this->assertSame(1, DB::connection()->transactionLevel());
            $queries = [
                'request insert' => "INSERT INTO requests (reference_no,service_id,name,mobile,created_at) VALUES ('SC/2026/009999',{$fixture['service']},'Synthetic concurrent','9999999999','2026-11-13 12:00:00')",
                'queue insert' => "INSERT INTO jobs (queue,payload,attempts,available_at,created_at) VALUES ('default','{}',0,1,1)",
                'sequence insert' => 'INSERT INTO file_number_sequences (year,last_number) VALUES (2027,0)',
                'request update' => "UPDATE requests SET name='Synthetic concurrent change' WHERE id=".$fixture['ids'][0],
            ];
            if ($queued !== null) {
                $queries['queue update'] = 'UPDATE jobs SET attempts=1 WHERE id='.$queued;
            }
            foreach ($queries as $name => $sql) {
                try {
                    $secondary->exec($sql);
                    $this->fail('Concurrent write unexpectedly succeeded: '.$name);
                } catch (\PDOException $exception) {
                    $this->assertSame(1205, (int) $exception->errorInfo[1], $name);
                    $timeouts[] = $name;
                }
            }
        };
        $approval = $this->approve();
        $this->app->instance(CleanupInventory::class, $probe);
        $this->assertSame(0, $this->execute($approval), Artisan::output());
        $this->assertCount($populatedQueue ? 5 : 4, $timeouts);
        $this->assertDatabaseCount('jobs', $populatedQueue ? 1 : 0);
        if ($queued !== null) {
            $this->assertDatabaseHas('jobs', ['id' => $queued, 'attempts' => 0]);
        }
        $this->assertSame(0, DB::table('requests')->count());
        // The independent connection can insert again after the command commits.
        $secondary->exec("INSERT INTO requests (reference_no,service_id,name,mobile,created_at) VALUES ('SC/2026/000001',{$fixture['service']},'Synthetic after commit','9999999999','2026-11-13 12:00:00')");
        $this->inserted[] = ['requests', (int) $secondary->lastInsertId()];
        $this->assertSame(1, DB::table('requests')->count());
    }

    public function test_secondary_insert_after_approval_is_preserved_and_invalidates_snapshot(): void
    {
        $fixture = $this->fixture();
        $approval = $this->approve();
        $secondary = cleanupMariaDbConnection();
        $statement = $secondary->prepare('INSERT INTO requests (reference_no,service_id,name,mobile,created_at) VALUES (?,?,?,?,?)');
        $statement->execute(['SC/2026/000019', $fixture['service'], 'Synthetic concurrent arrival', '9999999999', '2026-11-13 12:00:00']);
        $id = (int) $secondary->lastInsertId();
        $this->inserted[] = ['requests', $id];
        $before = $this->snapshot();
        $this->assertSame(1, $this->execute($approval));
        $this->assertSame($before, $this->snapshot());
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
    }

    public function test_unknown_dependency_and_database_trigger_block_without_deletion(): void
    {
        $fixture = $this->fixture();
        Schema::create('cleanup_synthetic_dependency', function ($table): void {
            $table->id();
            $table->foreignId('request_document_id')->constrained('request_documents')->cascadeOnDelete();
        });
        try {
            $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests'));
            $this->assertStringContainsString('Unexpected dependency', Artisan::output());
        } finally {
            Schema::drop('cleanup_synthetic_dependency');
        }
        DB::unprepared('CREATE TRIGGER cleanup_synthetic_guard BEFORE DELETE ON requests FOR EACH ROW SET @cleanup_synthetic_trigger = 1');
        try {
            $before = $this->snapshot();
            $this->assertSame(1, $this->execute(str_repeat('0', 64)));
            $this->assertStringContainsString('Database triggers affect cleanup tables', Artisan::output());
            $this->assertSame($before, $this->snapshot());
            Storage::disk('local')->assertExists($fixture['path']);
            Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
        } finally {
            DB::unprepared('DROP TRIGGER cleanup_synthetic_guard');
        }
    }

    public function test_guards_reserved_queue_and_file_failure_recovery_on_mariadb(): void
    {
        $fixture = $this->approvedScopeFixture();
        $approval = $this->approve();
        $before = $this->snapshot();
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame(1, Artisan::call('sai:cleanup-all-test-requests', ['--execute' => true,
            '--confirm' => 'DELETE-ALL-INVENTORIED-TEST-REQUESTS', '--approve' => $approval]));
        $this->assertStringContainsString('--workers-stopped and --scheduler-stopped', Artisan::output());
        $this->assertSame($before, $this->snapshot());
        Storage::disk('local')->assertMissing(CleanupSafety::JOURNAL);
        $event = $this->insert('customer_notification_events', ['request_id' => $fixture['ids'][0],
            'milestone' => 'request_received', 'event_key' => 'synthetic-reserved', 'occurred_at' => now()]);
        $delivery = $this->insert('customer_notification_deliveries', ['notification_event_id' => $event,
            'channel' => 'email', 'status' => 'pending', 'template_key' => 'synthetic']);
        $job = new SendCustomerNotificationJob($delivery);
        $payload = (new \ReflectionMethod(Queue::class, 'createPayload'))->invoke(app('queue')->connection('database'), $job, 'default');
        $queued = $this->insert('jobs', ['queue' => 'default', 'payload' => $payload, 'attempts' => 0,
            'reserved_at' => 1, 'available_at' => 1, 'created_at' => 1]);
        $this->assertSame(1, $this->execute($approval));
        $this->assertStringContainsString('Reserved jobs exist', Artisan::output());
        DB::table('jobs')->where('id', $queued)->update(['reserved_at' => null]);
        $this->app->instance(CleanupSafety::class, new class extends CleanupSafety
        {
            public function deleteFile(string $path): bool
            {
                return false;
            }
        });
        $preserved = $this->preserved();
        $this->assertSame(1, $this->execute());
        $this->assertStringContainsString('Database cleanup committed; private files remain', Artisan::output());
        $this->assertSame(0, DB::table('requests')->count());
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame($preserved, $this->preserved());
        Storage::disk('local')->assertExists($fixture['files']);
        $this->assertFalse(app(CleanupSafety::class)->readJournal()['completed']);
        $this->app->instance(CleanupSafety::class, new CleanupSafety);
        $this->assertSame(0, $this->execute(), Artisan::output());
        $this->assertTrue(app(CleanupSafety::class)->readJournal()['completed']);
        Storage::disk('local')->assertMissing($fixture['files']);
    }

}
