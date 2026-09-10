<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_notification_deliveries', function (Blueprint $table): void {
            $table->json('provider_response')->nullable()->after('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_notification_deliveries', function (Blueprint $table): void {
            $table->dropColumn('provider_response');
        });
    }
};
