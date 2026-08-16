<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RequestFinalDocument extends Model
{
    protected $fillable = ['request_id', 'original_name', 'storage_path', 'mime_type', 'file_size', 'uploaded_by'];

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deliveries(): BelongsToMany
    {
        return $this->belongsToMany(RequestFinalDocumentDelivery::class, 'request_final_document_delivery_items', 'final_document_id', 'delivery_id')->withTimestamps();
    }
}
