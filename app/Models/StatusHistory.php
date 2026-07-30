<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusHistory extends Model
{
    protected $table = 'request_status_histories';
    protected $fillable = [

        'request_id', 'from_status', 'to_status', 'remarks', 'is_visible_to_customer', 'changed_by',

    ];

    protected function casts(): array { return ['is_visible_to_customer' => 'boolean']; }
    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }
}
