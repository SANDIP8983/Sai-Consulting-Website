<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RequestFinalDocumentDelivery extends Model
{
    protected $fillable = ['request_id', 'channel', 'status', 'recipient_masked', 'recipient_hash', 'idempotency_key', 'attempt_count', 'initiated_by', 'failure_category', 'failure_message', 'queued_at', 'last_attempt_at', 'sent_at', 'failed_at'];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'last_attempt_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(RequestFinalDocument::class, 'request_final_document_delivery_items', 'delivery_id', 'final_document_id')->withTimestamps();
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
