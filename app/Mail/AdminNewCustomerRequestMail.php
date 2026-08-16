<?php

namespace App\Mail;

use App\Models\CustomerRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminNewCustomerRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly CustomerRequest $customerRequest) {}

    public function build(): self
    {
        return $this->subject('New customer request: '.$this->customerRequest->reference_no)
            ->view('emails.admin-new-customer-request');
    }
}
