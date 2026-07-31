<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'file_number',
        'service_id',
        'name',
        'mobile',
        'email',
        'address',
        'village',
        'taluka',
        'district',
        'survey_numbers',
        'khata_number',
        'details',
        'status',
        'payment_status', 'amount_due', 'amount_paid', 'estimated_completion_date', 'last_status_changed_at',
    ];

    protected function casts(): array
    {
        return ['amount_due' => 'decimal:2', 'amount_paid' => 'decimal:2', 'estimated_completion_date' => 'date', 'last_status_changed_at' => 'datetime'];
    }

    /**
     * Service Relationship
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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
}
