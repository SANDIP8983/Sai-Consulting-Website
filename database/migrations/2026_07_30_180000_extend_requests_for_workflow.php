<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->string('status', 50)->default('received')->change();
            $table->string('payment_status', 30)->default('not_required')->index();
            $table->decimal('amount_due', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->date('estimated_completion_date')->nullable()->index();
            $table->timestamp('last_status_changed_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['estimated_completion_date']);
            $table->dropIndex(['last_status_changed_at']);
            $table->dropColumn(['payment_status', 'amount_due', 'amount_paid', 'estimated_completion_date', 'last_status_changed_at']);
        });
    }
};
