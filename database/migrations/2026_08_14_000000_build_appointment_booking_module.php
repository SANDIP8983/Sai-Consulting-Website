<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('reference_no')->unique();
            $table->string('customer_name');
            $table->string('mobile', 15);
            $table->string('whatsapp', 15)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->dateTime('scheduled_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('status', 20)->default('pending')->index();
            $table->string('source', 20)->default('online')->index();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('slot_key')->nullable()->unique();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
        Schema::create('appointment_blocks', function (Blueprint $table) {
            $table->id();
            $table->date('block_date')->index();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->boolean('full_day')->default(false);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('appointment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->dateTime('old_scheduled_at')->nullable();
            $table->dateTime('new_scheduled_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_histories');
        Schema::dropIfExists('appointment_blocks');
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropUnique(['reference_no']);
            $table->dropUnique(['slot_key']);
            $table->dropIndex(['scheduled_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['source']);
            $table->dropColumn(['reference_no', 'customer_name', 'mobile', 'whatsapp', 'email', 'service_id', 'scheduled_at', 'duration_minutes', 'status', 'source', 'customer_note', 'admin_note', 'slot_key', 'reminder_sent_at', 'confirmed_at', 'completed_at', 'cancelled_at']);
        });
    }
};
