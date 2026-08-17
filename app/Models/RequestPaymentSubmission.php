<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestPaymentSubmission extends Model
{
    protected $fillable = [
        'request_id', 'utr_reference', 'amount', 'proof_path', 'proof_original_name',
        'proof_mime_type', 'proof_file_size', 'status', 'submitted_at', 'reviewed_at',
        'reviewed_by', 'review_note', 'payment_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(RequestPayment::class, 'payment_id');
    }
}
