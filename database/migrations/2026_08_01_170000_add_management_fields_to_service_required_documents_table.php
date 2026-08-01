<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_required_documents', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('is_mandatory')->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('service_required_documents', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
        });
    }
};
