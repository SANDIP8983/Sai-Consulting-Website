<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('username', 50)->nullable()->unique()->after('name');
            $table->string('mobile', 10)->nullable()->unique()->after('email');
            $table->string('role', 30)->default('super_admin')->after('mobile')->index();
            $table->boolean('is_active')->default(true)->after('role')->index();
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        DB::table('users')->orderBy('id')->get(['id'])->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'username' => $user->id === DB::table('users')->min('id') ? 'admin' : 'user'.$user->id,
                'role' => 'super_admin',
                'is_active' => true,
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropUnique(['mobile']);
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['username', 'mobile', 'role', 'is_active', 'last_login_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
