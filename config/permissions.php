<?php

return [
    'keys' => [
        'dashboard.view', 'requests.view', 'requests.manage', 'requests.approve',
        'billing.view', 'billing.manage', 'payments.manage', 'processing.manage',
        'dispatch.manage', 'requests.assign', 'services.manage', 'documents.manage', 'settings.manage',
        'users.manage', 'notifications.view', 'notifications.manage',
    ],
    'roles' => [
        'super_admin' => ['*'],
        'admin' => [
            'dashboard.view', 'requests.view', 'requests.manage', 'requests.approve',
            'billing.view', 'billing.manage', 'payments.manage', 'processing.manage',
            'dispatch.manage', 'requests.assign',
        ],
        'staff' => ['dashboard.view', 'requests.view', 'processing.manage', 'dispatch.manage'],
    ],
    'labels' => ['super_admin' => 'Super Admin', 'admin' => 'Admin', 'staff' => 'Staff'],
    'user_reference_columns' => [
        'requests' => ['fee_updated_by', 'case_approved_by', 'closed_by', 'assigned_user_id', 'assigned_by'],
        'request_documents' => ['verified_by'],
        'request_payments' => ['received_by'],
        'request_status_histories' => ['changed_by'],
        'request_services' => ['added_by', 'decided_by', 'pricing_unlocked_by'],
        'request_service_approval_histories' => ['approved_by'],
        'request_billings' => ['applied_by', 'pricing_unlocked_by'],
        'request_billing_histories' => ['changed_by'],
        'request_processing_details' => ['file_in_charge_user_id'],
        'request_processing_histories' => ['changed_by'],
        'request_service_work_scopes' => ['selected_by', 'updated_by'],
        'request_service_work_scope_histories' => ['changed_by'],
        'request_case_action_histories' => ['performed_by'],
        'request_dispatches' => ['performed_by', 'updated_by'],
        'request_dispatch_proofs' => ['uploaded_by'],
        'request_dispatch_histories' => ['changed_by'],
        'request_assignment_histories' => ['previous_assigned_user_id', 'assigned_user_id', 'assigned_by'],
        'request_contact_change_histories' => ['changed_by'],
    ],
];
