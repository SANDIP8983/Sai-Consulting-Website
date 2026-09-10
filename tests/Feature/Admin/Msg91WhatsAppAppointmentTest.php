<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\NotificationMilestone;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Setting;
use App\Services\Notifications\AppointmentNotificationService;
use App\Services\Notifications\Msg91WhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Msg91WhatsAppAppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['msg91-whatsapp.enabled' => true, 'msg91-whatsapp.authkey' => 'unit-test-auth-key', 'msg91-whatsapp.integrated_number' => 'unit-test-sender']);
    }

    public function test_requested_appointment_milestones_are_configured_dispatched_and_use_the_four_variable_contract(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['message_id' => 'msg-123'], 200)]);
        $appointment = $this->appointment();

        foreach ([NotificationMilestone::AppointmentConfirmed, NotificationMilestone::AppointmentRescheduled, NotificationMilestone::AppointmentCancelled] as $milestone) {
            config(["msg91-whatsapp.templates.{$milestone->value}.languages.gu" => true]);
            Setting::create(['setting_key' => "notifications.{$milestone->value}.whatsapp", 'setting_value' => '1', 'value_type' => 'boolean', 'setting_group' => 'customer_notifications', 'is_public' => false]);
            $event = app(AppointmentNotificationService::class)->record($appointment, $milestone);
            $delivery = $event->deliveries->firstWhere('channel', 'whatsapp');
            $this->assertSame('pending', $delivery->status);
            $result = app(Msg91WhatsAppChannel::class)->send($delivery, '919876543210', []);
            $this->assertSame('sent', $result['status']);
            $this->assertSame('msg-123', $result['provider_message_id']);
        }

        Http::assertSent(fn ($request): bool => $request['payload']['template']['name'] === 'appointment_confirmed'
            && $request['payload']['template']['language']['code'] === 'gu'
            && $request['payload']['template']['to_and_components'][0]['components'] === [
                'body_1' => ['type' => 'text', 'value' => 'Customer'],
                'body_2' => ['type' => 'text', 'value' => 'APT/2026/000001'],
                'body_3' => ['type' => 'text', 'value' => '17 Aug 2026'],
                'body_4' => ['type' => 'text', 'value' => '10:30 AM'],
            ]);
    }

    public function test_unavailable_appointment_reminder_cannot_be_enabled_or_sent(): void
    {
        Queue::fake();
        $event = app(AppointmentNotificationService::class)->record($this->appointment(), NotificationMilestone::AppointmentReminder);

        $this->assertSame('skipped', $event->deliveries->firstWhere('channel', 'whatsapp')->status);
        $this->assertSame('channel_disabled', $event->deliveries->firstWhere('channel', 'whatsapp')->failure_category);
    }

    public function test_appointment_uses_the_configured_english_fallback_when_english_template_is_enabled(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        config([
            'msg91-whatsapp.default_language' => 'en',
            'msg91-whatsapp.templates.appointment_confirmed.languages.en' => true,
        ]);
        Setting::create(['setting_key' => 'notifications.appointment_confirmed.whatsapp', 'setting_value' => '1', 'value_type' => 'boolean', 'setting_group' => 'customer_notifications', 'is_public' => false]);
        $appointment = $this->appointment();
        $event = app(AppointmentNotificationService::class)->record($appointment, NotificationMilestone::AppointmentConfirmed);
        $delivery = $event->deliveries->firstWhere('channel', 'whatsapp');

        $result = app(Msg91WhatsAppChannel::class)->send($delivery, '919876543210', []);

        $this->assertSame('sent', $result['status']);
        Http::assertSent(fn ($request): bool => $request['payload']['template']['language']['code'] === 'en');
    }

    private function appointment(): Appointment
    {
        $service = Service::create(['name_en' => 'Consultation', 'name_gu' => 'પરામર્શ', 'slug' => 'consultation', 'is_active' => true]);

        return Appointment::create(['reference_no' => 'APT/2026/000001', 'customer_name' => 'Customer', 'mobile' => '9876543210', 'email' => 'customer@example.com', 'service_id' => $service->id, 'scheduled_at' => '2026-08-17 10:30:00', 'status' => AppointmentStatus::Pending, 'source' => 'admin', 'slot_key' => '2026-08-17 10:30']);
    }
}
