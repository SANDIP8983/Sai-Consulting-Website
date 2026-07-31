<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->text('address')->nullable()->after('email');
        });

        DB::table('requests')
            ->select(['id', 'village', 'taluka', 'district'])
            ->orderBy('id')
            ->chunkById(100, function ($requests): void {
                foreach ($requests as $request) {
                    $address = collect([$request->village, $request->taluka, $request->district])
                        ->filter(fn ($value) => filled($value))
                        ->implode(', ');

                    if ($address !== '') {
                        DB::table('requests')->where('id', $request->id)->update(['address' => $address]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropColumn('address');
        });
    }
};
