<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDispatch extends Model
{
    protected $fillable = ['request_id', 'dispatch_status', 'dispatch_method', 'dispatch_date', 'tracking_number', 'carrier_name', 'internal_note', 'customer_remark', 'performed_by'];

    protected function casts(): array
    {
        return ['dispatch_date' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
