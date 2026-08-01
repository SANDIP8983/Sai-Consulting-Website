<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_processing_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('requests')->cascadeOnDelete();
            $table->string('processing_stage', 50)->default('file_opened')->index();
            $table->date('file_opened_at')->nullable();
            $table->string('priority', 20)->default('normal')->index();
            $table->foreignId('file_in_charge_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_file_note')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->boolean('uses_drafting_workflow')->default(false);
            $table->boolean('requires_token_booking')->default(false);
            $table->boolean('requires_registration')->default(false);
            $table->boolean('requires_certified_copy')->default(false);
            $table->date('draft_started_at')->nullable();
            $table->date('draft_ready_at')->nullable();
            $table->date('customer_verification_at')->nullable();
            $table->text('correction_note')->nullable();
            $table->date('final_draft_at')->nullable();
            $table->text('drafting_internal_note')->nullable();
            $table->text('drafting_customer_remark')->nullable();
            $table->string('token_booking_status', 30)->nullable();
            $table->string('token_number', 100)->nullable();
            $table->timestamp('token_scheduled_at')->nullable();
            $table->string('sub_registrar_office_name', 200)->nullable();
            $table->timestamp('registration_appointment_at')->nullable();
            $table->date('registration_date')->nullable();
            $table->string('registration_number', 150)->nullable();
            $table->boolean('registration_number_public')->default(false);
            $table->text('registration_internal_note')->nullable();
            $table->text('registration_customer_remark')->nullable();
            $table->timestamp('registered_scan_received_at')->nullable();
            $table->foreignId('registered_document_id')->nullable()->constrained('request_documents')->nullOnDelete();
            $table->string('certified_copy_status', 30)->nullable();
            $table->date('certified_copy_received_date')->nullable();
            $table->date('ready_for_dispatch_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_processing_details');
    }
};
