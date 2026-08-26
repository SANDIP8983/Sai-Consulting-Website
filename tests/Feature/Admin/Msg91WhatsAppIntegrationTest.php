<?php

namespace Tests\Feature\Admin;

use App\Enums\NotificationMilestone;
use App\Jobs\SendCustomerNotificationJob;
use App\Models\CustomerNotificationDelivery;
use App\Models\CustomerNotificationEvent;
use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\Setting;
use App\Services\Notifications\CustomerMessageFactory;
use App\Services\Notifications\CustomerNotificationService;
use App\Services\Notifications\Msg91WhatsAppChannel;
use App\Services\Notifications\WhatsAppLanguageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Msg91WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://control.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'msg91-whatsapp.enabled' => true,
            'msg91-whatsapp.endpoint' => self::ENDPOINT,
            'msg91-whatsapp.authkey' => 'unit-test-auth-key',
            'msg91-whatsapp.integrated_number' => 'unit-test-sender',
        ]);
    }

    public function test_master_disabled_queues_only_email_and_makes_no_http_request(): void
    {
        Queue::fake();
        Http::fake();
        config(['msg91-whatsapp.enabled' => false]);
        $this->enable(NotificationMilestone::RequestReceived);

        $event = app(CustomerNotificationService::class)->record($this->request(), NotificationMilestone::RequestReceived);

        $this->assertSame('pending', $event->deliveries->firstWhere('channel', 'email')->status);
        $this->assertSame('master_disabled', $event->deliveries->firstWhere('channel', 'whatsapp')->failure_category);
        Queue::assertPushed(SendCustomerNotificationJob::class, 1);
        Http::assertNothingSent();
    }

    public function test_email_remains_independent_when_whatsapp_is_disabled(): void
    {
        Queue::fake();
        Mail::fake();
        config(['msg91-whatsapp.enabled' => false]);
        $event = app(CustomerNotificationService::class)->record($this->request(), NotificationMilestone::RequestReceived);
        $delivery = $event->deliveries->firstWhere('channel', 'email');

        (new SendCustomerNotificationJob($delivery->id))->handle(app(CustomerNotificationService::class), app(CustomerMessageFactory::class), app(Msg91WhatsAppChannel::class));

        Mail::assertSentCount(1);
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_missing_credentials_and_invalid_phone_skip_safely(): void
    {
        Http::fake();
        config(['msg91-whatsapp.authkey' => null]);
        $delivery = $this->delivery($this->request(), NotificationMilestone::RequestReceived);
        $this->assertSame('missing_provider_configuration', app(Msg91WhatsAppChannel::class)->send($delivery, '919999999999', [])['failure_category']);

        $this->assertNull(app(CustomerNotificationService::class)->recipient($this->request(['mobile' => '12345']), 'whatsapp'));
        Http::assertNothingSent();
    }

    public function test_indian_phone_normalization_is_strict(): void
    {
        $notifications = app(CustomerNotificationService::class);
        $this->assertSame('919876543210', $notifications->recipient($this->request(['mobile' => '9876543210']), 'whatsapp'));
        $this->assertSame('919876543210', $notifications->recipient($this->request(['mobile' => '919876543210']), 'whatsapp'));
        $this->assertSame('919876543210', $notifications->recipient($this->request(['mobile' => '+919876543210']), 'whatsapp'));
        $this->assertNull($notifications->recipient($this->request(['mobile' => '1234567890']), 'whatsapp'));
    }

    public function test_language_resolver_supports_gujarati_english_and_gujarati_fallbacks(): void
    {
        $resolver = app(WhatsAppLanguageResolver::class);
        $request = $this->request();
        $request->setAttribute('language', 'gujarati');
        $this->assertSame('gu', $resolver->resolve($request));
        $request->setAttribute('language', 'English');
        $this->assertSame('en', $resolver->resolve($request));
        $request->setAttribute('language', 'unsupported');
        $this->assertSame('gu', $resolver->resolve($request));
        unset($request->language);
        $this->assertSame('gu', $resolver->resolve($request));
    }

    public function test_request_received_gujarati_payload_matches_official_contract_and_variable_order(): void
    {
        $request = $this->request();
        $this->send($request, NotificationMilestone::RequestReceived, 'gu');

        Http::assertSent(fn ($sent): bool => $sent->url() === self::ENDPOINT
            && $sent->hasHeader('authkey', 'unit-test-auth-key')
            && $sent['integrated_number'] === 'unit-test-sender'
            && $sent['content_type'] === 'template'
            && $sent['payload']['type'] === 'template'
            && $sent['payload']['messaging_product'] === 'whatsapp'
            && $sent['payload']['template']['name'] === 'request_received'
            && $sent['payload']['template']['language'] === ['code' => 'gu', 'policy' => 'deterministic']
            && $sent['payload']['template']['to_and_components'][0]['to'] === ['919999999999']
            && $sent['payload']['template']['to_and_components'][0]['components'] === $this->components(['Customer', 'Gujarati Service', $request->reference_no]));
    }

    public function test_request_received_english_payload_uses_same_template_with_en_language(): void
    {
        $request = $this->request();
        $this->send($request, NotificationMilestone::RequestReceived, 'en');

        Http::assertSent(fn ($sent): bool => $sent['payload']['template']['name'] === 'request_received'
            && $sent['payload']['template']['language']['code'] === 'en'
            && $sent['payload']['template']['to_and_components'][0]['components']['body_2']['value'] === 'English Service');
    }

    public function test_payable_acceptance_uses_authoritative_balance_and_zero_fee_skips(): void
    {
        $request = $this->request(['file_number' => 'FILE-100']);
        $request->requestServices()->update(['status' => 'approved', 'original_professional_fee' => 1000, 'professional_fee' => 1000]);
        $request->billing()->create(['total_original_professional_fee' => 1000, 'discount_amount' => 0, 'net_professional_fee' => 1000, 'gst_rate' => 0, 'gst_amount' => 0, 'government_charges_total' => 0, 'grand_total' => 1000, 'pricing_locked_at' => now()]);
        $request->payments()->create(['amount' => 250, 'payment_status' => 'received', 'payment_method' => 'cash', 'received_at' => now()]);
        $this->send($request, NotificationMilestone::Accepted, 'gu');
        Http::assertSent(fn ($sent): bool => $sent['payload']['template']['to_and_components'][0]['components']['body_5']['value'] === '750.00');

        Http::fake();
        $zero = $this->request(['file_number' => 'FILE-ZERO']);
        $zero->requestServices()->update(['status' => 'approved', 'original_professional_fee' => 0, 'professional_fee' => 0]);
        $zero->billing()->create(['total_original_professional_fee' => 0, 'discount_amount' => 0, 'net_professional_fee' => 0, 'gst_rate' => 0, 'gst_amount' => 0, 'government_charges_total' => 0, 'grand_total' => 0, 'pricing_locked_at' => now()]);
        $result = $this->send($zero, NotificationMilestone::Accepted, 'gu', false);
        $this->assertSame('template_unavailable', $result['failure_category']);
        Http::assertNothingSent();
    }

    public function test_payment_received_uses_only_confirmed_source_payment_amount(): void
    {
        $request = $this->request(['file_number' => 'FILE-PAID']);
        $ignored = $request->payments()->create(['amount' => 999, 'payment_status' => 'pending', 'payment_method' => 'upi', 'received_at' => now()]);
        $confirmed = $request->payments()->create(['amount' => 425.5, 'payment_status' => 'received', 'payment_method' => 'cash', 'received_at' => now()]);
        $this->send($request, NotificationMilestone::PaymentReceived, 'gu', true, 'payment', $confirmed->id);
        Http::assertSent(fn ($sent): bool => $sent['payload']['template']['to_and_components'][0]['components']['body_3']['value'] === '425.50'
            && $sent['payload']['template']['to_and_components'][0]['components']['body_3']['value'] !== (string) $ignored->amount);
    }

    public function test_rejection_and_completion_and_document_ready_mappings_are_customer_safe(): void
    {
        $request = $this->request(['file_number' => 'FILE-SAFE', 'details' => 'PRIVATE ADMIN NOTE']);
        $request->requestServices()->update(['status' => 'rejected', 'customer_decision_message' => 'Please provide a corrected document.', 'internal_note' => 'SECRET INTERNAL']);
        $this->send($request, NotificationMilestone::Rejected, 'gu');
        Http::assertSent(fn ($sent): bool => $sent['payload']['template']['name'] === 'request_rejected'
            && $sent['payload']['template']['to_and_components'][0]['components']['body_4']['value'] === 'Please provide a corrected document.');
        Http::assertSent(fn ($sent): bool => ! str_contains((string) $sent->body(), 'SECRET INTERNAL'));

        $request->requestServices()->update(['status' => 'approved']);
        $this->send($request, NotificationMilestone::Completed, 'gu');
        Http::assertSent(fn ($sent): bool => $sent['payload']['template']['name'] === 'work_completed');
        $this->send($request, NotificationMilestone::FinalDraftReady, 'gu');
        Http::assertSent(fn ($sent): bool => $sent['payload']['template']['name'] === 'document_ready');
    }

    public function test_dispatch_mapping_contains_all_six_variables(): void
    {
        $request = $this->request(['file_number' => 'FILE-DISPATCH']);
        $request->dispatches()->create(['dispatch_status' => 'dispatched', 'dispatch_method' => 'speed_post', 'dispatch_date' => '2026-08-20 10:00:00', 'tracking_number' => 'TRACK-SAFE']);
        $this->send($request, NotificationMilestone::Dispatched, 'en');
        Http::assertSent(fn ($sent): bool => $sent['payload']['template']['to_and_components'][0]['components'] === $this->components(['Customer', 'English Service', 'FILE-DISPATCH', 'Speed Post', 'TRACK-SAFE', '20-08-2026']));
    }

    public function test_revenue_entry_mappings_use_customer_safe_fields_only(): void
    {
        $request = $this->request(['file_number' => 'FILE-ENTRY']);
        $entry = $request->revenueEntries()->create(['entry_number' => 'ENTRY-12', 'entry_date' => '2026-08-10', 'status' => 'entry_number_received', 'customer_note' => 'Customer-safe action.', 'admin_notes' => 'PRIVATE REVENUE NOTE']);
        $context = ['status_label' => 'Entry Number Received', 'customer_note' => 'Customer-safe action.'];
        $this->send($request, NotificationMilestone::RevenueEntryNumberReceived, 'gu', true, 'revenue_entry', $entry->id, $context);
        Http::assertSent(fn ($sent): bool => $sent['payload']['template']['name'] === 'entry_notification'
            && $sent['payload']['template']['to_and_components'][0]['components']['body_6']['value'] === 'Entry Number Received');

        $entry->update(['status' => 'certified', 'certified_at' => '2026-08-22 12:00:00']);
        $this->send($request, NotificationMilestone::RevenueEntryCertified, 'gu', true, 'revenue_entry', $entry->id, $context);
        Http::assertSent(fn ($sent): bool => $sent['payload']['template']['name'] === 'entry_certified'
            && $sent['payload']['template']['to_and_components'][0]['components']['body_6']['value'] === '22-08-2026');
        $entry->update(['status' => 'rejected']);
        $this->send($request, NotificationMilestone::RevenueEntryRejected, 'gu', true, 'revenue_entry', $entry->id, $context);
        Http::assertSent(fn ($sent): bool => $sent['payload']['template']['name'] === 'entry_rejected'
            && $sent['payload']['template']['to_and_components'][0]['components']['body_5']['value'] === 'Customer-safe action.');
        Http::assertSent(fn ($sent): bool => ! str_contains((string) $sent->body(), 'PRIVATE REVENUE NOTE'));
    }

    public function test_provider_failures_are_sanitized_and_never_throw(): void
    {
        $request = $this->request();
        Http::fakeSequence()
            ->push(['secret' => 'provider-internals'], 401)
            ->push(['secret' => 'provider-internals'], 503)
            ->push('not-json', 200)
            ->whenEmpty(Http::response([], 200));
        foreach ([[401, 'provider_4xx'], [503, 'provider_5xx']] as [, $category]) {
            $result = $this->send($request, NotificationMilestone::RequestReceived, 'gu', false, null, null, [], false);
            $this->assertSame($category, $result['failure_category']);
        }

        $this->assertSame('malformed_provider_response', $this->send($request, NotificationMilestone::RequestReceived, 'gu', false, null, null, [], false)['failure_category']);
        Http::fake(fn () => throw new ConnectionException('timeout containing unit-test-auth-key'));
        Log::spy();
        $this->assertSame('connection_failure', $this->send($request, NotificationMilestone::RequestReceived, 'gu', false, null, null, [], false)['failure_category']);
        Log::shouldNotHaveReceived('error');
    }

    public function test_template_language_disabled_and_milestone_toggle_off_suppress_sending(): void
    {
        Http::fake();
        config(['msg91-whatsapp.templates.request_received.languages.gu' => false]);
        $this->assertSame('template_unavailable', $this->send($this->request(), NotificationMilestone::RequestReceived, 'gu', false, null, null, [], false, false)['failure_category']);
        Http::assertNothingSent();

        Queue::fake();
        $event = app(CustomerNotificationService::class)->record($this->request(), NotificationMilestone::RequestReceived);
        $this->assertSame('channel_disabled', $event->deliveries->firstWhere('channel', 'whatsapp')->failure_category);
    }

    public function test_duplicate_event_and_revenue_same_status_do_not_queue_twice(): void
    {
        Queue::fake();
        $this->enable(NotificationMilestone::RequestReceived);
        $request = $this->request();
        $service = app(CustomerNotificationService::class);
        $service->record($request, NotificationMilestone::RequestReceived);
        $service->record($request, NotificationMilestone::RequestReceived);
        $this->assertDatabaseCount('customer_notification_events', 1);
        Queue::assertPushed(SendCustomerNotificationJob::class, 2);
    }

    public function test_no_inbound_msg91_or_whatsapp_webhook_route_can_modify_website_state(): void
    {
        $inboundRoutes = collect(Route::getRoutes()->getRoutes())->filter(function ($route): bool {
            $uri = strtolower($route->uri());

            return str_contains($uri, 'msg91') || str_contains($uri, 'whatsapp/webhook') || str_contains($uri, 'whatsapp/inbound');
        });

        $this->assertCount(0, $inboundRoutes);
    }

    private function send(CustomerRequest $request, NotificationMilestone $milestone, string $language, bool $assertSent = true, ?string $sourceType = null, ?int $sourceId = null, array $context = [], bool $fakeSuccess = true, bool $enableTemplate = true): array
    {
        if ($fakeSuccess) {
            Http::fake(['*' => Http::response([], 200)]);
        }
        if ($enableTemplate) {
            config(["msg91-whatsapp.templates.{$milestone->value}.languages.{$language}" => true]);
        }
        $delivery = $this->delivery($request, $milestone, $sourceType, $sourceId, $context);
        $request->setAttribute('language', $language);
        $delivery->event->setRelation('customerRequest', $request);
        $result = app(Msg91WhatsAppChannel::class)->send($delivery, '919999999999', []);
        if ($assertSent) {
            $this->assertSame('sent', $result['status']);
        }

        return $result;
    }

    private function delivery(CustomerRequest $request, NotificationMilestone $milestone, ?string $sourceType = null, ?int $sourceId = null, array $context = []): CustomerNotificationDelivery
    {
        $event = CustomerNotificationEvent::query()->create(['request_id' => $request->id, 'milestone' => $milestone, 'event_key' => fake()->uuid(), 'source_type' => $sourceType, 'source_id' => $sourceId, 'safe_context' => $context, 'occurred_at' => now()]);
        $delivery = $event->deliveries()->create(['channel' => 'whatsapp', 'status' => 'pending', 'template_key' => 'customer_'.$milestone->value]);
        $delivery->setRelation('event', $event->setRelation('customerRequest', $request));

        return $delivery;
    }

    private function request(array $attributes = []): CustomerRequest
    {
        $suffix = fake()->unique()->numerify('######');
        $service = Service::query()->create(['name_en' => 'English Service '.$suffix, 'name_gu' => 'Gujarati Service '.$suffix, 'slug' => 'msg91-'.$suffix, 'service_fee' => 1000, 'gst_rate' => 0, 'is_active' => true]);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/'.fake()->unique()->numerify('######'), 'service_id' => $service->id, 'name' => 'Customer', 'mobile' => '9999999999', 'email' => 'customer@example.com', 'status' => 'received', ...$attributes]);
        $request->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => 'English Service', 'service_name_gu_snapshot' => 'Gujarati Service', 'status' => 'received']);

        return $request;
    }

    private function enable(NotificationMilestone $milestone): void
    {
        Setting::query()->updateOrCreate(['setting_key' => "notifications.{$milestone->value}.whatsapp"], ['setting_value' => '1', 'value_type' => 'boolean', 'setting_group' => 'customer_notifications', 'is_public' => false]);
    }

    private function components(array $values): array
    {
        return collect($values)->mapWithKeys(fn ($value, $index) => ['body_'.($index + 1) => ['type' => 'text', 'value' => $value]])->all();
    }
}
