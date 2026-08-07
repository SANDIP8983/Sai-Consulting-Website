<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_services', function (Blueprint $table): void {
            $table->foreignId('added_by')->nullable()->after('service_id')->constrained('users')->nullOnDelete();
            $table->boolean('is_admin_added')->default(false)->after('added_by');
            $table->text('internal_note')->nullable()->after('decision_notes');
        });
    }

    public function down(): void
    {
        Schema::table('request_services', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('added_by');
            $table->dropColumn(['is_admin_added', 'internal_note']);
        });
    }
};
