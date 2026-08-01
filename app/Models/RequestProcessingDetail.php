<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestProcessingDetail extends Model
{
    protected $fillable = [
        'request_id', 'processing_stage', 'file_opened_at', 'priority', 'file_in_charge_user_id',
        'internal_file_note', 'actual_completion_date', 'uses_drafting_workflow',
        'requires_token_booking', 'requires_registration', 'requires_certified_copy',
        'requires_dispatch', 'requires_payment_before_processing',
        'draft_started_at', 'draft_ready_at', 'customer_verification_at', 'correction_note',
        'final_draft_at', 'drafting_internal_note', 'drafting_customer_remark',
        'token_booking_status', 'token_number', 'token_scheduled_at', 'sub_registrar_office_name',
        'registration_appointment_at', 'registration_date', 'registration_number',
        'registration_number_public', 'registration_internal_note', 'registration_customer_remark',
        'registered_scan_received_at', 'registered_document_id', 'certified_copy_status',
        'certified_copy_received_date', 'ready_for_dispatch_date',
    ];

    protected function casts(): array
    {
        return [
            'file_opened_at' => 'date', 'actual_completion_date' => 'date',
            'uses_drafting_workflow' => 'boolean', 'requires_token_booking' => 'boolean',
            'requires_registration' => 'boolean', 'requires_certified_copy' => 'boolean',
            'requires_dispatch' => 'boolean', 'requires_payment_before_processing' => 'boolean',
            'draft_started_at' => 'date', 'draft_ready_at' => 'date',
            'customer_verification_at' => 'date', 'final_draft_at' => 'date',
            'token_scheduled_at' => 'datetime', 'registration_appointment_at' => 'datetime',
            'registration_date' => 'date', 'registration_number_public' => 'boolean',
            'registered_scan_received_at' => 'datetime', 'certified_copy_received_date' => 'date',
            'ready_for_dispatch_date' => 'date',
        ];
    }

    public function request(): BelongsTo { return $this->belongsTo(CustomerRequest::class, 'request_id'); }
    public function fileInCharge(): BelongsTo { return $this->belongsTo(User::class, 'file_in_charge_user_id'); }
    public function registeredDocument(): BelongsTo { return $this->belongsTo(RequestDocument::class, 'registered_document_id'); }
}
