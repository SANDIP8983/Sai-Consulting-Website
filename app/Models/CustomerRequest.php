<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'service_id',
        'name',
        'mobile',
        'email',
        'village',
        'taluka',
        'district',
        'survey_numbers',
        'khata_number',
        'details',
        'status',
    ];

    /**
     * Service Relationship
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Uploaded Documents
     */
    public function documents()
    {
        return $this->hasMany(RequestDocument::class);
    }

    /**
     * Status History
     */
    public function statusHistory()
    {
        return $this->hasMany(StatusHistory::class);
    }
}