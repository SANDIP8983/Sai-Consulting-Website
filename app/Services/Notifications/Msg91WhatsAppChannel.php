<?php

namespace App\Services\Notifications;

use App\Contracts\WhatsAppChannelInterface;
use App\Models\CustomerNotificationDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class Msg91WhatsAppChannel implements WhatsAppChannelInterface
{
    public function __construct(private readonly Msg91WhatsAppTemplateFactory $templates) {}

    public function send(CustomerNotificationDelivery $delivery, string $recipient, array $message): array
    {
        if (! config('msg91-whatsapp.enabled')) {
            return $this->result('skipped', 'master_disabled');
        }

        $authkey = config('msg91-whatsapp.authkey');
        $sender = config('msg91-whatsapp.integrated_number');
        if (blank($authkey) || blank($sender)) {
            return $this->result('skipped', 'missing_provider_configuration');
        }

        $template = $this->templates->make($delivery);
        if (! $template) {
            return $this->result('skipped', 'template_unavailable');
        }

        $components = [];
        foreach ($template['parameters'] as $index => $value) {
            $components['body_'.($index + 1)] = ['type' => 'text', 'value' => $value];
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['authkey' => $authkey])
                ->contentType('application/json')
                ->timeout(max(1, (int) config('msg91-whatsapp.timeout', 10)))
                ->post(config('msg91-whatsapp.endpoint'), [
                    'integrated_number' => $sender,
                    'content_type' => 'template',
                    'payload' => [
                        'type' => 'template',
                        'template' => [
                            'name' => $template['name'],
                            'language' => ['code' => $template['language'], 'policy' => 'deterministic'],
                            'to_and_components' => [[
                                'to' => [$recipient],
                                'components' => $components,
                            ]],
                        ],
                        'messaging_product' => 'whatsapp',
                    ],
                ]);
        } catch (ConnectionException) {
            return $this->result('failed', 'connection_failure');
        } catch (\Throwable) {
            return $this->result('failed', 'transport_failure');
        }

        if ($response->clientError()) {
            return $this->result('failed', 'provider_4xx');
        }
        if ($response->serverError()) {
            return $this->result('failed', 'provider_5xx');
        }
        if (! $response->successful()) {
            return $this->result('failed', 'unexpected_provider_status');
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            return $this->result('failed', 'malformed_provider_response');
        }

        return $this->result('sent', null);
    }

    private function result(string $status, ?string $failure): array
    {
        return ['status' => $status, 'provider' => 'msg91', 'provider_message_id' => null, 'failure_category' => $failure];
    }
}
