<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the typed, grouped configuration fields to the existing settings table.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('setting_key', 100)
                ->unique()
                ->comment('Unique setting identifier, for example website.status');
            $table->text('setting_value')->nullable();
            $table->string('value_type', 30)
                ->default('string')
                ->comment('Expected value format, such as string, boolean, integer, or json');
            $table->string('setting_group', 50)
                ->default('general')
                ->comment('Logical grouping used by the administration interface');
            $table->boolean('is_public')
                ->default(false)
                ->comment('Whether the setting may be displayed on the public website');

            $table->index(['setting_group', 'is_public']);
        });
    }

    /**
     * Remove the configuration fields added to the existing settings table.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex(['setting_group', 'is_public']);
            $table->dropUnique(['setting_key']);
            $table->dropColumn([
                'setting_key',
                'setting_value',
                'value_type',
                'setting_group',
                'is_public',
            ]);
        });
    }
};
