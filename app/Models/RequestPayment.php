<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestPayment extends Model
{
    protected $fillable = ['request_id', 'amount', 'payment_status', 'payment_method', 'transaction_reference', 'received_at', 'received_by', 'notes', 'customer_remark'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
