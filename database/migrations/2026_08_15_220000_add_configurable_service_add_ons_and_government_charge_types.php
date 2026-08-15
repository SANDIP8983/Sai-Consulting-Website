<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SERVICE_FK = 'svc_addons_service_fk';

    private const ADD_ON_FK = 'svc_addons_addon_fk';

    private const SERVICE_INDEX = 'svc_addons_service_idx';

    private const ADD_ON_INDEX = 'svc_addons_addon_idx';

    private const ACTIVE_INDEX = 'svc_addons_active_idx';

    private const SERVICE_ADD_ON_UNIQUE = 'svc_addons_pair_uq';

    private const CHARGE_NAME_UNIQUE = 'gov_charge_types_name_uq';

    private const CHARGE_ACTIVE_INDEX = 'gov_charge_types_active_idx';

    private const REQUEST_CHARGE_TYPE_INDEX = 'rb_gov_charges_type_idx';

    private const REQUEST_CHARGE_TYPE_FK = 'rb_gov_charges_type_fk';

    public function up(): void
    {
        $this->ensureServiceAddOnsTable();
        $this->ensureGovernmentChargeTypesTable();
        $this->ensureRequestChargeColumns();
    }

    public function down(): void
    {
        if ($this->tableExists('request_billing_government_charges')) {
            $foreign = $this->foreignKey('request_billing_government_charges', ['government_charge_type_id']);
            if ($foreign) {
                Schema::table('request_billing_government_charges', fn (Blueprint $table) => $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['government_charge_type_id'] : $foreign['name']));
            }
            $index = $this->index('request_billing_government_charges', ['government_charge_type_id']);
            if ($index) {
                Schema::table('request_billing_government_charges', fn (Blueprint $table) => $table->dropIndex($index['name']));
            }
            if ($this->columnExists('request_billing_government_charges', 'government_charge_type_id')) {
                Schema::table('request_billing_government_charges', fn (Blueprint $table) => $table->dropColumn('government_charge_type_id'));
            }
            if ($this->columnExists('request_billing_government_charges', 'name_gu')) {
                Schema::table('request_billing_government_charges', fn (Blueprint $table) => $table->dropColumn('name_gu'));
            }
        }
        Schema::dropIfExists('government_charge_types');
        Schema::dropIfExists('service_add_ons');
    }

    private function ensureServiceAddOnsTable(): void
    {
        if (! $this->tableExists('service_add_ons')) {
            $created = $this->createTableSafely('service_add_ons', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id');
                $table->foreignId('add_on_service_id');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index('service_id', self::SERVICE_INDEX);
                $table->index('add_on_service_id', self::ADD_ON_INDEX);
                $table->index('is_active', self::ACTIVE_INDEX);
                $table->unique(['service_id', 'add_on_service_id'], self::SERVICE_ADD_ON_UNIQUE);
                $table->foreign('service_id', self::SERVICE_FK)->references('id')->on('services')->cascadeOnDelete();
                $table->foreign('add_on_service_id', self::ADD_ON_FK)->references('id')->on('services')->cascadeOnDelete();
            });

            if ($created) {
                return;
            }
        }

        $this->ensureIndex('service_add_ons', ['service_id'], self::SERVICE_INDEX);
        $this->ensureIndex('service_add_ons', ['add_on_service_id'], self::ADD_ON_INDEX);
        $this->ensureIndex('service_add_ons', ['is_active'], self::ACTIVE_INDEX);
        $this->ensureIndex('service_add_ons', ['service_id', 'add_on_service_id'], self::SERVICE_ADD_ON_UNIQUE, true);
        $this->ensureForeignKey('service_add_ons', ['service_id'], 'services', self::SERVICE_FK, true);
        $this->ensureForeignKey('service_add_ons', ['add_on_service_id'], 'services', self::ADD_ON_FK, true);
    }

    private function ensureGovernmentChargeTypesTable(): void
    {
        if (! $this->tableExists('government_charge_types')) {
            $created = $this->createTableSafely('government_charge_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name_en', 150);
                $table->string('name_gu', 150)->nullable();
                $table->decimal('default_amount', 12, 2)->default(0);
                $table->string('description', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique('name_en', self::CHARGE_NAME_UNIQUE);
                $table->index('is_active', self::CHARGE_ACTIVE_INDEX);
            });

            if ($created) {
                return;
            }
        }

        $this->ensureIndex('government_charge_types', ['name_en'], self::CHARGE_NAME_UNIQUE, true);
        $this->ensureIndex('government_charge_types', ['is_active'], self::CHARGE_ACTIVE_INDEX);
    }

    private function ensureRequestChargeColumns(): void
    {
        if (! $this->columnExists('request_billing_government_charges', 'government_charge_type_id')) {
            $this->addColumnSafely('request_billing_government_charges', 'government_charge_type_id', fn (Blueprint $table) => $table->foreignId('government_charge_type_id')->nullable()->after('request_billing_id'));
        }
        if (! $this->columnExists('request_billing_government_charges', 'name_gu')) {
            $this->addColumnSafely('request_billing_government_charges', 'name_gu', fn (Blueprint $table) => $table->string('name_gu', 150)->nullable()->after('name'));
        }

        $this->ensureIndex('request_billing_government_charges', ['government_charge_type_id'], self::REQUEST_CHARGE_TYPE_INDEX);
        $this->ensureForeignKey('request_billing_government_charges', ['government_charge_type_id'], 'government_charge_types', self::REQUEST_CHARGE_TYPE_FK, false);
    }

    private function ensureIndex(string $table, array $columns, string $name, bool $unique = false): void
    {
        $exists = $this->hasIndex($table, $columns, $unique);
        if (! $exists) {
            try {
                Schema::table($table, fn (Blueprint $blueprint) => $unique ? $blueprint->unique($columns, $name) : $blueprint->index($columns, $name));
            } catch (QueryException $exception) {
                if (! $this->hasIndex($table, $columns, $unique)) {
                    throw $exception;
                }
            }
        }
    }

    private function createTableSafely(string $table, callable $definition): bool
    {
        try {
            Schema::create($table, $definition);

            return true;
        } catch (QueryException $exception) {
            // MySQL DDL is not transactional. If metadata visibility changed
            // between inspection and CREATE, preserve the existing table and
            // let the repair phase below validate its indexes and constraints.
            if (! $this->tableExists($table)) {
                throw $exception;
            }

            return false;
        }
    }

    private function tableExists(string $table): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return (bool) DB::scalar(
                'select exists(select 1 from information_schema.tables where table_schema = database() and table_name = ?) as present',
                [$table]
            );
        }

        return Schema::hasTable($table);
    }

    private function columnExists(string $table, string $column): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return (bool) DB::scalar(
                'select exists(select 1 from information_schema.columns where table_schema = database() and table_name = ? and column_name = ?) as present',
                [$table, $column]
            );
        }

        return Schema::hasColumn($table, $column);
    }

    private function hasIndex(string $table, array $columns, bool $unique = false): bool
    {
        return $this->index($table, $columns, $unique) !== null;
    }

    private function index(string $table, array $columns, bool $unique = false): ?array
    {
        $indexes = DB::getDriverName() === 'mysql'
            ? collect(DB::select('select index_name, non_unique, column_name, seq_in_index from information_schema.statistics where table_schema = database() and table_name = ? order by index_name, seq_in_index', [$table]))
                ->groupBy('index_name')->map(fn ($rows, $name) => ['name' => $name, 'columns' => $rows->pluck('column_name')->all(), 'unique' => ! (bool) $rows->first()->non_unique])->values()
            : collect(Schema::getIndexes($table));

        return $indexes->first(
            fn (array $index): bool => $index['columns'] === $columns && (! $unique || $index['unique'])
        );
    }

    private function ensureForeignKey(string $table, array $columns, string $foreignTable, string $name, bool $cascade): void
    {
        if ($this->hasForeignKey($table, $columns, $foreignTable)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $foreignTable, $name, $cascade): void {
                $foreign = $blueprint->foreign($columns, $name)->references('id')->on($foreignTable);
                $cascade ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete();
            });
        } catch (QueryException $exception) {
            if (! $this->hasForeignKey($table, $columns, $foreignTable)) {
                throw $exception;
            }
        }
    }

    private function hasForeignKey(string $table, array $columns, ?string $foreignTable = null): bool
    {
        return $this->foreignKey($table, $columns, $foreignTable) !== null;
    }

    private function foreignKey(string $table, array $columns, ?string $foreignTable = null): ?array
    {
        $keys = DB::getDriverName() === 'mysql'
            ? collect(DB::select('select constraint_name, referenced_table_name, column_name, ordinal_position from information_schema.key_column_usage where table_schema = database() and table_name = ? and referenced_table_name is not null order by constraint_name, ordinal_position', [$table]))
                ->groupBy('constraint_name')->map(fn ($rows, $name) => ['name' => $name, 'columns' => $rows->pluck('column_name')->all(), 'foreign_table' => $rows->first()->referenced_table_name])->values()
            : collect(Schema::getForeignKeys($table));

        return $keys->first(
            fn (array $key): bool => $key['columns'] === $columns && ($foreignTable === null || $key['foreign_table'] === $foreignTable)
        );
    }

    private function addColumnSafely(string $table, string $column, callable $definition): void
    {
        try {
            Schema::table($table, $definition);
        } catch (QueryException $exception) {
            if (! $this->columnExists($table, $column)) {
                throw $exception;
            }
        }
    }
};
