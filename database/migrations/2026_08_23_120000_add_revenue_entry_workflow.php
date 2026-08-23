<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->boolean('revenue_entry_required')->default(false)->after('case_planning_version');
        });

        Schema::create('request_revenue_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('entry_number')->nullable();
            $table->date('entry_date')->nullable();
            $table->string('application_number')->nullable();
            $table->date('application_date')->nullable();
            $table->string('village')->nullable();
            $table->string('taluka')->nullable();
            $table->string('district')->nullable();
            $table->json('survey_numbers')->nullable();
            $table->string('status', 40)->default('not_started');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('certified_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('customer_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['request_id', 'status']);
        });

        Schema::create('request_revenue_entry_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_revenue_entry_id')->constrained('request_revenue_entries')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('request_revenue_entry_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('request_revenue_entry_id')->nullable()->constrained('request_revenue_entries')->cascadeOnDelete();
            $table->string('action', 50);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_revenue_entry_histories');
        Schema::dropIfExists('request_revenue_entry_documents');
        Schema::dropIfExists('request_revenue_entries');
        Schema::table('requests', fn (Blueprint $table) => $table->dropColumn('revenue_entry_required'));
    }
};
