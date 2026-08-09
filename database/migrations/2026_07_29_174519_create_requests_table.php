<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {

            $table->id();

            $table->string('reference_no')->unique();

            // The services table is created by a later historical migration.
            // Its foreign key is added after both tables exist.
            $table->foreignId('service_id')->index();

            $table->string('name');
            $table->string('mobile', 15);
            $table->string('email')->nullable();

            $table->string('village')->nullable();
            $table->string('taluka')->nullable();
            $table->string('district')->nullable();

            $table->text('survey_numbers')->nullable();
            $table->string('khata_number')->nullable();

            $table->text('details')->nullable();

            $table->enum('status', [
                'received',
                'under_review',
                'need_documents',
                'contact_customer',
                'completed',
            ])->default('received');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
