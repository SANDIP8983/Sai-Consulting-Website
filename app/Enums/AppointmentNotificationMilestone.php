<?php

namespace App\Enums;

enum AppointmentNotificationMilestone: string
{
    case Received = 'appointment_received';
    case Confirmed = 'appointment_confirmed';
    case Rescheduled = 'appointment_rescheduled';
    case Cancelled = 'appointment_cancelled';
    case Reminder = 'appointment_reminder';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Appointment Booking Received', self::Confirmed => 'Appointment Confirmed', self::Rescheduled => 'Appointment Rescheduled', self::Cancelled => 'Appointment Cancelled', self::Reminder => 'Appointment Reminder'
        };
    }
}
