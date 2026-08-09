<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'requests_service_id_deferred_foreign';

    public function up(): void
    {
        $hasEquivalentConstraint = collect(Schema::getForeignKeys('requests'))->contains(
            fn (array $key): bool => $key['columns'] === ['service_id']
                && $key['foreign_table'] === 'services'
                && $key['foreign_columns'] === ['id']
        );

        if ($hasEquivalentConstraint) {
            return;
        }

        Schema::table('requests', function (Blueprint $table): void {
            $table->foreign('service_id', self::CONSTRAINT)
                ->references('id')
                ->on('services')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // SQLite does not expose constraint names. A no-op is safer there for
        // existing installations; dropping requests later removes the key.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $ownsConstraint = collect(Schema::getForeignKeys('requests'))->contains(
            fn (array $key): bool => $key['name'] === self::CONSTRAINT
        );

        if ($ownsConstraint) {
            Schema::table('requests', function (Blueprint $table): void {
                $table->dropForeign(self::CONSTRAINT);
            });
        }
    }
};
