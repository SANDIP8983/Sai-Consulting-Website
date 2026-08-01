<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->text('short_description')->nullable();
            $table->boolean('available_online')->default(true)->index();
            $table->boolean('available_offline')->default(true)->index();
            $table->boolean('requires_property_documents')->default(true);
            $table->boolean('requires_dispatch')->default(true);
            $table->boolean('requires_payment_before_processing')->default(true);
            $table->unique('name_en');
            $table->unique('name_gu');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique(['name_en']);
            $table->dropUnique(['name_gu']);
            $table->dropIndex(['available_online']);
            $table->dropIndex(['available_offline']);
            $table->dropColumn([
                'short_description', 'available_online', 'available_offline',
                'requires_property_documents', 'requires_dispatch',
                'requires_payment_before_processing',
            ]);
        });
    }
};
