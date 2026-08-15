<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LAND_RECORD_CODES = [
        '7-12-extract',
        'property-card',
        'assessment-register-village-form-2',
    ];

    public function up(): void
    {
        DB::table('services')
            ->where('slug', 'legal-consulting')
            ->update(['requires_property_documents' => false, 'updated_at' => now()]);

        DB::table('service_required_documents')
            ->join('services', 'services.id', '=', 'service_required_documents.service_id')
            ->join('common_required_documents', 'common_required_documents.id', '=', 'service_required_documents.common_required_document_id')
            ->where('services.requires_property_documents', false)
            ->whereIn('common_required_documents.code', self::LAND_RECORD_CODES)
            ->where('service_required_documents.requirement_type', 'any_one_required')
            ->update([
                'service_required_documents.requirement_type' => 'optional',
                'service_required_documents.is_mandatory' => false,
                'service_required_documents.updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This correction intentionally does not recreate globally seeded requirements.
    }
};
