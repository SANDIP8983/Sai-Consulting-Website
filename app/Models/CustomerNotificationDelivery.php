<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNotificationDelivery extends Model
{
    protected $fillable = ['notification_event_id', 'channel', 'status', 'provider', 'provider_message_id', 'recipient_masked', 'recipient_hash', 'template_key', 'attempt_count', 'failure_category', 'failure_message', 'queued_at', 'last_attempt_at', 'sent_at', 'failed_at'];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'last_attempt_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(CustomerNotificationEvent::class, 'notification_event_id');
    }
}
