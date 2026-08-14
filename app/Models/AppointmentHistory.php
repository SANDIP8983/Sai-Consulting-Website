<?php

namespace App\Models;

use App\Casts\AppointmentDateTime;
use Illuminate\Database\Eloquent\Model;

class AppointmentHistory extends Model
{
    protected $fillable = ['appointment_id', 'action', 'old_status', 'new_status', 'old_scheduled_at', 'new_scheduled_at', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['old_scheduled_at' => AppointmentDateTime::class, 'new_scheduled_at' => AppointmentDateTime::class];
    }
}
