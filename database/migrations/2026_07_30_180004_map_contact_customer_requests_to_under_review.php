<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('requests')->where('status', 'contact_customer')->update(['status' => 'under_review']);
    }

    public function down(): void
    {
        // The original contact_customer value cannot be distinguished after migration.
    }
};
