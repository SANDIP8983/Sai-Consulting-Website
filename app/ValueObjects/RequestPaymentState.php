<?php

namespace App\ValueObjects;

final readonly class RequestPaymentState
{
    public function __construct(
        public float $payableAmount,
        public float $confirmedPaidAmount,
        public float $balanceDue,
        public bool $paymentRequired,
        public string $status,
    ) {}
}
