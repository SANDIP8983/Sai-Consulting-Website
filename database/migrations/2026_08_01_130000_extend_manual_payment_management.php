<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->string('request_origin', 20)->default('online')->index()->after('file_number');
            $table->foreignId('fee_updated_by')->nullable()->after('amount_due')->constrained('users')->nullOnDelete();
            $table->timestamp('fee_updated_at')->nullable()->after('fee_updated_by');
        });

        Schema::table('request_payments', function (Blueprint $table): void {
            $table->string('payment_status', 20)->default('received')->index()->after('amount');
            $table->text('customer_remark')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('request_payments', function (Blueprint $table): void {
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'customer_remark']);
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->dropForeign(['fee_updated_by']);
            $table->dropIndex(['request_origin']);
            $table->dropColumn(['request_origin', 'fee_updated_by', 'fee_updated_at']);
        });
    }
};
