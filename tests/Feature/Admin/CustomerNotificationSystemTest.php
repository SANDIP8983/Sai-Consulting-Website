<?php

namespace Tests\Feature\Admin;

use App\Enums\NotificationMilestone;
use App\Jobs\SendCustomerNotificationJob;
use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\CustomerMessageFactory;
use App\Services\Notifications\CustomerNotificationService;
use App\Services\Notifications\DisabledWhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerNotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_milestone_has_editable_email_and_whatsapp_defaults(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);
        $response = $this->actingAs($super)->get(route('admin.settings.customer-notifications'))->assertOk();
        foreach (NotificationMilestone::cases() as $milestone) {
            $response->assertSee($milestone->label());
            $this->assertIsBool(app(CustomerNotificationService::class)->enabled($milestone, 'email'));
            $this->assertTrue(app(CustomerNotificationService::class)->enabled($milestone, 'whatsapp'));
        }
        $this->assertFalse(app(CustomerNotificationService::class)->enabled(NotificationMilestone::ProcessingStarted, 'email'));
        $this->assertFalse(app(CustomerNotificationService::class)->enabled(NotificationMilestone::DraftReady, 'email'));
    }

    public function test_staff_and_unapproved_admin_are_denied_but_explicitly_authorized_admin_can_view(): void
    {
        foreach (['staff', 'admin'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('admin.notifications.index'))->assertForbidden();
            $this->actingAs($actor)->get(route('admin.settings.customer-notifications'))->assertForbidden();
        }
        config(['permissions.roles.admin' => [...config('permissions.roles.admin'), 'notifications.view']]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.notifications.index'))->assertOk();
    }

    public function test_idempotent_event_creates_only_one_delivery_per_channel_and_masks_recipients(): void
    {
        Queue::fake();
        $request = $this->request();
        $service = app(CustomerNotificationService::class);
        $first = $service->record($request, NotificationMilestone::RequestReceived);
        $second = $service->record($request, NotificationMilestone::RequestReceived);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('customer_notification_events', 1);
        $this->assertDatabaseCount('customer_notification_deliveries', 2);
        $this->assertDatabaseHas('customer_notification_deliveries', ['channel' => 'email', 'recipient_masked' => 'c***@example.com']);
        $this->assertDatabaseHas('customer_notification_deliveries', ['channel' => 'whatsapp', 'recipient_masked' => '********9999']);
        Queue::assertPushed(SendCustomerNotificationJob::class, 2);
    }

    public function test_missing_email_and_disabled_channel_skip_safely(): void
    {
        Queue::fake();
        $request = $this->request(['email' => null]);
        Setting::query()->create(['setting_key' => 'notifications.request_received.whatsapp', 'setting_value' => '0', 'value_type' => 'boolean', 'setting_group' => 'customer_notifications', 'is_public' => false]);
        app(CustomerNotificationService::class)->record($request, NotificationMilestone::RequestReceived);
        $this->assertDatabaseHas('customer_notification_deliveries', ['channel' => 'email', 'status' => 'skipped', 'failure_category' => 'missing_or_invalid_recipient']);
        $this->assertDatabaseHas('customer_notification_deliveries', ['channel' => 'whatsapp', 'status' => 'skipped', 'failure_category' => 'channel_disabled']);
        Queue::assertNothingPushed();
    }

    public function test_email_sends_once_and_job_retry_is_idempotent(): void
    {
        Queue::fake();
        Mail::fake();
        $event = app(CustomerNotificationService::class)->record($this->request(), NotificationMilestone::Accepted);
        $delivery = $event->deliveries->firstWhere('channel', 'email');
        $job = new SendCustomerNotificationJob($delivery->id);
        $job->handle(app(CustomerNotificationService::class), app(CustomerMessageFactory::class), app(DisabledWhatsAppChannel::class));
        $job->handle(app(CustomerNotificationService::class), app(CustomerMessageFactory::class), app(DisabledWhatsAppChannel::class));
        Mail::assertSentCount(1);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempt_count);
    }

    public function test_enabled_whatsapp_without_provider_is_skipped_not_sent(): void
    {
        Queue::fake();
        $event = app(CustomerNotificationService::class)->record($this->request(), NotificationMilestone::PaymentReceived);
        $delivery = $event->deliveries->firstWhere('channel', 'whatsapp');
        (new SendCustomerNotificationJob($delivery->id))->handle(app(CustomerNotificationService::class), app(CustomerMessageFactory::class), app(DisabledWhatsAppChannel::class));
        $this->assertDatabaseHas('customer_notification_deliveries', ['id' => $delivery->id, 'status' => 'skipped', 'provider' => 'disabled', 'failure_category' => 'provider_not_configured']);
    }

    public function test_mail_transport_failure_is_logged_without_changing_business_state(): void
    {
        Queue::fake();
        $request = $this->request(['status' => 'completed']);
        $event = app(CustomerNotificationService::class)->record($request, NotificationMilestone::Completed);
        $delivery = $event->deliveries->firstWhere('channel', 'email');
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP timeout containing no credentials'));
        try {
            (new SendCustomerNotificationJob($delivery->id))->handle(app(CustomerNotificationService::class), app(CustomerMessageFactory::class), app(DisabledWhatsAppChannel::class));
            $this->fail('Expected the queue job to expose the retryable transport failure.');
        } catch (\RuntimeException) {
            $this->assertSame('completed', $request->fresh()->status);
            $this->assertDatabaseHas('customer_notification_deliveries', ['id' => $delivery->id, 'status' => 'failed', 'failure_category' => 'transport_failure']);
        }
    }

    public function test_message_is_customer_safe_and_tracking_url_has_no_private_credentials(): void
    {
        $request = $this->request(['details' => 'INTERNAL Aadhaar PAN password staff@example.com']);
        $message = app(CustomerMessageFactory::class)->make($request, NotificationMilestone::Rejected);
        $this->assertStringContainsString($request->reference_no, $message['body']);
        $this->assertStringContainsString(route('request.track'), $message['body']);
        foreach (['Aadhaar', 'PAN', 'password', 'staff@example.com', $request->mobile] as $private) {
            $this->assertStringNotContainsString($private, $message['body']);
        }
        $this->assertStringNotContainsString('?', $message['body']);
    }

    private function request(array $attributes = []): CustomerRequest
    {
        $service = Service::query()->create(['name_en' => 'Notification Service', 'name_gu' => 'સેવા', 'slug' => 'notification-'.fake()->unique()->numerify('######'), 'service_fee' => 1000, 'gst_rate' => 18, 'is_active' => true]);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/'.fake()->unique()->numerify('######'), 'service_id' => $service->id, 'name' => 'Customer', 'mobile' => '9999999999', 'whatsapp' => null, 'email' => 'customer@example.com', 'status' => 'received', ...$attributes]);
        $request->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en, 'status' => 'received']);

        return $request;
    }
}
