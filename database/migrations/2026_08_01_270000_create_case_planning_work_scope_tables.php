<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_scope_items', function (Blueprint $t): void {
            $t->id();
            $t->string('name_en', 150);
            $t->string('name_gu', 150);
            $t->string('normalized_name', 160)->unique();
            $t->boolean('is_active')->default(true)->index();
            $t->unsignedInteger('display_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('service_work_scope_defaults', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $t->foreignId('work_scope_item_id')->constrained('work_scope_items')->restrictOnDelete();
            $t->boolean('is_default')->default(true);
            $t->unsignedInteger('display_order')->default(0);
            $t->timestamps();
            $t->unique(['service_id', 'work_scope_item_id']);
        });
        Schema::create('request_service_work_scopes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('request_service_id')->constrained('request_services')->cascadeOnDelete();
            $t->foreignId('work_scope_item_id')->nullable()->constrained('work_scope_items')->restrictOnDelete();
            $t->string('name_en_snapshot', 150);
            $t->string('name_gu_snapshot', 150)->nullable();
            $t->boolean('is_custom')->default(false);
            $t->string('status', 30)->default('pending')->index();
            $t->text('internal_note')->nullable();
            $t->unsignedInteger('display_order')->default(0);
            $t->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['request_service_id', 'work_scope_item_id']);
        });
        Schema::table('request_services', function (Blueprint $t): void {
            $t->timestamp('decided_at')->nullable()->after('decided_by');
            $t->string('customer_decision_message', 500)->nullable()->after('decision_notes');
        });
        Schema::table('requests', function (Blueprint $t): void {
            $t->unsignedTinyInteger('case_planning_version')->default(0)->after('request_origin');
            $t->timestamp('case_approved_at')->nullable()->after('last_status_changed_at');
            $t->foreignId('case_approved_by')->nullable()->after('case_approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('case_approved_by');
            $t->dropColumn(['case_planning_version', 'case_approved_at']);
        });
        Schema::table('request_services', fn (Blueprint $t) => $t->dropColumn(['decided_at', 'customer_decision_message']));
        Schema::dropIfExists('request_service_work_scopes');
        Schema::dropIfExists('service_work_scope_defaults');
        Schema::dropIfExists('work_scope_items');
    }
};
