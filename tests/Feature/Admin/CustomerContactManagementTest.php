<?php

namespace Tests\Feature\Admin;

use App\Enums\NotificationMilestone;
use App\Jobs\SendCustomerNotificationJob;
use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\Notifications\CustomerMessageFactory;
use App\Services\Notifications\CustomerNotificationService;
use App\Services\Notifications\DisabledWhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerContactManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_request_requires_mobile_but_accepts_missing_email_and_whatsapp(): void
    {
        $service = $this->service();
        $payload = ['service_id' => $service->id, 'service_ids' => [$service->id], 'name' => 'Public Customer', 'property_village' => 'Patan', 'property_taluka' => 'Patan', 'property_district' => 'Patan', 'declaration' => '1'];
        $this->post(route('request.store'), $payload)->assertSessionHasErrors('mobile');
        $this->post(route('request.store'), [...$payload, 'mobile' => '9999999999'])->assertRedirect(route('request.success'));
        $request = CustomerRequest::query()->sole();
        $this->assertNull($request->email);
        $this->assertNull($request->whatsapp);
    }

    public function test_admin_can_add_and_update_optional_contacts_with_masked_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $reference = $request->reference_no;
        $this->actingAs($admin)->patch(route('admin.requests.contact.update', $request), ['mobile' => '9999999999', 'whatsapp' => '9888888888', 'email' => 'First@Example.com'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('requests', ['id' => $request->id, 'mobile' => '9999999999', 'whatsapp' => '9888888888', 'email' => 'first@example.com', 'reference_no' => $reference]);
        $history = $request->contactChangeHistory()->sole();
        $this->assertSame(['whatsapp', 'email'], $history->changed_fields);
        $this->assertSame(['whatsapp' => null, 'email' => null], $history->masked_old_values);
        $this->assertSame('******8888', $history->masked_new_values['whatsapp']);
        $this->assertSame('f***@example.com', $history->masked_new_values['email']);
        $this->assertJsonStringNotEqualsJsonString(json_encode(['email' => 'first@example.com']), json_encode($history->masked_new_values));

        $this->actingAs($admin)->patch(route('admin.requests.contact.update', $request), ['mobile' => '9876543210', 'whatsapp' => '9777777777', 'email' => 'second@example.com'])->assertSessionHasNoErrors();
        $this->assertSame(2, $request->contactChangeHistory()->count());
        $this->assertSame('under_review', $request->fresh()->status);
        $this->assertSame($reference, $request->fresh()->reference_no);
    }

    public function test_invalid_contact_is_rejected_and_staff_cannot_modify(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $request = $this->request(['assigned_user_id' => $staff->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);
        $this->actingAs($admin)->patch(route('admin.requests.contact.update', $request), ['mobile' => '123', 'whatsapp' => '1234567890', 'email' => 'invalid'])->assertSessionHasErrors(['mobile', 'whatsapp', 'email']);
        $this->actingAs($staff)->patch(route('admin.requests.contact.update', $request), ['mobile' => '9876543210', 'whatsapp' => '9888888888', 'email' => 'changed@example.com'])->assertForbidden();
        $this->assertSame('9999999999', $request->fresh()->mobile);
        $this->assertDatabaseCount('request_contact_change_histories', 0);
    }

    public function test_notification_recipient_fallback_priority_and_prospective_contact_updates(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $notifications = app(CustomerNotificationService::class);
        $this->assertSame('919999999999', $notifications->recipient($request, 'whatsapp'));
        $this->assertNull($notifications->recipient($request, 'email'));
        $old = $notifications->record($request, NotificationMilestone::RequestReceived);
        $this->assertSame('skipped', $old->deliveries->firstWhere('channel', 'email')->status);

        $this->actingAs($admin)->patch(route('admin.requests.contact.update', $request), ['mobile' => '9999999999', 'whatsapp' => '9888888888', 'email' => 'future@example.com'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_notification_events', 1);
        $this->assertDatabaseCount('customer_notification_deliveries', 2);
        $this->assertSame('919888888888', $notifications->recipient($request->fresh(), 'whatsapp'));
        $this->assertSame('future@example.com', $notifications->recipient($request->fresh(), 'email'));

        $future = $notifications->record($request->fresh(), NotificationMilestone::Accepted);
        $this->assertSame('f***@example.com', $future->deliveries->firstWhere('channel', 'email')->recipient_masked);
        $this->assertSame('********8888', $future->deliveries->firstWhere('channel', 'whatsapp')->recipient_masked);
        $this->assertSame('skipped', $old->deliveries->firstWhere('channel', 'email')->fresh()->status);
    }

    public function test_contact_history_is_not_exposed_by_public_tracking(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request();
        $this->actingAs($admin)->patch(route('admin.requests.contact.update', $request), ['mobile' => '9876543210', 'whatsapp' => '9888888888', 'email' => 'private@example.com'])->assertSessionHasNoErrors();
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => '9876543210'])->assertOk()->assertDontSee('Contact Change History')->assertDontSee('******9999')->assertDontSee($admin->name);
    }

    public function test_pending_delivery_does_not_silently_switch_to_updated_recipient(): void
    {
        Queue::fake();
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->request(['email' => 'old@example.com']);
        $event = app(CustomerNotificationService::class)->record($request, NotificationMilestone::Accepted);
        $delivery = $event->deliveries->firstWhere('channel', 'email');
        $this->actingAs($admin)->patch(route('admin.requests.contact.update', $request), ['mobile' => $request->mobile, 'whatsapp' => null, 'email' => 'new@example.com'])->assertSessionHasNoErrors();
        (new SendCustomerNotificationJob($delivery->id))->handle(app(CustomerNotificationService::class), app(CustomerMessageFactory::class), app(DisabledWhatsAppChannel::class));
        Mail::assertNothingSent();
        $this->assertDatabaseHas('customer_notification_deliveries', ['id' => $delivery->id, 'status' => 'skipped', 'failure_category' => 'recipient_changed_before_delivery', 'recipient_masked' => 'o***@example.com']);
    }

    private function request(array $attributes = []): CustomerRequest
    {
        $service = $this->service();
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/'.fake()->unique()->numerify('######'), 'service_id' => $service->id, 'name' => 'Customer', 'mobile' => '9999999999', 'whatsapp' => null, 'email' => null, 'status' => 'under_review', ...$attributes]);
        $request->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => $service->name_en, 'status' => 'under_review']);

        return $request;
    }

    private function service(): Service
    {
        $suffix = fake()->unique()->numerify('######');

        return Service::query()->create(['name_en' => 'Contact Service '.$suffix, 'name_gu' => 'સેવા '.$suffix, 'slug' => 'contact-'.$suffix, 'service_fee' => 1000, 'gst_rate' => 18, 'is_active' => true, 'available_online' => true]);
    }
}
