<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentMail extends Mailable
{
    use Queueable,SerializesModels;

    public function __construct(public array $data) {}

    public function build(): self
    {
        return $this->subject($this->data['subject'])->view('emails.appointment', $this->data);
    }
}
