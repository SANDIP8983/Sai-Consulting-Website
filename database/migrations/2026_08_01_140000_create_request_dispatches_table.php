<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('dispatch_status', 30)->index();
            $table->string('dispatch_method', 50);
            $table->timestamp('dispatch_date');
            $table->string('tracking_number', 150)->nullable();
            $table->string('carrier_name', 150)->nullable();
            $table->text('internal_note')->nullable();
            $table->text('customer_remark')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['request_id', 'dispatch_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_dispatches');
    }
};
