<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDispatchProof extends Model
{
    protected $fillable = ['request_dispatch_id', 'proof_type', 'file_name', 'file_path', 'mime_type', 'file_size', 'uploaded_by'];

    public function dispatch(): BelongsTo { return $this->belongsTo(RequestDispatch::class, 'request_dispatch_id'); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
