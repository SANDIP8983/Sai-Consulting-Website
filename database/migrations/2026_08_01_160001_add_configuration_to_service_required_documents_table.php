<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_required_documents', function (Blueprint $table): void {
            $table->boolean('is_mandatory')->default(true);
            $table->json('allowed_file_types')->nullable();
            $table->unsignedInteger('max_upload_size_kb')->default(10240);
        });
    }

    public function down(): void
    {
        Schema::table('service_required_documents', function (Blueprint $table): void {
            $table->dropColumn(['is_mandatory', 'allowed_file_types', 'max_upload_size_kb']);
        });
    }
};
