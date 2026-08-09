<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_notification_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->string('milestone', 60)->index();
            $table->string('event_key', 191)->unique();
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('safe_context')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('customer_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_event_id')->constrained('customer_notification_events')->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->string('provider', 60)->nullable();
            $table->string('provider_message_id', 191)->nullable()->index();
            $table->string('recipient_masked', 255)->nullable();
            $table->string('recipient_hash', 64)->nullable()->index();
            $table->string('template_key', 100);
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('failure_category', 60)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_event_id', 'channel'], 'notification_event_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notification_deliveries');
        Schema::dropIfExists('customer_notification_events');
    }
};
