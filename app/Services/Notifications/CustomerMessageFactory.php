<?php

namespace App\Services\Notifications;

use App\Enums\NotificationMilestone;
use App\Models\CustomerRequest;
use App\Services\RequestBillingStateResolver;

class CustomerMessageFactory
{
    public function __construct(private readonly RequestBillingStateResolver $billingStateResolver) {}

    public function make(CustomerRequest $request, NotificationMilestone $milestone, array $safeContext = []): array
    {
        $request->loadMissing(['requestServices', 'billing', 'dispatches']);
        $reference = $request->reference_no;
        $status = match ($milestone) {
            NotificationMilestone::RequestReceived => 'તમારી વિનંતી મળી ગઈ છે. / Request received.',
            NotificationMilestone::Accepted => 'તમારી અરજી સ્વીકારવામાં આવી છે. / Request accepted.',
            NotificationMilestone::Rejected => 'તમારી અરજી રદ કરવામાં આવી છે. / Request rejected.',
            NotificationMilestone::PaymentPending => 'તમારી અરજી માટે ચુકવણી બાકી છે. / Payment is pending.',
            NotificationMilestone::PaymentReceived => 'ચુકવણી સફળતાપૂર્વક મળી ગઈ છે. તમારી અરજી આગળની પ્રક્રિયા માટે તૈયાર છે. / Payment received; your request is ready for further processing.',
            NotificationMilestone::ProcessingStarted => 'તમારી અરજીની પ્રક્રિયા શરૂ થઈ ગઈ છે. / Processing has started.',
            NotificationMilestone::DraftReady => 'તમારો ડ્રાફ્ટ તૈયાર છે. / Draft is ready.',
            NotificationMilestone::FinalDraftReady => 'તમારો અંતિમ ડ્રાફ્ટ તૈયાર છે. / Final draft is ready.',
            NotificationMilestone::Completed => 'તમારી અરજીનું કામ પૂર્ણ થયું છે. / Request completed.',
            NotificationMilestone::Dispatched => 'તમારા દસ્તાવેજો મોકલવામાં આવ્યા છે. / Documents dispatched.',
            NotificationMilestone::DeliveredClosed => 'તમારા દસ્તાવેજોની ડિલિવરી પૂર્ણ થઈ છે અને કેસ પૂર્ણ થયો છે. / Delivery and case conclusion completed.',
            NotificationMilestone::RevenueEntrySubmitted => 'તમારી ફેરફાર નોંધ માટેની અરજી દાખલ કરવામાં આવી છે. / Revenue Entry application submitted.',
            NotificationMilestone::RevenueEntryNumberReceived => 'તમારી ફેરફાર નોંધ માટે નોંધ નંબર મળ્યો છે. / Revenue Entry number received.',
            NotificationMilestone::RevenueEntryCertified => 'તમારી ફેરફાર નોંધ પ્રમાણિત થઈ ગઈ છે. / Revenue Entry certified.',
            NotificationMilestone::RevenueEntryRejected => 'તમારી ફેરફાર નોંધ અંગે વધુ કાર્યવાહી જરૂરી છે. / Action is required for your Revenue Entry.',
            NotificationMilestone::AppointmentReceived, NotificationMilestone::AppointmentConfirmed,
            NotificationMilestone::AppointmentRescheduled, NotificationMilestone::AppointmentCancelled,
            NotificationMilestone::AppointmentReminder => throw new \LogicException('Appointment milestones require AppointmentMessageFactory.'),
        };
        $tracking = route('request.track');
        $details = [];
        $billingState = null;
        if ($milestone === NotificationMilestone::Accepted) {
            $billingState = $this->billingStateResolver->resolve($request);
            $approved = $request->requestServices->where('status', 'approved')->pluck('service_name_en_snapshot')->filter()->implode(', ');
            $rejected = $request->requestServices->where('status', 'rejected')->pluck('service_name_en_snapshot')->filter()->implode(', ');
            if ($approved) {
                $details[] = 'Approved services: '.$approved;
            }
            if ($rejected) {
                $details[] = 'Not accepted: '.$rejected;
            }
        }
        if ($milestone === NotificationMilestone::Rejected) {
            $customerMessages = $request->requestServices->pluck('customer_decision_message')->filter()->unique()->implode(' ');
            if ($customerMessages) {
                $details[] = $customerMessages;
            }
        }
        if (in_array($milestone, [NotificationMilestone::PaymentPending, NotificationMilestone::PaymentReceived], true) && $request->billing) {
            $details[] = 'Amount: ₹'.number_format((float) $request->billing->grand_total, 2);
        }
        if ($milestone === NotificationMilestone::Dispatched) {
            $dispatch = $request->dispatches->first();
            if ($dispatch?->carrier_name) {
                $details[] = 'Carrier: '.$dispatch->carrier_name;
            }
            if ($dispatch?->tracking_number) {
                $details[] = 'Tracking / Consignment: '.$dispatch->tracking_number;
            }
        }
        if ($milestone->isRevenueEntry()) {
            $details[] = 'Service / સેવા: '.($safeContext['service_name'] ?? 'Document Service');
            if (! empty($safeContext['survey_numbers'])) {
                $details[] = 'Survey No. / સર્વે નં.: '.implode(', ', $safeContext['survey_numbers']);
            }
            if (in_array($milestone, [NotificationMilestone::RevenueEntryNumberReceived, NotificationMilestone::RevenueEntryCertified], true) && ! empty($safeContext['entry_number'])) {
                $details[] = 'Entry Number / ફેરફાર નોંધ નં.: '.$safeContext['entry_number'];
            }
            if ($milestone === NotificationMilestone::RevenueEntryNumberReceived && ! empty($safeContext['entry_date'])) {
                $details[] = 'Entry Date / નોંધ તારીખ: '.$safeContext['entry_date'];
            }
            if ($milestone === NotificationMilestone::RevenueEntryCertified && ! empty($safeContext['certified_date'])) {
                $details[] = 'Certified Date / પ્રમાણિત તારીખ: '.$safeContext['certified_date'];
            }
            $details[] = 'Status / સ્થિતિ: '.($safeContext['status_label'] ?? $milestone->label());
            if (! empty($safeContext['customer_note'])) {
                $details[] = 'ગ્રાહક માટે નોંધ / Customer Note: '.$safeContext['customer_note'];
            }
        }
        $body = implode("\n", array_filter(["નમસ્તે {$request->name},", $status, ...$details, "Reference: {$reference}", "Tracking: {$tracking}"]));

        $subjectLabel = match ($milestone) {
            NotificationMilestone::Accepted => $billingState?->paymentRequired ? 'Accepted — Payment Pending' : $milestone->label(),
            NotificationMilestone::RevenueEntrySubmitted => 'ફેરફાર નોંધ માટે અરજી દાખલ થઈ',
            NotificationMilestone::RevenueEntryNumberReceived => 'ફેરફાર નોંધ નંબર મળ્યો',
            NotificationMilestone::RevenueEntryCertified => 'ફેરફાર નોંધ પ્રમાણિત થઈ',
            NotificationMilestone::RevenueEntryRejected => 'ફેરફાર નોંધ અંગે કાર્યવાહી જરૂરી',
            default => $milestone->label(),
        };

        return [
            'subject' => 'Sai Consulting — '.$subjectLabel.' — '.$reference,
            'body' => $body,
            'template_key' => 'customer_'.$milestone->value,
            'parameters' => [$request->name, $reference, $tracking],
            'tracking_url' => $tracking,
            'accepted_payment_required' => $milestone === NotificationMilestone::Accepted ? $billingState?->paymentRequired : null,
            'outstanding_amount' => $milestone === NotificationMilestone::Accepted ? $billingState?->balanceDue : null,
        ];
    }
}
