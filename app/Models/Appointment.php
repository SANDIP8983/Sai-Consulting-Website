<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    protected $fillable = ['reference_no', 'customer_name', 'mobile', 'whatsapp', 'email', 'service_id', 'scheduled_at', 'duration_minutes', 'status', 'source', 'customer_note', 'admin_note', 'slot_key', 'reminder_sent_at', 'confirmed_at', 'completed_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'status' => AppointmentStatus::class, 'reminder_sent_at' => 'datetime', 'confirmed_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AppointmentHistory::class)->latest();
    }
}
