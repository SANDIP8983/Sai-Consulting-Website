<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_service_work_scopes', function (Blueprint $table): void {
            $table->timestamp('started_at')->nullable()->after('selected_by');
            $table->foreignId('updated_by')->nullable()->after('started_at')->constrained('users')->nullOnDelete();
            $table->text('customer_remark')->nullable()->after('internal_note');
            $table->text('resolution_reason')->nullable()->after('customer_remark');
        });
        Schema::create('request_service_work_scope_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_service_work_scope_id')->constrained('request_service_work_scopes')->cascadeOnDelete();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('reason')->nullable();
            $table->text('internal_note')->nullable();
            $table->text('customer_remark')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['request_id', 'created_at']);
        });
        Schema::table('requests', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('estimated_completion_date');
            $table->text('completion_customer_remark')->nullable()->after('completed_at');
            $table->text('completion_internal_note')->nullable()->after('completion_customer_remark');
        });
        Schema::create('request_case_action_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->text('reason')->nullable();
            $table->text('internal_note')->nullable();
            $table->text('customer_remark')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_case_action_histories');
        Schema::table('requests', fn (Blueprint $table) => $table->dropColumn(['completed_at', 'completion_customer_remark', 'completion_internal_note']));
        Schema::dropIfExists('request_service_work_scope_histories');
        Schema::table('request_service_work_scopes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['started_at', 'customer_remark', 'resolution_reason']);
        });
    }
};
