<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusHistory extends Model
{
    protected $fillable = [

        'customer_request_id',
        'status',
        'remarks',

    ];

    public function request()
    {
        return $this->belongsTo(CustomerRequest::class);
    }
}