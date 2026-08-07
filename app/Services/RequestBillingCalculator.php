<?php

namespace App\Services;

use App\Models\RequestService;
use App\ValueObjects\RequestBillingCalculation;
use App\ValueObjects\RequestPaymentState;
use InvalidArgumentException;

final class RequestBillingCalculator
{
    /**
     * @param  iterable<RequestService>  $requestServices
     * @param  array<int, array{name: string, amount: numeric-string|int|float, note?: ?string, display_order?: int}>  $governmentCharges
     */
    public function calculate(
        iterable $requestServices,
        string $discountType,
        float $discountValue,
        float $gstRate,
        array $governmentCharges = [],
        float $confirmedPaidAmount = 0,
        bool $explicitNoPaymentRequired = false,
    ): RequestBillingCalculation {
        if (! in_array($discountType, ['none', 'fixed', 'percentage'], true)) {
            throw new InvalidArgumentException('Discount type must be none, fixed, or percentage.');
        }
        if ($discountValue < 0) {
            throw new InvalidArgumentException('Discount value cannot be negative.');
        }
        if ($discountType === 'percentage' && $discountValue > 100) {
            throw new InvalidArgumentException('Percentage discount cannot exceed 100.');
        }
        if ($gstRate < 0 || $gstRate > 100) {
            throw new InvalidArgumentException('GST rate must be between 0 and 100.');
        }
        if ($confirmedPaidAmount < 0) {
            throw new InvalidArgumentException('Confirmed paid amount cannot be negative.');
        }

        $totalProfessionalFee = 0.0;
        foreach ($requestServices as $requestService) {
            if ($requestService->status === 'approved') {
                $totalProfessionalFee += $requestService->billingProfessionalFee();
            }
        }
        $totalProfessionalFee = $this->money($totalProfessionalFee);
        $discountValue = $discountType === 'none' ? 0.0 : $this->money($discountValue);
        $discountAmount = match ($discountType) {
            'fixed' => $discountValue,
            'percentage' => $this->money($totalProfessionalFee * $discountValue / 100),
            default => 0.0,
        };
        if ($discountAmount > $totalProfessionalFee) {
            throw new InvalidArgumentException('Discount cannot exceed the total professional fee.');
        }

        $netProfessionalFee = $this->money($totalProfessionalFee - $discountAmount);
        $gstRate = $this->money($gstRate);
        $gstAmount = $this->money($netProfessionalFee * $gstRate / 100);
        $charges = $this->normalizeGovernmentCharges($governmentCharges);
        $governmentChargesTotal = $this->money(array_sum(array_column($charges, 'amount')));
        $grandTotal = $this->money($netProfessionalFee + $gstAmount + $governmentChargesTotal);
        $payment = $this->paymentState($grandTotal, $confirmedPaidAmount, $explicitNoPaymentRequired);

        return new RequestBillingCalculation(
            totalProfessionalFee: $totalProfessionalFee,
            discountType: $discountType,
            discountValue: $discountValue,
            discountAmount: $discountAmount,
            netProfessionalFee: $netProfessionalFee,
            gstRate: $gstRate,
            gstAmount: $gstAmount,
            governmentCharges: $charges,
            governmentChargesTotal: $governmentChargesTotal,
            grandTotal: $grandTotal,
            confirmedPaidAmount: $payment->confirmedPaidAmount,
            balanceDue: $payment->balanceDue,
            paymentRequired: $payment->paymentRequired,
            paymentStatus: $payment->status,
        );
    }

    public function paymentState(float $payableAmount, float $confirmedPaidAmount, bool $explicitNoPaymentRequired = false): RequestPaymentState
    {
        $payableAmount = $this->money(max(0, $payableAmount));
        $confirmedPaidAmount = $this->money(max(0, $confirmedPaidAmount));
        $paymentRequired = $payableAmount > 0 && ! $explicitNoPaymentRequired;
        $balanceDue = $paymentRequired ? $this->money(max(0, $payableAmount - $confirmedPaidAmount)) : 0.0;
        $status = match (true) {
            ! $paymentRequired => 'not_required',
            $balanceDue === 0.0 => 'paid',
            $confirmedPaidAmount > 0 => 'partial',
            default => 'pending',
        };

        return new RequestPaymentState($payableAmount, $confirmedPaidAmount, $balanceDue, $paymentRequired, $status);
    }

    /**
     * @param  array<int, array{name: string, amount: numeric-string|int|float, note?: ?string, display_order?: int}>  $charges
     * @return array<int, array{name: string, amount: float, note: ?string, display_order: int}>
     */
    private function normalizeGovernmentCharges(array $charges): array
    {
        $normalized = [];
        foreach (array_values($charges) as $index => $charge) {
            $amount = (float) $charge['amount'];
            if ($amount < 0) {
                throw new InvalidArgumentException('Government charges cannot be negative.');
            }
            $normalized[] = [
                'name' => $charge['name'],
                'amount' => $this->money($amount),
                'note' => $charge['note'] ?? null,
                'display_order' => (int) ($charge['display_order'] ?? $index),
            ];
        }

        return $normalized;
    }

    private function money(float $amount): float
    {
        return round($amount, 2);
    }
}
