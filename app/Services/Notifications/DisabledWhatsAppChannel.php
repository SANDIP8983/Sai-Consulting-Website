<?php

namespace App\Services\Notifications;

use App\Contracts\WhatsAppChannelInterface;
use App\Models\CustomerNotificationDelivery;

class DisabledWhatsAppChannel implements WhatsAppChannelInterface
{
    public function send(CustomerNotificationDelivery $delivery, string $recipient, array $message): array
    {
        return ['status' => 'skipped', 'provider' => 'disabled', 'provider_message_id' => null, 'failure_category' => 'provider_not_configured'];
    }
}
