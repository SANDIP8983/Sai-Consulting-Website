<?php

use App\Enums\NotificationMilestone;

$template = static fn (string $name, string $prefix): array => [
    'name' => $name,
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
    'templates' => [
        NotificationMilestone::RequestReceived->value => $template('request_received', 'REQUEST_RECEIVED'),
        NotificationMilestone::Accepted->value => $template('request_accepted_payment_pending', 'REQUEST_ACCEPTED_PAYMENT_PENDING'),
        NotificationMilestone::PaymentReceived->value => $template('payment_received_awaiting_assignment', 'PAYMENT_RECEIVED_AWAITING_ASSIGNMENT'),
        NotificationMilestone::Rejected->value => $template('request_rejected', 'REQUEST_REJECTED'),
        NotificationMilestone::Completed->value => $template('work_completed', 'WORK_COMPLETED'),
        NotificationMilestone::FinalDraftReady->value => $template('document_ready', 'DOCUMENT_READY'),
        NotificationMilestone::Dispatched->value => $template('dispatch_notification', 'DISPATCH_NOTIFICATION'),
        NotificationMilestone::RevenueEntryNumberReceived->value => $template('entry_notification', 'ENTRY_NOTIFICATION'),
        NotificationMilestone::RevenueEntryCertified->value => $template('entry_certified', 'ENTRY_CERTIFIED'),
        NotificationMilestone::RevenueEntryRejected->value => $template('entry_rejected', 'ENTRY_REJECTED'),
    ],
];
