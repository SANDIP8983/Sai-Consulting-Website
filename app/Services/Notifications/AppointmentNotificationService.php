<?php

namespace App\Services\Notifications;

use App\Enums\AppointmentNotificationMilestone;
use App\Jobs\SendAppointmentNotificationJob;
use App\Models\Appointment;
use App\Models\Setting;

class AppointmentNotificationService
{
    public function send(Appointment $appointment, AppointmentNotificationMilestone $milestone): void
    {
        foreach (['email', 'whatsapp'] as $channel) {
            $default = $channel === 'email';
            $stored = Setting::query()->where('setting_key', "notifications.{$milestone->value}.{$channel}")->value('setting_value');
            if (($stored === null ? $default : filter_var($stored, FILTER_VALIDATE_BOOL)) && ($channel === 'email' ? $appointment->email : ($appointment->whatsapp ?: $appointment->mobile))) {
                SendAppointmentNotificationJob::dispatch($appointment->id, $milestone, $channel)->onQueue(config('customer-notifications.queue'));
            }
        }
    }
}
