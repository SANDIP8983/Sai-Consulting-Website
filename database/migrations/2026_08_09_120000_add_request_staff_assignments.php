<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->foreignId('assigned_user_id')->nullable()->after('case_approved_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by')->index();
        });

        Schema::create('request_assignment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->foreignId('previous_assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->index();
            $table->timestamps();
            $table->index(['request_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_assignment_histories');

        Schema::table('requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('assigned_at');
        });
    }
};
