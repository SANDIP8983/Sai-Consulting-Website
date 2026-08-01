<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_processing_details', function (Blueprint $table): void {
            $table->boolean('requires_dispatch')->default(true);
            $table->boolean('requires_payment_before_processing')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('request_processing_details', function (Blueprint $table): void {
            $table->dropColumn(['requires_dispatch', 'requires_payment_before_processing']);
        });
    }
};
