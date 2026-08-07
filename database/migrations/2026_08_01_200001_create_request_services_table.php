<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->decimal('professional_fee', 10, 2)->default(0);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('government_charges', 10, 2)->default(0);
            $table->unsignedSmallInteger('estimated_days')->nullable();
            $table->json('required_documents_snapshot')->nullable();
            $table->string('status', 50)->default('received')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['request_id', 'service_id']);
        });
        DB::table('requests')->orderBy('id')->each(function (object $request): void {
            $service = DB::table('services')->where('id', $request->service_id)->first();
            if (! $service) {
                return;
            }
            $documents = DB::table('service_required_documents')->where('service_id', $service->id)->where('is_active', true)->whereNull('deleted_at')->orderBy('sort_order')->get(['id', 'name_en', 'name_gu', 'is_mandatory', 'sort_order'])->map(fn ($document) => (array) $document)->all();
            DB::table('request_services')->insert(['request_id' => $request->id, 'service_id' => $service->id, 'professional_fee' => $service->service_fee ?? 0, 'gst_rate' => $service->gst_rate ?? 0, 'government_charges' => $service->government_charges ?? 0, 'estimated_days' => $service->estimated_days, 'required_documents_snapshot' => json_encode($documents), 'status' => $request->status, 'created_at' => $request->created_at, 'updated_at' => $request->updated_at]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_services');
    }
};
