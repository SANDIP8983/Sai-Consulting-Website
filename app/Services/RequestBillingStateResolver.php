<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\RequestBilling;
use App\ValueObjects\RequestBillingState;
use App\ValueObjects\RequestPaymentState;

final class RequestBillingStateResolver
{
    public function __construct(private readonly RequestBillingCalculator $calculator) {}

    public function resolve(CustomerRequest $request): RequestBillingState
    {
        $request->loadMissing(['billing.history', 'payments', 'requestServices']);
        $billing = $request->billing;
        $received = (float) $request->payments->where('payment_status', 'received')->sum('amount');
        $refunded = (float) $request->payments->where('payment_status', 'refunded')->sum('amount');
        $confirmedPaid = round(max(0, $received - $refunded), 2);

        if ($billing) {
            $payment = $this->calculator->paymentState((float) $billing->grand_total, $confirmedPaid);
            $hasHistoricalSavedSnapshot = $billing->history->contains('action', 'saved');
            $hasCurrentFrozenSnapshot = $billing->history->contains('action', 'frozen');
            $hasNeverBeenLocked = $billing->pricing_locked_at === null && $billing->pricing_unlocked_at === null;

            if ($confirmedPaid > 0 && $hasNeverBeenLocked && $hasHistoricalSavedSnapshot && ! $hasCurrentFrozenSnapshot) {
                return $this->billingState(
                    billing: $billing,
                    lifecycle: 'legacy_paid',
                    payment: $payment,
                    confirmedPaid: $confirmedPaid,
                    hasFrozenBilling: false,
                    legacy: true,
                    pricingLocked: true,
                );
            }

            if ($confirmedPaid > 0 && $hasNeverBeenLocked) {
                return $this->billingState(
                    billing: $billing,
                    lifecycle: 'invalid_paid_unlocked',
                    payment: $payment,
                    confirmedPaid: $confirmedPaid,
                    hasFrozenBilling: false,
                    legacy: false,
                    pricingLocked: true,
                    paymentStatus: 'billing_error',
                );
            }

            $acceptedSnapshotFee = (float) $request->requestServices
                ->where('status', 'approved')
                ->sum(fn ($service): float => $service->billingProfessionalFee());
            if ((float) $billing->grand_total <= 0 && $acceptedSnapshotFee > 0) {
                return new RequestBillingState(
                    lifecycle: 'invalid_frozen',
                    hasFrozenBilling: true,
                    legacy: false,
                    professionalFee: (float) $billing->total_original_professional_fee,
                    discountAmount: (float) $billing->discount_amount,
                    netProfessionalFee: (float) $billing->net_professional_fee,
                    gstRate: (float) $billing->gst_rate,
                    gstAmount: (float) $billing->gst_amount,
                    governmentChargesTotal: (float) $billing->government_charges_total,
                    grandTotal: (float) $billing->grand_total,
                    confirmedPaidAmount: $confirmedPaid,
                    balanceDue: null,
                    paymentRequired: false,
                    paymentStatus: 'billing_error',
                    pricingLocked: $billing->isLocked(),
                );
            }
            $lifecycle = match ($payment->status) {
                'paid' => 'paid',
                'partial' => 'partially_paid',
                'not_required' => 'frozen_no_payment',
                default => 'frozen_payable',
            };

            return new RequestBillingState(
                lifecycle: $lifecycle,
                hasFrozenBilling: true,
                legacy: false,
                professionalFee: (float) $billing->total_original_professional_fee,
                discountAmount: (float) $billing->discount_amount,
                netProfessionalFee: (float) $billing->net_professional_fee,
                gstRate: (float) $billing->gst_rate,
                gstAmount: (float) $billing->gst_amount,
                governmentChargesTotal: (float) $billing->government_charges_total,
                grandTotal: (float) $billing->grand_total,
                confirmedPaidAmount: $confirmedPaid,
                balanceDue: $payment->balanceDue,
                paymentRequired: $payment->paymentRequired,
                paymentStatus: $payment->status,
                pricingLocked: $billing->isLocked(),
            );
        }

        $legacy = $request->requestServices->contains(fn ($service): bool => $service->pricing_locked_at !== null)
            || (($received > 0 || (float) $request->amount_paid > 0) && (float) $request->amount_due > 0);
        if ($legacy) {
            $payable = (float) $request->amount_due;
            $legacyPaid = $confirmedPaid > 0 ? $confirmedPaid : (float) $request->amount_paid;
            $payment = $this->calculator->paymentState($payable, $legacyPaid);

            return new RequestBillingState(
                lifecycle: 'legacy_frozen',
                hasFrozenBilling: false,
                legacy: true,
                professionalFee: null,
                discountAmount: null,
                netProfessionalFee: null,
                gstRate: null,
                gstAmount: null,
                governmentChargesTotal: null,
                grandTotal: $payable,
                confirmedPaidAmount: $legacyPaid,
                balanceDue: $payment->balanceDue,
                paymentRequired: $payment->paymentRequired,
                paymentStatus: $payment->status,
                pricingLocked: true,
            );
        }

        return new RequestBillingState(
            lifecycle: 'unpriced',
            hasFrozenBilling: false,
            legacy: false,
            professionalFee: null,
            discountAmount: null,
            netProfessionalFee: null,
            gstRate: null,
            gstAmount: null,
            governmentChargesTotal: null,
            grandTotal: null,
            confirmedPaidAmount: 0,
            balanceDue: null,
            paymentRequired: false,
            paymentStatus: 'billing_pending',
            pricingLocked: false,
        );
    }

    private function billingState(
        RequestBilling $billing,
        string $lifecycle,
        RequestPaymentState $payment,
        float $confirmedPaid,
        bool $hasFrozenBilling,
        bool $legacy,
        bool $pricingLocked,
        ?string $paymentStatus = null,
    ): RequestBillingState {
        return new RequestBillingState(
            lifecycle: $lifecycle,
            hasFrozenBilling: $hasFrozenBilling,
            legacy: $legacy,
            professionalFee: (float) $billing->total_original_professional_fee,
            discountAmount: (float) $billing->discount_amount,
            netProfessionalFee: (float) $billing->net_professional_fee,
            gstRate: (float) $billing->gst_rate,
            gstAmount: (float) $billing->gst_amount,
            governmentChargesTotal: (float) $billing->government_charges_total,
            grandTotal: (float) $billing->grand_total,
            confirmedPaidAmount: $confirmedPaid,
            balanceDue: $payment->balanceDue,
            paymentRequired: $payment->paymentRequired,
            paymentStatus: $paymentStatus ?? $payment->status,
            pricingLocked: $pricingLocked,
        );
    }
}
