<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the public-facing service description without changing the existing migration.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->longText('description')
                ->nullable()
                ->after('name_gu')
                ->comment('Public-facing service description');
        });
    }

    /**
     * Remove the public-facing service description.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
