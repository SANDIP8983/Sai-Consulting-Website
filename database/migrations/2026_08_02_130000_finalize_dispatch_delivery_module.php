<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_dispatches', function (Blueprint $table): void {
            $table->text('document_description')->nullable();
            $table->string('recipient_name', 150)->nullable();
            $table->string('recipient_mobile', 20)->nullable();
            $table->string('recipient_email', 255)->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('tracking_url', 2048)->nullable();
            $table->string('method_description', 255)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('request_dispatch_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_dispatch_id')->constrained('request_dispatches')->cascadeOnDelete();
            $table->string('proof_type', 50);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('request_dispatch_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('request_dispatch_id')->constrained('request_dispatches')->cascadeOnDelete();
            $table->string('action', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['request_id', 'created_at']);
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_customer_remark')->nullable();
            $table->text('closure_internal_note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['closed_at', 'closure_customer_remark', 'closure_internal_note']);
        });
        Schema::dropIfExists('request_dispatch_histories');
        Schema::dropIfExists('request_dispatch_proofs');
        Schema::table('request_dispatches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['document_description', 'recipient_name', 'recipient_mobile', 'recipient_email', 'delivery_address', 'tracking_url', 'method_description', 'delivered_at', 'collected_at', 'failure_reason', 'cancellation_reason']);
        });
    }
};
