<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->longText('description_gu')->nullable();
            $table->longText('description_en')->nullable();
            $table->text('customer_instructions')->nullable();
            $table->text('important_notes')->nullable();
            $table->text('disclaimer')->nullable();
            $table->string('processing_time_label', 100)->nullable();
        });

        DB::table('services')->update([
            'description_en' => DB::raw('description'),
            'customer_instructions' => DB::raw('notes'),
        ]);
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['description_gu', 'description_en', 'customer_instructions', 'important_notes', 'disclaimer', 'processing_time_label']);
        });
    }
};
