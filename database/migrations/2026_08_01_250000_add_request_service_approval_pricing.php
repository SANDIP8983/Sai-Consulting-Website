<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_services', function (Blueprint $table): void {
            $table->decimal('original_professional_fee', 10, 2)->nullable()->after('professional_fee');
            $table->string('discount_type', 20)->default('none')->after('original_professional_fee');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_value');
            $table->string('discount_reason', 50)->nullable()->after('discount_amount');
            $table->decimal('net_professional_fee', 10, 2)->nullable()->after('discount_reason');
            $table->decimal('gst_amount', 10, 2)->nullable()->after('gst_rate');
            $table->decimal('final_total', 12, 2)->nullable()->after('government_charges_snapshot');
            $table->timestamp('pricing_locked_at')->nullable()->after('final_total');
            $table->timestamp('pricing_unlocked_at')->nullable()->after('pricing_locked_at');
            $table->foreignId('pricing_unlocked_by')->nullable()->after('pricing_unlocked_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('request_service_approval_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_service_id')->constrained('request_services')->cascadeOnDelete();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('pricing_snapshot');
            $table->string('action', 20)->default('approved');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_service_approval_histories');
        Schema::table('request_services', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pricing_unlocked_by');
            $table->dropColumn(['original_professional_fee', 'discount_type', 'discount_value', 'discount_amount', 'discount_reason', 'net_professional_fee', 'gst_amount', 'final_total', 'pricing_locked_at', 'pricing_unlocked_at']);
        });
    }
};
