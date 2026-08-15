<?php

namespace Tests\Feature;

use App\Models\GovernmentChargeType;
use App\Models\Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConfigurableBillingMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_08_15_220000_add_configurable_service_add_ons_and_government_charge_types.php';

    public function test_all_declared_constraint_names_are_mysql_safe_and_deterministic(): void
    {
        $source = file_get_contents(database_path(self::MIGRATION));
        $names = [
            'svc_addons_service_fk', 'svc_addons_addon_fk', 'svc_addons_service_idx',
            'svc_addons_addon_idx', 'svc_addons_active_idx', 'svc_addons_pair_uq',
            'gov_charge_types_name_uq', 'gov_charge_types_active_idx',
            'rb_gov_charges_type_idx', 'rb_gov_charges_type_fk',
        ];

        foreach ($names as $name) {
            $this->assertLessThanOrEqual(64, strlen($name));
            $this->assertStringContainsString("'{$name}'", $source);
        }
        $this->assertStringNotContainsString('request_billing_government_charges_government_charge_type_id_foreign', $source);
        $this->assertStringContainsString('information_schema.tables', $source);
        $this->assertStringContainsString('information_schema.columns', $source);
        $this->assertStringContainsString('information_schema.statistics', $source);
        $this->assertStringContainsString('information_schema.key_column_usage', $source);
        $this->assertStringContainsString('createTableSafely', $source);
        $this->assertStringContainsString('addColumnSafely', $source);
    }

    public function test_clean_schema_contains_the_complete_configurable_billing_schema(): void
    {
        $this->assertTrue(Schema::hasTable('service_add_ons'));
        $this->assertTrue(Schema::hasTable('government_charge_types'));
        $this->assertTrue(Schema::hasColumns('request_billing_government_charges', ['government_charge_type_id', 'name_gu']));
        $this->assertTrue($this->hasForeignKey('request_billing_government_charges', ['government_charge_type_id'], 'government_charge_types'));
        $this->assertTrue($this->hasForeignKey('service_add_ons', ['service_id'], 'services'));
        $this->assertTrue($this->hasForeignKey('service_add_ons', ['add_on_service_id'], 'services'));
    }

    public function test_up_is_safe_to_rerun_after_equivalent_partial_mysql_ddl_state(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->down();

        Schema::create('service_add_ons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_service_id')->constrained('services')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['service_id', 'add_on_service_id']);
        });
        Schema::create('government_charge_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name_en', 150)->unique();
            $table->string('name_gu', 150)->nullable();
            $table->decimal('default_amount', 12, 2)->default(0);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::table('request_billing_government_charges', fn (Blueprint $table) => $table->foreignId('government_charge_type_id')->nullable());

        $base = Service::query()->create(['name_en' => 'Partial Base', 'name_gu' => 'Partial Base', 'slug' => 'partial-base']);
        $addOn = Service::query()->create(['name_en' => 'Partial Add-on', 'name_gu' => 'Partial Add-on', 'slug' => 'partial-add-on']);
        DB::table('service_add_ons')->insert(['service_id' => $base->id, 'add_on_service_id' => $addOn->id, 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        GovernmentChargeType::query()->create(['name_en' => 'Preserved Charge', 'default_amount' => 25]);

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('request_billing_government_charges', 'name_gu'));
        $this->assertTrue($this->hasForeignKey('request_billing_government_charges', ['government_charge_type_id'], 'government_charge_types'));
        $this->assertDatabaseHas('service_add_ons', ['service_id' => $base->id, 'add_on_service_id' => $addOn->id]);
        $this->assertDatabaseHas('government_charge_types', ['name_en' => 'Preserved Charge', 'default_amount' => 25]);
    }

    private function hasForeignKey(string $table, array $columns, string $foreignTable): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(
            fn (array $key): bool => $key['columns'] === $columns && $key['foreign_table'] === $foreignTable
        );
    }
}
