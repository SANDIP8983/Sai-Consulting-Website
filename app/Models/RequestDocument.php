<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDocument extends Model
{
    protected $fillable = [

        'request_id', 'service_required_document_id', 'file_name', 'file_path', 'file_type', 'file_size', 'source', 'is_verified', 'verified_by', 'verified_at', 'verification_notes',

    ];

    protected function casts(): array { return ['is_verified' => 'boolean', 'verified_at' => 'datetime']; }
    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function requiredDocument(): BelongsTo
    {
        return $this->belongsTo(ServiceRequiredDocument::class, 'service_required_document_id')->withTrashed();
    }
}
