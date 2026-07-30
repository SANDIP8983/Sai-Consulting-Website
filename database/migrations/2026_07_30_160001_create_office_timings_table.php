<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the weekly office schedule.
     */
    public function up(): void
    {
        Schema::create('office_timings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')
                ->unique()
                ->comment('0 = Sunday through 6 = Saturday');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Drop the weekly office schedule.
     */
    public function down(): void
    {
        Schema::dropIfExists('office_timings');
    }
};
