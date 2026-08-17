<?php

namespace App\Services;

use App\Models\CustomerRequest;
use Illuminate\Validation\ValidationException;

class PublicRequestTrackingService
{
    public function __construct(private readonly RequestBillingStateResolver $billingStateResolver) {}

    public function find(string $trackingNumber, string $mobile): CustomerRequest
    {
        $request = CustomerRequest::query()
            ->select(['id', 'reference_no', 'file_number', 'service_id', 'name', 'status', 'payment_status', 'amount_due', 'property_village', 'property_taluka', 'property_district', 'survey_numbers', 'khata_number', 'estimated_completion_date', 'completed_at', 'completion_customer_remark', 'closed_at', 'closure_customer_remark', 'last_status_changed_at', 'created_at', 'updated_at'])
            ->where(fn ($query) => $query->where('reference_no', $trackingNumber)->orWhere('file_number', $trackingNumber))
            ->where('mobile', $mobile)
            ->with([
                'service:id,name_en,name_gu',
                'service.activeRequiredDocuments:id,service_id,name_en,name_gu,sort_order,is_mandatory',
                'requestServices:id,request_id,service_id,is_admin_added,service_name_en_snapshot,service_name_gu_snapshot,professional_fee,original_professional_fee,net_professional_fee,gst_rate,gst_amount,government_charges,government_charges_snapshot,final_total,pricing_locked_at,estimated_days,required_documents_snapshot,status,customer_decision_message',
                'requestServices.service:id,name_en,name_gu',
                'requestServices.workScopes' => fn ($query) => $query->select(['id', 'request_service_id', 'status', 'customer_remark']),
                'documents' => fn ($query) => $query->select(['id', 'request_id', 'service_required_document_id']),
                'finalDocuments' => fn ($query) => $query
                    ->select(['id', 'request_id', 'original_name', 'mime_type', 'file_size', 'created_at'])
                    ->whereHas('deliveries', fn ($delivery) => $delivery->whereColumn('request_final_document_deliveries.request_id', 'request_final_documents.request_id')->where('status', 'sent'))
                    ->latest(),
                'billing' => fn ($query) => $query->select(['id', 'request_id', 'total_original_professional_fee', 'discount_amount', 'net_professional_fee', 'gst_rate', 'gst_amount', 'government_charges_total', 'grand_total', 'pricing_locked_at']),
                'billing.charges' => fn ($query) => $query->select(['id', 'request_billing_id', 'name', 'amount', 'display_order'])->orderBy('display_order')->orderBy('id'),
                'payments' => fn ($query) => $query
                    ->select(['id', 'request_id', 'amount', 'payment_status', 'payment_method', 'received_at', 'customer_remark'])
                    ->latest('received_at'),
                'paymentSubmission' => fn ($query) => $query->select(['id', 'request_id', 'amount', 'status', 'submitted_at']),
                'dispatches' => fn ($query) => $query
                    ->select(['id', 'request_id', 'dispatch_status', 'dispatch_method', 'dispatch_date', 'tracking_number', 'tracking_url', 'carrier_name', 'customer_remark', 'delivered_at', 'collected_at'])
                    ->latest('dispatch_date'),
                'processing' => fn ($query) => $query->select(['id', 'request_id', 'processing_stage', 'token_booking_status', 'token_scheduled_at', 'registration_date', 'registration_number', 'registration_number_public', 'certified_copy_status']),
                'processingHistory' => fn ($query) => $query
                    ->select(['id', 'request_id', 'to_stage', 'remarks', 'created_at'])
                    ->where('is_visible_to_customer', true)
                    ->latest('created_at'),
                'statusHistory' => fn ($query) => $query
                    ->select(['id', 'request_id', 'to_status', 'remarks', 'created_at'])
                    ->where('is_visible_to_customer', true)
                    ->latest('created_at'),
            ])
            ->first();

        if (! $request) {
            throw ValidationException::withMessages([
                'reference_no' => 'No request matches the reference/file number and mobile number provided.',
            ]);
        }

        $workScopes = $request->requestServices->where('status', 'approved')->flatMap->workScopes;
        $resolvedScopes = $workScopes->whereIn('status', ['completed', 'not_required', 'cancelled'])->count();
        $progress = $workScopes->isNotEmpty()
            ? (int) round($resolvedScopes / $workScopes->count() * 100)
            : $this->statusProgress($request->status);

        $uploadedDocumentIds = $request->documents->pluck('service_required_document_id')->filter()->map(fn ($id) => (int) $id);
        $requiredDocuments = $request->requestServices
            ->where('status', '!=', 'rejected')
            ->flatMap(fn ($service) => collect($service->required_documents_snapshot ?? []));
        if ($requiredDocuments->isEmpty()) {
            $requiredDocuments = $request->service?->activeRequiredDocuments
                ?->map(fn ($document) => $document->only(['id', 'name_en', 'name_gu', 'is_mandatory'])) ?? collect();
        }
        $pendingDocuments = $requiredDocuments
            ->filter(fn (array $document) => (bool) ($document['is_mandatory'] ?? true))
            ->reject(fn (array $document) => isset($document['id']) && $uploadedDocumentIds->contains((int) $document['id']))
            ->unique(fn (array $document) => ($document['id'] ?? '').'|'.($document['name_en'] ?? ''))
            ->values();

        $request->setAttribute('public_progress_percentage', $progress);
        $request->setAttribute('public_pending_documents', $pendingDocuments);
        $request->setAttribute('public_work_remarks', $workScopes->pluck('customer_remark')->filter()->unique()->values());
        $request->setAttribute('public_status', $this->customerStatus($request));
        $billingState = $this->billingStateResolver->resolve($request);
        $request->setAttribute('public_billing_state', $billingState);
        $request->setAttribute(
            'public_payment_status',
            $request->public_status === 'rejected' ? 'not_required' : $billingState->paymentStatus,
        );

        return $request;
    }

    /**
     * Resolve the authoritative customer-facing request status.
     *
     * Older records can retain an active parent status after every service was
     * declined. Rejection must win over stale payment or processing records,
     * while completed lifecycle states remain authoritative.
     */
    private function customerStatus(CustomerRequest $request): string
    {
        if ($request->status === 'rejected') {
            return 'rejected';
        }

        if ($request->requestServices->isNotEmpty()
            && $request->requestServices->every(fn ($service) => $service->status === 'rejected')) {
            return 'rejected';
        }

        return $request->status;
    }

    private function statusProgress(string $status): int
    {
        return match ($status) {
            'received' => 10,
            'under_review', 'need_documents' => 20,
            'approved', 'payment_pending' => 35,
            'payment_received' => 45,
            'awaiting_staff_assignment' => 50,
            'in_progress', 'draft_in_progress', 'ready_for_verification', 'customer_approved', 'ready_for_registration' => 65,
            'completed' => 85,
            'dispatched' => 90,
            'delivered', 'closed', 'archived' => 100,
            'rejected' => 0,
            default => 0,
        };
    }
}
