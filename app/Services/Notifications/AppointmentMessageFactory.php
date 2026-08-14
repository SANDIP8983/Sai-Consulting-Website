<?php

namespace App\Services\Notifications;

use App\Enums\NotificationMilestone;
use App\Models\Appointment;

class AppointmentMessageFactory
{
    public function make(Appointment $appointment, NotificationMilestone $milestone): array
    {
        $appointment->loadMissing('service');
        $status = match ($milestone) {
            NotificationMilestone::AppointmentReceived => 'તમારી મુલાકાત વિનંતી મળી ગઈ છે. / Your appointment request has been received and is pending confirmation.',
            NotificationMilestone::AppointmentConfirmed => 'તમારી મુલાકાત નિશ્ચિત થઈ છે. / Your appointment is confirmed.',
            NotificationMilestone::AppointmentRescheduled => 'તમારી મુલાકાતનો સમય બદલાયો છે. / Your appointment has been rescheduled.',
            NotificationMilestone::AppointmentCancelled => 'તમારી મુલાકાત રદ થઈ છે. / Your appointment has been cancelled.',
            NotificationMilestone::AppointmentReminder => 'તમારી મુલાકાત માટે યાદ અપાવીએ છીએ. / This is a reminder for your appointment.',
            default => throw new \LogicException('A customer request milestone cannot be used for an appointment.'),
        };
        $at = $appointment->scheduled_at;
        $body = implode("\n", [
            "નમસ્તે {$appointment->customer_name},",
            $status,
            "Reference: {$appointment->reference_no}",
            "Service: {$appointment->service->name_en}",
            'Date: '.$at->format('d M Y'),
            'Time: '.$at->format('g:i A'),
            'Status: '.$appointment->status->label(),
            'Please arrive on time. કૃપા કરીને સમયસર આવશો.',
        ]);

        return ['subject' => 'Sai Consulting — '.$milestone->label().' — '.$appointment->reference_no, 'body' => $body, 'template_key' => 'customer_'.$milestone->value, 'parameters' => [$appointment->customer_name, $appointment->reference_no, $at->format('d M Y'), $at->format('g:i A')]];
    }
}
