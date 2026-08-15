<?php

namespace App\Services\Pdf;

use App\Data\Pdf\PdfDocumentData;
use App\Enums\PdfDocumentType;
use App\Models\CustomerRequest;
use App\Services\RequestBillingStateResolver;
use App\Support\IndiaDateTime;
use Illuminate\Support\Carbon;

class CustomerSafePdfDataFactory
{
    public function __construct(
        private readonly PdfCompanyContext $companies,
        private readonly RequestBillingStateResolver $billingStateResolver,
    ) {}

    public function make(PdfDocumentType $type, CustomerRequest $request): PdfDocumentData
    {
        $company = $this->companies->get();
        $generatedAt = Carbon::now($company['timezone']);
        $content = match ($type) {
            PdfDocumentType::RequestAcknowledgement => $this->acknowledgement($request),
            PdfDocumentType::PaymentSummary => $this->payment($request),
            PdfDocumentType::CaseSummary => $this->caseSummary($request),
            PdfDocumentType::DispatchSlip => $this->dispatch($request),
        };

        return new PdfDocumentData($type, $request->reference_no, $request->file_number, $generatedAt, $company, $content);
    }

    private function base(CustomerRequest $request): array
    {
        return [
            'customer' => ['name' => $request->name, 'mobile' => $request->mobile, 'email' => $request->email, 'address' => $request->address],
            'request' => [
                'status' => str($request->status)->headline()->toString(),
                'submitted_at' => IndiaDateTime::format($request->created_at),
                'estimated_completion_date' => $request->estimated_completion_date?->format('d M Y'),
                'details' => $request->details,
                'property' => collect([$request->property_village ?: $request->village, $request->property_taluka ?: $request->taluka, $request->property_district ?: $request->district])->filter()->implode(', '),
                'survey_numbers' => $request->survey_numbers,
                'khata_number' => $request->khata_number,
            ],
        ];
    }

    private function services(CustomerRequest $request, bool $includeScopes = false): array
    {
        return $request->requestServices()->with('workScopes')->orderBy('id')->get()->map(function ($service) use ($includeScopes): array {
            $row = [
                'name_en' => $service->service_name_en_snapshot ?: $service->service?->name_en,
                'name_gu' => $service->service_name_gu_snapshot ?: $service->service?->name_gu,
                'status' => str($service->status)->headline()->toString(),
                'customer_message' => $service->customer_decision_message,
            ];
            if ($includeScopes) {
                $row['scopes'] = $service->workScopes->map(fn ($scope) => ['name_en' => $scope->name_en_snapshot, 'name_gu' => $scope->name_gu_snapshot, 'status' => str($scope->status)->headline()->toString(), 'customer_remark' => $scope->customer_remark])->all();
            }

            return $row;
        })->all();
    }

    private function acknowledgement(CustomerRequest $request): array
    {
        return [...$this->base($request), 'services' => $this->services($request), 'message' => 'Your request has been recorded successfully.'];
    }

    private function payment(CustomerRequest $request): array
    {
        $billing = $request->billing()->with('charges')->first();
        $state = $this->billingStateResolver->resolve($request);

        return [...$this->base($request), 'billing' => $billing ? [
            'original_fee' => (float) $billing->total_original_professional_fee, 'discount' => (float) $billing->discount_amount,
            'net_fee' => (float) $billing->net_professional_fee, 'gst_rate' => (float) $billing->gst_rate,
            'gst_amount' => (float) $billing->gst_amount, 'government_charges' => (float) $billing->government_charges_total,
            'grand_total' => (float) $billing->grand_total,
            'charges' => $billing->charges->map(fn ($charge) => ['name' => $charge->name, 'amount' => (float) $charge->amount])->all(),
        ] : ['grand_total' => $state->grandTotal, 'charges' => []],
            'payment_status' => str($state->paymentStatus)->headline()->toString(), 'amount_paid' => $state->confirmedPaidAmount,
            'payments' => $request->payments()->latest('received_at')->get()->map(fn ($payment) => ['amount' => (float) $payment->amount, 'status' => str($payment->payment_status)->headline()->toString(), 'method' => str($payment->payment_method)->headline()->toString(), 'reference' => $payment->transaction_reference, 'received_at' => IndiaDateTime::format($payment->received_at), 'customer_remark' => $payment->customer_remark])->all(),
        ];
    }

    private function caseSummary(CustomerRequest $request): array
    {
        return [...$this->base($request), 'services' => $this->services($request), 'completion' => ['date' => IndiaDateTime::format($request->completed_at), 'customer_remark' => $request->completion_customer_remark], 'closure' => ['date' => IndiaDateTime::format($request->closed_at), 'customer_remark' => $request->closure_customer_remark], 'dispatches' => $this->safeDispatches($request)];
    }

    private function dispatch(CustomerRequest $request): array
    {
        return [...$this->base($request), 'dispatches' => $this->safeDispatches($request)];
    }

    private function safeDispatches(CustomerRequest $request): array
    {
        return $request->dispatches()->latest('dispatch_date')->get()->map(fn ($dispatch) => [
            'method' => str($dispatch->dispatch_method)->headline()->toString(), 'status' => str($dispatch->dispatch_status)->headline()->toString(),
            'dispatch_date' => IndiaDateTime::format($dispatch->dispatch_date), 'description' => $dispatch->document_description,
            'recipient_name' => $dispatch->recipient_name, 'recipient_mobile' => $dispatch->recipient_mobile, 'recipient_email' => $dispatch->recipient_email,
            'delivery_address' => $dispatch->delivery_address, 'carrier' => $dispatch->carrier_name, 'tracking_number' => $dispatch->tracking_number,
            'tracking_url' => $dispatch->tracking_url, 'delivered_at' => IndiaDateTime::format($dispatch->delivered_at),
            'collected_at' => IndiaDateTime::format($dispatch->collected_at), 'customer_remark' => $dispatch->customer_remark,
        ])->all();
    }
}
