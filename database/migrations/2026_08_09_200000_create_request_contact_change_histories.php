<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_contact_change_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->restrictOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->json('changed_fields');
            $table->json('masked_old_values');
            $table->json('masked_new_values');
            $table->timestamp('changed_at')->index();
            $table->timestamps();
            $table->index(['request_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_contact_change_histories');
    }
};
