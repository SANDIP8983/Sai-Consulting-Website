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
            return $this->result('skipped', 'master_disabled', 'MSG91 WhatsApp is disabled.');
        }

        $authkey = config('msg91-whatsapp.authkey');
        $sender = config('msg91-whatsapp.integrated_number');
        if (blank($authkey) || blank($sender)) {
            return $this->result('skipped', 'missing_provider_configuration', 'MSG91 provider configuration is incomplete.');
        }

        $template = $this->templates->make($delivery);
        if (! $template) {
            return $this->result('skipped', 'template_unavailable', 'No enabled MSG91 template matches this milestone, language, and parameter contract.');
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
            return $this->result('failed', 'connection_failure', 'MSG91 could not be reached.');
        } catch (\Throwable) {
            return $this->result('failed', 'transport_failure', 'MSG91 transport failed.');
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            return $this->result('failed', 'malformed_provider_response', 'MSG91 returned an unreadable response.');
        }

        $messageId = $this->messageId($decoded);
        $responseSummary = ['http_status' => $response->status(), 'message_id' => $messageId];
        if ($response->clientError()) {
            return $this->result('failed', 'provider_4xx', $this->message($decoded), $messageId, $responseSummary);
        }
        if ($response->serverError()) {
            return $this->result('failed', 'provider_5xx', $this->message($decoded), $messageId, $responseSummary);
        }
        if (! $response->successful()) {
            return $this->result('failed', 'unexpected_provider_status', $this->message($decoded), $messageId, $responseSummary);
        }

        return $this->result('sent', null, null, $messageId, $responseSummary);
    }

    /** @param array<string,mixed>|null $response */
    private function result(string $status, ?string $failure, ?string $message = null, ?string $messageId = null, ?array $response = null): array
    {
        return ['status' => $status, 'provider' => 'msg91', 'provider_message_id' => $messageId, 'failure_category' => $failure, 'failure_message' => $message, 'provider_response' => $response];
    }

    /** @param array<string,mixed> $response */
    private function messageId(array $response): ?string
    {
        foreach (['message_id', 'messageId', 'id'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) {
                return substr((string) $response[$key], 0, 191);
            }
        }

        return null;
    }

    /** @param array<string,mixed> $response */
    private function message(array $response): ?string
    {
        foreach (['message', 'error', 'error_message'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) {
                return str((string) $response[$key])->squish()->limit(500)->toString();
            }
        }

        return null;
    }
}
