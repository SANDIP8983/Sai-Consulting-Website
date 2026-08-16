<?php

namespace App\Mail;

use App\Models\CustomerRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerFinalDocumentsMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array<int, array{name:string,url:string}> $documentLinks */
    public function __construct(public readonly CustomerRequest $customerRequest, public readonly array $documentLinks) {}

    public function build(): self
    {
        return $this->subject('Final documents available — '.$this->customerRequest->reference_no)
            ->view('emails.customer-final-documents');
    }
}
