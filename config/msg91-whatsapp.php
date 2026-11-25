<?php

use App\Enums\NotificationMilestone;

$template = static fn (string $name, string $prefix, int $parameterCount): array => [
    'name' => $name,
    // This is an explicit contract with the approved MSG91 template body variables.
    'parameter_count' => $parameterCount,
    'languages' => [
        'gu' => (bool) env("MSG91_WHATSAPP_TEMPLATE_{$prefix}_GU_ENABLED", false),
        'en' => (bool) env("MSG91_WHATSAPP_TEMPLATE_{$prefix}_EN_ENABLED", false),
    ],
];

return [
    'enabled' => (bool) env('MSG91_WHATSAPP_ENABLED', false),
    'endpoint' => env('MSG91_WHATSAPP_ENDPOINT', 'https://control.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/'),
    'authkey' => env('MSG91_WHATSAPP_AUTHKEY'),
    'integrated_number' => env('MSG91_WHATSAPP_INTEGRATED_NUMBER'),
    'timeout' => (int) env('MSG91_WHATSAPP_TIMEOUT', 10),
    'default_language' => env('MSG91_WHATSAPP_DEFAULT_LANGUAGE', 'gu'),
    'templates' => [
        NotificationMilestone::RequestReceived->value => $template('request_received', 'REQUEST_RECEIVED', 3),
        NotificationMilestone::Accepted->value => $template('request_accepted_payment_pending', 'REQUEST_ACCEPTED_PAYMENT_PENDING', 5),
        // The event key is intentionally different from the verified MSG91 template name.
        NotificationMilestone::PaymentReceived->value => $template('payment_received', 'PAYMENT_RECEIVED', 4),
        NotificationMilestone::Rejected->value => $template('request_rejected', 'REQUEST_REJECTED', 4),
        NotificationMilestone::Completed->value => $template('work_completed', 'WORK_COMPLETED', 3),
        NotificationMilestone::FinalDraftReady->value => $template('document_ready', 'DOCUMENT_READY', 3),
        NotificationMilestone::Dispatched->value => $template('dispatch_notification', 'DISPATCH_NOTIFICATION', 6),
        NotificationMilestone::RevenueEntryNumberReceived->value => $template('entry_notification', 'ENTRY_NOTIFICATION', 6),
        NotificationMilestone::RevenueEntryCertified->value => $template('entry_certified', 'ENTRY_CERTIFIED', 6),
        NotificationMilestone::RevenueEntryRejected->value => $template('entry_rejected', 'ENTRY_REJECTED', 5),
        // These names and four-variable contracts must be approved in MSG91 before enabling.
        NotificationMilestone::AppointmentConfirmed->value => $template('appointment_confirmed', 'APPOINTMENT_CONFIRMED', 4),
        NotificationMilestone::AppointmentRescheduled->value => $template('appointment_rescheduled', 'APPOINTMENT_RESCHEDULED', 4),
        NotificationMilestone::AppointmentCancelled->value => $template('appointment_cancelled', 'APPOINTMENT_CANCELLED', 4),
    ],
];
