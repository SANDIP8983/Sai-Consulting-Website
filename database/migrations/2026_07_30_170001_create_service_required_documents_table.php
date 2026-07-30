<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the structured list of documents required for each service.
     */
    public function up(): void
    {
        Schema::create('service_required_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name_en', 150);
            $table->string('name_gu', 150);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['service_id', 'sort_order']);
        });
    }

    /**
     * Drop the structured service document list.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_required_documents');
    }
};
