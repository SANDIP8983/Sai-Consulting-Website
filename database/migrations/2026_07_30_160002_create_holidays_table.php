<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the office-holiday calendar.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->boolean('is_recurring')
                ->default(false)
                ->comment('Whether the holiday recurs annually');
            $table->boolean('is_closed')
                ->default(true)
                ->comment('Whether the office is closed for this holiday');
            $table->timestamps();

            $table->index(['is_recurring', 'holiday_date']);
        });
    }

    /**
     * Drop the office-holiday calendar.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
