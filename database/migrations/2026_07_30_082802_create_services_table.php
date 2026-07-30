<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->string('name_en', 150);
            $table->string('name_gu', 150);
            $table->string('slug', 180)->unique();

            $table->decimal('service_fee', 10, 2)->nullable();
            $table->unsignedTinyInteger('advance_percentage')->default(100);

            $table->unsignedSmallInteger('estimated_days')->nullable();

            $table->longText('required_documents')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
