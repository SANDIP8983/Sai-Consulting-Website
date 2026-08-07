<?php

namespace App\ValueObjects;

final readonly class RequestBillingState
{
    public function __construct(
        public string $lifecycle,
        public bool $hasFrozenBilling,
        public bool $legacy,
        public ?float $professionalFee,
        public ?float $discountAmount,
        public ?float $netProfessionalFee,
        public ?float $gstRate,
        public ?float $gstAmount,
        public ?float $governmentChargesTotal,
        public ?float $grandTotal,
        public float $confirmedPaidAmount,
        public ?float $balanceDue,
        public bool $paymentRequired,
        public string $paymentStatus,
        public bool $pricingLocked,
    ) {}

    public function canRecordPayment(): bool
    {
        return ($this->hasFrozenBilling || $this->legacy)
            && $this->paymentRequired
            && $this->balanceDue > 0;
    }
}
