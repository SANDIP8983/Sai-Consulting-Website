<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_add_ons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_service_id')->constrained('services')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['service_id', 'add_on_service_id']);
        });

        Schema::create('government_charge_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name_en', 150)->unique();
            $table->string('name_gu', 150)->nullable();
            $table->decimal('default_amount', 12, 2)->default(0);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('request_billing_government_charges', function (Blueprint $table): void {
            $table->foreignId('government_charge_type_id')->nullable()->after('request_billing_id')->constrained('government_charge_types')->nullOnDelete();
            $table->string('name_gu', 150)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('request_billing_government_charges', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('government_charge_type_id');
            $table->dropColumn('name_gu');
        });
        Schema::dropIfExists('government_charge_types');
        Schema::dropIfExists('service_add_ons');
    }
};
