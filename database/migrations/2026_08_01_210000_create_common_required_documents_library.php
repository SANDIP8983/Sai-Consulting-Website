<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('common_required_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('name_en', 150);
            $table->string('name_gu', 150);
            $table->string('normalized_name', 160)->unique();
            $table->json('allowed_file_types')->nullable();
            $table->unsignedInteger('max_upload_size_kb')->default(10240);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('service_required_documents', function (Blueprint $table): void {
            $table->foreignId('common_required_document_id')->nullable()->after('service_id')->constrained('common_required_documents')->nullOnDelete();
        });

        DB::table('service_required_documents')->whereNull('deleted_at')->orderBy('id')->each(function (object $document): void {
            $normalized = Str::of($document->name_en)->trim()->lower()->squish()->value();
            $master = DB::table('common_required_documents')->where('normalized_name', $normalized)->first();
            $masterId = $master?->id ?? DB::table('common_required_documents')->insertGetId([
                'name_en' => $document->name_en,
                'name_gu' => $document->name_gu,
                'normalized_name' => $normalized,
                'allowed_file_types' => $document->allowed_file_types,
                'max_upload_size_kb' => $document->max_upload_size_kb,
                'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('service_required_documents')->where('id', $document->id)->update(['common_required_document_id' => $masterId]);
        });

        $services = DB::table('services')->pluck('id');
        $masters = DB::table('common_required_documents')->get();
        foreach ($services as $serviceId) {
            foreach ($masters as $master) {
                if (! DB::table('service_required_documents')->where('service_id', $serviceId)->where('common_required_document_id', $master->id)->whereNull('deleted_at')->exists()) {
                    DB::table('service_required_documents')->insert([
                        'service_id' => $serviceId, 'common_required_document_id' => $master->id,
                        'name_en' => $master->name_en, 'name_gu' => $master->name_gu,
                        'is_mandatory' => false, 'is_active' => false, 'sort_order' => 999,
                        'allowed_file_types' => $master->allowed_file_types, 'max_upload_size_kb' => $master->max_upload_size_kb,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
        Schema::table('service_required_documents', fn (Blueprint $table) => $table->unique(['service_id', 'common_required_document_id'], 'service_common_document_unique'));
    }

    public function down(): void
    {
        Schema::table('service_required_documents', function (Blueprint $table): void {
            $table->dropUnique('service_common_document_unique');
            $table->dropConstrainedForeignId('common_required_document_id');
        });
        Schema::dropIfExists('common_required_documents');
    }
};
