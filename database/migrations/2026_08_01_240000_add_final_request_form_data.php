<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->string('whatsapp', 15)->nullable()->after('mobile');
            $table->string('submission_fingerprint', 64)->nullable()->unique()->after('reference_no');
        });
        Schema::create('service_government_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('name', 150);
            $table->decimal('amount', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::table('request_services', function (Blueprint $table): void {
            $table->json('government_charges_snapshot')->nullable()->after('government_charges');
        });
    }

    public function down(): void
    {
        Schema::table('request_services', fn (Blueprint $table) => $table->dropColumn('government_charges_snapshot'));
        Schema::dropIfExists('service_government_charges');
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropUnique(['submission_fingerprint']);
            $table->dropColumn(['whatsapp', 'submission_fingerprint']);
        });
    }
};
