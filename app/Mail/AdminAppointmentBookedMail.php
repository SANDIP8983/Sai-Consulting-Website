<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminAppointmentBookedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function build(): self
    {
        return $this->subject('New appointment request: '.$this->appointment->reference_no)
            ->view('emails.admin-appointment-booked');
    }
}
