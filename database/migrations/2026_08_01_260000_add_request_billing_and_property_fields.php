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
            $table->string('property_village')->nullable()->after('district');
            $table->string('property_taluka')->nullable()->after('property_village');
            $table->string('property_district')->nullable()->after('property_taluka');
            $table->text('property_address_remarks')->nullable()->after('property_district');
            $table->string('tp_number', 100)->nullable()->after('khata_number');
            $table->string('final_plot_number', 100)->nullable()->after('tp_number');
            $table->string('revenue_village')->nullable()->after('final_plot_number');
        });

        Schema::table('request_services', function (Blueprint $table): void {
            $table->string('service_name_en_snapshot')->nullable()->after('service_id');
            $table->string('service_name_gu_snapshot')->nullable()->after('service_name_en_snapshot');
        });

        Schema::create('request_billings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('requests')->cascadeOnDelete();
            $table->decimal('total_original_professional_fee', 12, 2);
            $table->string('discount_type', 20)->default('none');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('discount_reason', 50)->nullable();
            $table->text('internal_note')->nullable();
            $table->decimal('net_professional_fee', 12, 2);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('government_charges_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('pricing_locked_at')->nullable();
            $table->timestamp('pricing_unlocked_at')->nullable();
            $table->foreignId('pricing_unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('request_billing_government_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_billing_id')->constrained('request_billings')->cascadeOnDelete();
            $table->string('name', 150);
            $table->decimal('amount', 12, 2);
            $table->string('note', 500)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('request_billing_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_billing_id')->constrained('request_billings')->cascadeOnDelete();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->json('pricing_snapshot');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        DB::table('request_services')->orderBy('id')->chunkById(200, function ($rows): void {
            $names = DB::table('services')->whereIn('id', $rows->pluck('service_id'))->get(['id', 'name_en', 'name_gu'])->keyBy('id');
            foreach ($rows as $row) {
                $service = $names->get($row->service_id);
                if ($service) {
                    $snapshot = [
                        'service_name_en_snapshot' => $service->name_en,
                        'service_name_gu_snapshot' => $service->name_gu,
                    ];
                    if ($row->original_professional_fee === null) {
                        $snapshot['original_professional_fee'] = $row->professional_fee;
                    }
                    DB::table('request_services')->where('id', $row->id)->update($snapshot);
                }
            }
        });

        DB::table('requests')->orderBy('id')->chunkById(200, function ($requests): void {
            foreach ($requests as $request) {
                $propertyRelated = DB::table('services')->where('id', $request->service_id)->where('requires_property_documents', true)->exists()
                    || DB::table('request_services')->join('services', 'services.id', '=', 'request_services.service_id')->where('request_services.request_id', $request->id)->where('services.requires_property_documents', true)->exists();
                if ($propertyRelated) {
                    DB::table('requests')->where('id', $request->id)->update([
                        'property_village' => $request->village,
                        'property_taluka' => $request->taluka,
                        'property_district' => $request->district,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_billing_histories');
        Schema::dropIfExists('request_billing_government_charges');
        Schema::dropIfExists('request_billings');
        Schema::table('request_services', fn (Blueprint $table) => $table->dropColumn(['service_name_en_snapshot', 'service_name_gu_snapshot']));
        Schema::table('requests', fn (Blueprint $table) => $table->dropColumn(['property_village', 'property_taluka', 'property_district', 'property_address_remarks', 'tp_number', 'final_plot_number', 'revenue_village']));
    }
};
