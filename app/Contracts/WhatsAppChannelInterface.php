<?php

namespace App\Contracts;

use App\Models\CustomerNotificationDelivery;

interface WhatsAppChannelInterface
{
    /** @return array{status:string,provider:string,provider_message_id:?string,failure_category:?string} */
    public function send(CustomerNotificationDelivery $delivery, string $recipient, array $message): array;
}
