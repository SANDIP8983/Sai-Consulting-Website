<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public static function active(): array
    {
        return [self::Pending->value, self::Confirmed->value, self::Rescheduled->value];
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
