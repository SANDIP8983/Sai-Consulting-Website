<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_final_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->string('original_name');
            $table->string('storage_path')->unique();
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['request_id', 'created_at']);
        });

        Schema::create('request_final_document_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->string('recipient_masked');
            $table->string('recipient_hash', 64);
            $table->string('idempotency_key', 64)->unique();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('failure_category', 60)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['request_id', 'created_at']);
        });

        Schema::create('request_final_document_delivery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')->constrained('request_final_document_deliveries')->cascadeOnDelete();
            $table->foreignId('final_document_id')->constrained('request_final_documents')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['delivery_id', 'final_document_id'], 'final_document_delivery_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_final_document_delivery_items');
        Schema::dropIfExists('request_final_document_deliveries');
        Schema::dropIfExists('request_final_documents');
    }
};
