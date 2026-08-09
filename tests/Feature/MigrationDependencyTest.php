<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_has_the_requests_service_foreign_key_after_all_migrations_run(): void
    {
        $foreignKey = collect(Schema::getForeignKeys('requests'))->first(
            fn (array $key): bool => $key['columns'] === ['service_id']
                && $key['foreign_table'] === 'services'
                && $key['foreign_columns'] === ['id']
        );

        $this->assertNotNull($foreignKey);
        $this->assertSame('cascade', $foreignKey['on_update']);
        $this->assertSame('restrict', $foreignKey['on_delete']);
    }

    public function test_requests_creation_does_not_reference_the_later_services_table(): void
    {
        $requestMigration = file_get_contents(database_path('migrations/2026_07_29_174519_create_requests_table.php'));
        $deferredMigration = database_path('migrations/2026_08_09_210000_add_deferred_requests_service_foreign_key.php');

        $this->assertStringNotContainsString("constrained('services')", $requestMigration);
        $this->assertFileExists($deferredMigration);
        $this->assertGreaterThan(
            '2026_07_30_082802_create_services_table.php',
            basename($deferredMigration)
        );
    }

    public function test_corrective_migration_skips_an_existing_equivalent_constraint(): void
    {
        $migration = require database_path('migrations/2026_08_09_210000_add_deferred_requests_service_foreign_key.php');
        $before = collect(Schema::getForeignKeys('requests'))->filter(
            fn (array $key): bool => $key['columns'] === ['service_id']
                && $key['foreign_table'] === 'services'
                && $key['foreign_columns'] === ['id']
        )->count();

        $migration->up();

        $after = collect(Schema::getForeignKeys('requests'))->filter(
            fn (array $key): bool => $key['columns'] === ['service_id']
                && $key['foreign_table'] === 'services'
                && $key['foreign_columns'] === ['id']
        )->count();

        $this->assertSame(1, $before);
        $this->assertSame($before, $after);
    }
}
