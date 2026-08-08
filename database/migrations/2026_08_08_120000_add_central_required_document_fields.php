<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('common_required_documents', function (Blueprint $table): void {
            $table->string('code', 100)->nullable()->unique()->after('id');
            $table->unsignedInteger('display_order')->default(0)->after('is_common');
        });
        Schema::table('service_required_documents', function (Blueprint $table): void {
            $table->string('requirement_type', 30)->default('optional')->after('is_mandatory')->index();
        });

        DB::table('service_required_documents')->where('is_mandatory', true)->update(['requirement_type' => 'required']);
        DB::table('service_required_documents')->where('is_active', false)->update(['requirement_type' => 'not_applicable']);
    }

    public function down(): void
    {
        Schema::table('service_required_documents', function (Blueprint $table): void {
            $table->dropIndex(['requirement_type']);
            $table->dropColumn('requirement_type');
        });
        Schema::table('common_required_documents', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'display_order']);
        });
    }
};
