<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_notification_events', function (Blueprint $table): void {
            $table->dropForeign(['request_id']);
            $table->foreignId('request_id')->nullable()->change();
            $table->foreign('request_id')->references('id')->on('requests')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->after('request_id')->constrained('appointments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_notification_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('appointment_id');
            $table->dropForeign(['request_id']);
            $table->foreignId('request_id')->nullable(false)->change();
            $table->foreign('request_id')->references('id')->on('requests')->restrictOnDelete();
        });
    }
};
