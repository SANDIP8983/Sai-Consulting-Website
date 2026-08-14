<?php

namespace App\Enums;

enum NotificationMilestone: string
{
    case RequestReceived = 'request_received';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case PaymentPending = 'payment_pending';
    case PaymentReceived = 'payment_received_awaiting_assignment';
    case ProcessingStarted = 'processing_started';
    case DraftReady = 'draft_ready';
    case FinalDraftReady = 'final_draft_ready';
    case Completed = 'completed';
    case Dispatched = 'dispatched';
    case DeliveredClosed = 'delivered_closed';
    case AppointmentReceived = 'appointment_received';
    case AppointmentConfirmed = 'appointment_confirmed';
    case AppointmentRescheduled = 'appointment_rescheduled';
    case AppointmentCancelled = 'appointment_cancelled';
    case AppointmentReminder = 'appointment_reminder';

    public function label(): string
    {
        return match ($this) {
            self::RequestReceived => 'Request Received', self::Accepted => 'Accepted', self::Rejected => 'Rejected',
            self::PaymentPending => 'Payment Pending', self::PaymentReceived => 'Payment Received / Awaiting Assignment',
            self::ProcessingStarted => 'Processing Started', self::DraftReady => 'Draft Ready', self::FinalDraftReady => 'Final Draft Ready',
            self::Completed => 'Completed', self::Dispatched => 'Dispatched', self::DeliveredClosed => 'Delivered / Closed',
            self::AppointmentReceived => 'Appointment Booking Received', self::AppointmentConfirmed => 'Appointment Confirmed',
            self::AppointmentRescheduled => 'Appointment Rescheduled', self::AppointmentCancelled => 'Appointment Cancelled', self::AppointmentReminder => 'Appointment Reminder',
        };
    }

    public function defaults(): array
    {
        return ['email' => ! in_array($this, [self::ProcessingStarted, self::DraftReady], true), 'whatsapp' => true];
    }
}
