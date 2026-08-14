<?php

namespace App\Models;

use App\Enums\NotificationMilestone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerNotificationEvent extends Model
{
    protected $fillable = ['request_id', 'appointment_id', 'milestone', 'event_key', 'source_type', 'source_id', 'safe_context', 'occurred_at'];

    protected function casts(): array
    {
        return ['milestone' => NotificationMilestone::class, 'safe_context' => 'array', 'occurred_at' => 'datetime'];
    }

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'request_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CustomerNotificationDelivery::class, 'notification_event_id');
    }
}
