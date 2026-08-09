<?php

return ['queue' => env('CUSTOMER_NOTIFICATION_QUEUE', 'customer-notifications'), 'retention_days' => (int) env('CUSTOMER_NOTIFICATION_RETENTION_DAYS', 365)];
