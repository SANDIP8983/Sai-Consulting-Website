<?php

namespace App\Enums;

use App\Models\CustomerRequest;

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

    public function isCustomerAvailable(CustomerRequest $request): bool
    {
        return match ($this) {
            self::RequestAcknowledgement => true,
            self::PaymentSummary => $request->billing()->exists()
                || $request->requestServices()->whereNotNull('pricing_locked_at')->exists(),
            self::CaseSummary => $request->completed_at !== null
                || in_array($request->status, ['completed', 'dispatched', 'delivered', 'closed', 'archived'], true),
            self::DispatchSlip => $request->dispatches()->exists(),
        };
    }
}
