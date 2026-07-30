<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestDocument extends Model
{
    protected $fillable = [

        'customer_request_id',
        'file_name',
        'file_path',

    ];

    public function request()
    {
        return $this->belongsTo(CustomerRequest::class);
    }
}