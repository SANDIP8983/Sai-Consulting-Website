<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppChannelInterface;
use App\Mail\AdminNewCustomerRequestMail;
use App\Mail\CustomerMilestoneMail;
use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminNewCustomerRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_online_request_queues_one_admin_email_and_preserves_customer_acknowledgement(): void
    {
        Mail::fake();
        URL::forceRootUrl('https://saiconsultingchanasma.in');
        URL::forceScheme('https');
        $this->enableNotifications();
        $this->mock(WhatsAppChannelInterface::class, fn (MockInterface $mock) => $mock->shouldNotReceive('send'));

        $this->post(route('request.store'), $this->payload($this->service()))
            ->assertRedirect(route('request.success'));

        $request = CustomerRequest::query()->sole();
        Mail::assertQueued(AdminNewCustomerRequestMail::class, function (AdminNewCustomerRequestMail $mail) use ($request): bool {
            $html = $mail->render();

            return $mail->hasTo('office@example.com')
                && $mail->queue === 'customer-notifications'
                && str_contains($html, $request->reference_no)
                && str_contains($html, route('admin.requests.show', $request))
                && $mail->attachments === []
                && $mail->rawAttachments === []
                && $mail->diskAttachments === [];
        });
        Mail::assertQueued(AdminNewCustomerRequestMail::class, 1);
        Mail::assertSent(CustomerMilestoneMail::class, 1);
        $this->assertDatabaseHas('customer_notification_deliveries', [
            'channel' => 'whatsapp',
            'status' => 'skipped',
            'failure_category' => 'channel_disabled',
        ]);
    }

    public function test_disabled_admin_notification_does_not_queue_admin_email(): void
    {
        Mail::fake();
        $this->businessEmail();
        $this->setting('notifications.admin_new_online_request.email', '0', 'admin_notifications');

        app(RequestWorkflowService::class)->submit($this->payload($this->service()), []);

        Mail::assertNotQueued(AdminNewCustomerRequestMail::class);
    }

    public function test_invalid_submission_does_not_queue_admin_email(): void
    {
        Mail::fake();
        $this->enableNotifications();

        $this->post(route('request.store'), [])->assertSessionHasErrors(['service_id', 'name', 'mobile']);

        Mail::assertNotQueued(AdminNewCustomerRequestMail::class);
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_offline_request_does_not_queue_admin_new_online_request_email(): void
    {
        Mail::fake();
        $this->enableNotifications();
        $service = $this->service();

        app(RequestWorkflowService::class)->submitOffline(
            $this->payload($service),
            [],
            User::factory()->create(['role' => 'admin']),
        );

        Mail::assertNotQueued(AdminNewCustomerRequestMail::class);
    }

    public function test_duplicate_online_submission_does_not_queue_a_duplicate_admin_email(): void
    {
        Mail::fake();
        $this->enableNotifications();
        $payload = $this->payload($this->service());

        $this->post(route('request.store'), $payload)->assertRedirect(route('request.success'));
        $this->post(route('request.store'), $payload)->assertSessionHasErrors('request');

        Mail::assertQueued(AdminNewCustomerRequestMail::class, 1);
        $this->assertDatabaseCount('requests', 1);
    }

    public function test_admin_mail_queue_failure_does_not_roll_back_the_online_request(): void
    {
        $this->enableNotifications();
        Setting::query()->where('setting_key', 'notifications.request_received.email')->update(['setting_value' => '0']);
        Mail::shouldReceive('to')
            ->once()
            ->with('office@example.com')
            ->andThrow(new \RuntimeException('Queue unavailable'));

        $request = app(RequestWorkflowService::class)->submit($this->payload($this->service()), []);

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'reference_no' => $request->reference_no,
            'request_origin' => 'online',
        ]);
    }

    private function enableNotifications(): void
    {
        $this->businessEmail();
        $this->setting('notifications.admin_new_online_request.email', '1', 'admin_notifications');
        $this->setting('notifications.request_received.email', '1');
        $this->setting('notifications.request_received.whatsapp', '0');
    }

    private function businessEmail(): void
    {
        $this->setting('contact.email', 'office@example.com', 'contact', true);
    }

    private function setting(string $key, string $value, string $group = 'customer_notifications', bool $public = false): void
    {
        Setting::query()->create([
            'setting_key' => $key,
            'setting_value' => $value,
            'value_type' => $group === 'contact' ? 'string' : 'boolean',
            'setting_group' => $group,
            'is_public' => $public,
        ]);
    }

    private function service(): Service
    {
        return Service::query()->create([
            'name_en' => 'Title Search',
            'name_gu' => 'Title Search',
            'slug' => 'admin-notification-'.fake()->unique()->numerify('######'),
            'estimated_days' => 7,
            'is_active' => true,
            'available_online' => true,
            'available_offline' => true,
        ]);
    }

    private function payload(Service $service): array
    {
        return [
            'service_id' => $service->id,
            'service_ids' => [$service->id],
            'name' => 'Controlled Customer',
            'mobile' => '9999999999',
            'email' => 'customer@example.com',
            'property_village' => 'Chanasma',
            'property_taluka' => 'Chanasma',
            'property_district' => 'Patan',
            'declaration' => '1',
        ];
    }
}
