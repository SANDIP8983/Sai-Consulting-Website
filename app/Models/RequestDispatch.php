<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestDispatch extends Model
{
    protected $fillable = ['request_id', 'dispatch_status', 'dispatch_method', 'dispatch_date', 'document_description', 'recipient_name', 'recipient_mobile', 'recipient_email', 'delivery_address', 'tracking_number', 'tracking_url', 'carrier_name', 'method_description', 'delivered_at', 'collected_at', 'failure_reason', 'cancellation_reason', 'internal_note', 'customer_remark', 'performed_by', 'updated_by'];

    protected function casts(): array
    {
        return ['dispatch_date' => 'datetime', 'delivered_at' => 'datetime', 'collected_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(RequestDispatchProof::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(RequestDispatchHistory::class);
    }
}
