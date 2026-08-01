<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('services', function (Blueprint $table): void { $table->decimal('gst_rate', 5, 2)->default(0)->after('service_fee'); $table->decimal('government_charges', 10, 2)->default(0)->after('gst_rate'); }); }
 public function down(): void { Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['gst_rate', 'government_charges'])); }
};
