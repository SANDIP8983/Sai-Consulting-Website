<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestRevenueEntryDocument extends Model
{
    protected $fillable = ['request_revenue_entry_id', 'document_type', 'original_name', 'file_path', 'mime_type', 'file_size', 'uploaded_by'];

    public function revenueEntry(): BelongsTo
    {
        return $this->belongsTo(RequestRevenueEntry::class, 'request_revenue_entry_id');
    }
}
