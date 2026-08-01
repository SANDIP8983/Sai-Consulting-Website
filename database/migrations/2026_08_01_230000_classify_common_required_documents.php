<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('common_required_documents', function (Blueprint $table): void {
            $table->boolean('is_common')->default(true)->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('common_required_documents', function (Blueprint $table): void {
            $table->dropIndex(['is_common']);
            $table->dropColumn('is_common');
        });
    }
};
