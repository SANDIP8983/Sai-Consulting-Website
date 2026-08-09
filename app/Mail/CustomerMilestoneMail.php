<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerMilestoneMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $messageData) {}

    public function build(): self
    {
        return $this->subject($this->messageData['subject'])->view('emails.customer-milestone', ['messageData' => $this->messageData]);
    }
}
