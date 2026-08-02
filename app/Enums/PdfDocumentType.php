<?php

namespace App\Enums;

enum PdfDocumentType: string
{
    case RequestAcknowledgement = 'request-acknowledgement';
    case PaymentSummary = 'payment-summary';
    case CaseSummary = 'case-summary';
    case DispatchSlip = 'dispatch-slip';

    public function title(): string
    {
        return match ($this) {
            self::RequestAcknowledgement => 'Request Acknowledgement',
            self::PaymentSummary => 'Payment Summary',
            self::CaseSummary => 'Case Summary',
            self::DispatchSlip => 'Dispatch Slip',
        };
    }

    public function view(): string
    {
        return 'pdf.documents.'.$this->value;
    }
}
