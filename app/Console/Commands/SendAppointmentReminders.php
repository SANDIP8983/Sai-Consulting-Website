<?php

namespace App\Console\Commands;

use App\Enums\AppointmentNotificationMilestone;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Notifications\AppointmentNotificationService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Queue reminders for appointments occurring in approximately 24 hours';

    public function handle(AppointmentNotificationService $notifications): int
    {
        Appointment::whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Rescheduled])->whereNull('reminder_sent_at')->whereBetween('scheduled_at', [now()->addHours(23), now()->addHours(25)])->each(function ($a) use ($notifications) {
            if ($a->whereKey($a->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()])) {
                $notifications->send($a, AppointmentNotificationMilestone::Reminder);
            }
        });

        return self::SUCCESS;
    }
}
