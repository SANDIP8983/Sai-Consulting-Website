<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerRequest extends Model
{
    /**
     * Laravel ને જણાવો કે આ Model "requests" table વાપરે છે.
     */
    protected $table = 'requests';

    /**
     * Mass Assignable Fields
     */
    protected $fillable = [
        'reference_no',
        'submission_fingerprint',
        'file_number',
        'request_origin',
        'service_id',
        'name',
        'mobile',
        'whatsapp',
        'email',
        'address',
        'village', 'taluka', 'district',
        'property_village', 'property_taluka', 'property_district', 'property_address_remarks',
        'survey_numbers',
        'khata_number',
        'tp_number', 'final_plot_number', 'revenue_village',
        'details',
        'status',
        'payment_status', 'amount_due', 'fee_updated_by', 'fee_updated_at', 'amount_paid', 'estimated_completion_date', 'last_status_changed_at',
    ];

    protected function casts(): array
    {
        return ['amount_due' => 'decimal:2', 'amount_paid' => 'decimal:2', 'fee_updated_at' => 'datetime', 'estimated_completion_date' => 'date', 'last_status_changed_at' => 'datetime'];
    }

    /**
     * Service Relationship
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function requestServices(): HasMany
    {
        return $this->hasMany(RequestService::class, 'request_id');
    }

    public function billing(): HasOne
    {
        return $this->hasOne(RequestBilling::class, 'request_id');
    }

    /**
     * Uploaded Documents
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RequestDocument::class, 'request_id');
    }

    /**
     * Status History
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'request_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RequestPayment::class, 'request_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(RequestDispatch::class, 'request_id')->latest('dispatch_date');
    }

    public function processing(): HasOne
    {
        return $this->hasOne(RequestProcessingDetail::class, 'request_id');
    }

    public function processingHistory(): HasMany
    {
        return $this->hasMany(RequestProcessingHistory::class, 'request_id');
    }

    public function feeUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fee_updated_by');
    }

    public function isOffline(): bool
    {
        return $this->request_origin === 'offline';
    }
}
