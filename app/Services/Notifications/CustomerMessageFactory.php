<?php

namespace App\Services\Notifications;

use App\Enums\NotificationMilestone;
use App\Models\CustomerRequest;

class CustomerMessageFactory
{
    public function make(CustomerRequest $request, NotificationMilestone $milestone): array
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
        };
        $tracking = route('request.track');
        $details = [];
        if ($milestone === NotificationMilestone::Accepted) {
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
        $body = implode("\n", array_filter(["નમસ્તે {$request->name},", $status, ...$details, "Reference: {$reference}", "Tracking: {$tracking}"]));

        return ['subject' => 'Sai Consulting — '.$milestone->label().' — '.$reference, 'body' => $body, 'template_key' => 'customer_'.$milestone->value, 'parameters' => [$request->name, $reference, $tracking]];
    }
}
