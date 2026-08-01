<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('uses_drafting_workflow')->default(false);
            $table->boolean('requires_token_booking')->default(false);
            $table->boolean('requires_registration')->default(false);
            $table->boolean('requires_certified_copy')->default(false);
        });

        DB::table('services')->whereIn('slug', [
            'sale-deed', 'relinquishment-deed', 'partition-deed', 'rent-agreement',
            'power-of-attorney', 'gift-deed', 'mortgage', 'mortgage-release',
            'banakhat-agreement-to-sell',
        ])->update([
            'uses_drafting_workflow' => true,
            'requires_token_booking' => true,
            'requires_registration' => true,
            'requires_certified_copy' => true,
        ]);

        DB::table('services')->where('slug', 'sub-registrar-office-token-booking')
            ->update(['requires_token_booking' => true]);
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['uses_drafting_workflow', 'requires_token_booking', 'requires_registration', 'requires_certified_copy']);
        });
    }
};
