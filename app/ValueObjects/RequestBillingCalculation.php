<?php

namespace App\ValueObjects;

final readonly class RequestBillingCalculation
{
    public function __construct(
        public float $totalProfessionalFee,
        public string $discountType,
        public float $discountValue,
        public float $discountAmount,
        public float $netProfessionalFee,
        public float $gstRate,
        public float $gstAmount,
        public array $governmentCharges,
        public float $governmentChargesTotal,
        public float $grandTotal,
        public float $confirmedPaidAmount,
        public float $balanceDue,
        public bool $paymentRequired,
        public string $paymentStatus,
    ) {}

    /** @return array<string, mixed> */
    public function billingSnapshot(): array
    {
        return [
            'total_original_professional_fee' => $this->totalProfessionalFee,
            'discount_type' => $this->discountType,
            'discount_value' => $this->discountValue,
            'discount_amount' => $this->discountAmount,
            'net_professional_fee' => $this->netProfessionalFee,
            'gst_rate' => $this->gstRate,
            'gst_amount' => $this->gstAmount,
            'government_charges_total' => $this->governmentChargesTotal,
            'grand_total' => $this->grandTotal,
        ];
    }
}
