<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            $table->foreignId('service_required_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->default('customer');
            $table->boolean('is_verified')->default(false)->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            $table->dropForeign(['service_required_document_id']);
            $table->dropForeign(['verified_by']);
            $table->dropIndex(['is_verified']);
            $table->dropColumn(['service_required_document_id', 'source', 'is_verified', 'verified_by', 'verified_at', 'verification_notes']);
        });
    }
};
