<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\NotificationMilestone;
use App\Jobs\SendCustomerNotificationJob;
use App\Mail\CustomerMilestoneMail;
use App\Models\Appointment;
use App\Models\AppointmentBlock;
use App\Models\Holiday;
use App\Models\OfficeTiming;
use App\Models\Service;
use App\Models\User;
use App\Services\Notifications\AppointmentMessageFactory;
use App\Services\Notifications\CustomerMessageFactory;
use App\Services\Notifications\CustomerNotificationService;
use App\Services\Notifications\DisabledWhatsAppChannel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private string $date = '2026-08-17';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-14 10:00:00 Asia/Kolkata');
        $this->service = Service::create(['name_en' => 'Consultation', 'name_gu' => 'પરામર્શ', 'slug' => 'consultation', 'is_active' => true]);
        OfficeTiming::create(['day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '17:00', 'is_closed' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_public_page_only_shows_active_services(): void
    {
        Service::create(['name_en' => 'Hidden', 'name_gu' => 'છુપાયેલ', 'slug' => 'hidden', 'is_active' => false]);
        $this->get(route('appointments.create'))->assertOk()->assertSee('Consultation')->assertDontSee('Hidden');
    }

    public function test_successful_booking_generates_reference_history_and_queues_notification(): void
    {
        Queue::fake();
        $this->post(route('appointments.store'), $this->payload())->assertRedirect();
        $a = Appointment::sole();
        $this->assertMatchesRegularExpression('#^APT/2026/\d{6}$#', $a->reference_no);
        $this->assertSame('pending', $a->status->value);
        $this->assertDatabaseHas('appointment_histories', ['appointment_id' => $a->id, 'action' => 'created']);
        $this->assertDatabaseHas('customer_notification_events', ['appointment_id' => $a->id, 'request_id' => null, 'milestone' => 'appointment_received', 'source_type' => 'appointment', 'source_id' => $a->id]);
        $this->assertDatabaseHas('customer_notification_deliveries', ['channel' => 'email', 'status' => 'pending', 'recipient_masked' => 'c***@example.com']);
        Queue::assertPushed(SendCustomerNotificationJob::class, fn ($job) => $job->queue === config('customer-notifications.queue'));
    }

    public function test_public_slot_preserves_exact_asia_kolkata_business_time_everywhere(): void
    {
        Queue::fake();
        $this->getJson(route('appointments.availability', ['date' => '2026-08-17', 'service_id' => $this->service->id]))
            ->assertJsonFragment(['value' => '10:30', 'label' => '10:30 AM – 11:00 AM']);

        $this->post(route('appointments.store'), $this->payload())->assertRedirect();
        $appointment = Appointment::with('service')->sole();

        $this->assertSame('2026-08-17 10:30:00', DB::table('appointments')->where('id', $appointment->id)->value('scheduled_at'));
        $this->assertSame('2026-08-17 10:30', $appointment->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('2026-08-17 10:30:00', DB::table('appointment_histories')->where('appointment_id', $appointment->id)->value('new_scheduled_at'));

        $this->get(route('appointments.success', ['reference' => $appointment->reference_no]))->assertOk()->assertSee('17 Aug 2026, 10:30 AM');
        $this->actingAs(User::factory()->create())->get(route('admin.appointments.show', $appointment))->assertOk()->assertSee('17 Aug 2026, 10:30 AM');

        $email = app(AppointmentMessageFactory::class)->make($appointment, NotificationMilestone::AppointmentReceived);
        $this->assertStringContainsString('Time: 10:30 AM', $email['body']);
    }

    public function test_missing_email_is_logged_as_skipped_without_failing_booking(): void
    {
        Queue::fake();
        $this->post(route('appointments.store'), $this->payload(['email' => null]))->assertRedirect();
        $this->assertDatabaseHas('customer_notification_deliveries', ['channel' => 'email', 'status' => 'skipped', 'failure_category' => 'missing_or_invalid_recipient']);
    }

    public function test_shared_delivery_job_sends_appointment_email_and_updates_log(): void
    {
        Queue::fake();
        Mail::fake();
        $this->post(route('appointments.store'), $this->payload())->assertRedirect();
        $delivery = DB::table('customer_notification_deliveries')->where('channel', 'email')->first();

        (new SendCustomerNotificationJob($delivery->id))->handle(
            app(CustomerNotificationService::class),
            app(CustomerMessageFactory::class),
            app(DisabledWhatsAppChannel::class),
        );

        Mail::assertSent(CustomerMilestoneMail::class, fn ($mail) => str_contains($mail->messageData['body'], 'Time: 10:30 AM'));
        $this->assertDatabaseHas('customer_notification_deliveries', ['id' => $delivery->id, 'status' => 'sent', 'provider' => config('mail.default')]);
    }

    public function test_exact_same_local_time_is_detected_as_a_conflict(): void
    {
        Queue::fake();
        $this->post(route('appointments.store'), $this->payload())->assertRedirect();
        $this->getJson(route('appointments.availability', ['date' => '2026-08-17', 'service_id' => $this->service->id]))->assertJsonMissing(['value' => '10:30']);
        $this->post(route('appointments.store'), $this->payload())->assertStatus(422);
    }

    public function test_invalid_contact_and_past_date_are_rejected(): void
    {
        $this->post(route('appointments.store'), $this->payload(['mobile' => '123', 'email' => 'bad', 'appointment_date' => '2026-08-13']))->assertSessionHasErrors(['mobile', 'email', 'appointment_date']);
    }

    public function test_availability_honours_hours_blocks_holidays_and_conflicts(): void
    {
        $this->getJson(route('appointments.availability', ['date' => $this->date, 'service_id' => $this->service->id]))->assertOk()->assertJsonFragment(['value' => '10:00'])->assertJsonMissing(['value' => '17:00']);
        AppointmentBlock::create(['block_date' => $this->date, 'starts_at' => '11:00', 'ends_at' => '12:00']);
        $this->getJson(route('appointments.availability', ['date' => $this->date, 'service_id' => $this->service->id]))->assertJsonMissing(['value' => '11:00'])->assertJsonMissing(['value' => '11:30']);
        $this->post(route('appointments.store'), $this->payload())->assertRedirect();
        $this->post(route('appointments.store'), $this->payload())->assertStatus(422);
        Holiday::create(['holiday_date' => $this->date, 'title' => 'Closed', 'is_closed' => true]);
        $this->getJson(route('appointments.availability', ['date' => $this->date, 'service_id' => $this->service->id]))->assertJsonCount(0, 'slots');
    }

    public function test_closed_weekday_and_outside_hours_are_rejected(): void
    {
        OfficeTiming::where('day_of_week', 1)->update(['is_closed' => true]);
        $this->post(route('appointments.store'), $this->payload())->assertStatus(422);
        OfficeTiming::where('day_of_week', 1)->update(['is_closed' => false]);
        $this->post(route('appointments.store'), $this->payload(['appointment_time' => '09:30']))->assertStatus(422);
    }

    public function test_admin_can_create_confirm_reschedule_cancel_and_complete_with_history(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.appointments.store'), $this->payload())->assertRedirect();
        $a = Appointment::sole();
        $this->patch(route('admin.appointments.transition', $a), ['status' => 'confirmed'])->assertRedirect();
        $this->assertDatabaseHas('customer_notification_events', ['appointment_id' => $a->id, 'milestone' => 'appointment_confirmed']);
        $this->patch(route('admin.appointments.transition', $a), ['status' => 'rescheduled', 'appointment_date' => $this->date, 'appointment_time' => '14:30'])->assertRedirect();
        $this->assertDatabaseHas('customer_notification_events', ['appointment_id' => $a->id, 'milestone' => 'appointment_rescheduled']);
        $this->assertSame('2026-08-17 14:30:00', DB::table('appointments')->where('id', $a->id)->value('scheduled_at'));
        $this->assertDatabaseHas('appointment_histories', ['appointment_id' => $a->id, 'action' => 'rescheduled', 'old_scheduled_at' => '2026-08-17 10:30:00', 'new_scheduled_at' => '2026-08-17 14:30:00']);
        $this->patch(route('admin.appointments.transition', $a), ['status' => 'cancelled', 'note' => 'Customer asked'])->assertRedirect();
        $this->assertDatabaseHas('customer_notification_events', ['appointment_id' => $a->id, 'milestone' => 'appointment_cancelled']);
        $this->assertDatabaseHas('appointment_histories', ['appointment_id' => $a->id, 'action' => 'cancelled', 'note' => 'Customer asked']);
        $other = $this->createAppointment('APT/2026/999999', '12:00');
        $this->patch(route('admin.appointments.transition', $other), ['status' => 'completed'])->assertRedirect();
        $this->assertSame(AppointmentStatus::Completed, $other->fresh()->status);
    }

    public function test_admin_routes_require_authorization(): void
    {
        $this->get(route('admin.appointments.index'))->assertRedirect(route('login'));
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('admin.appointments.index'))->assertForbidden();
    }

    public function test_reminder_is_sent_once_and_cancelled_is_skipped(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-08-16 10:30:00 Asia/Kolkata');
        $due = $this->createAppointment('APT/2026/000010', '10:30', AppointmentStatus::Confirmed, '2026-08-17');
        $this->createAppointment('APT/2026/000011', '11:00', AppointmentStatus::Cancelled, '2026-08-17');
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->assertNotNull($due->fresh()->reminder_sent_at);
        $this->assertDatabaseHas('customer_notification_events', ['appointment_id' => $due->id, 'milestone' => 'appointment_reminder', 'source_type' => 'appointment', 'source_id' => $due->id]);
        $this->assertSame(1, DB::table('customer_notification_events')->where('appointment_id', $due->id)->where('milestone', 'appointment_reminder')->count());
        Queue::assertPushed(SendCustomerNotificationJob::class, 2);
    }

    public function test_notification_log_identifies_and_filters_appointment_events(): void
    {
        Queue::fake();
        $this->post(route('appointments.store'), $this->payload())->assertRedirect();
        $appointment = Appointment::sole();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->get(route('admin.notifications.index', ['q' => $appointment->reference_no]))
            ->assertOk()
            ->assertSee($appointment->reference_no)
            ->assertSee('Appointment Booking Received')
            ->assertSee('Appointment');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['customer_name' => 'Test Customer', 'mobile' => '9876543210', 'email' => 'customer@example.com', 'service_id' => $this->service->id, 'appointment_date' => $this->date, 'appointment_time' => '10:30', 'declaration' => '1'], $overrides);
    }

    private function createAppointment(string $reference, string $time, AppointmentStatus $status = AppointmentStatus::Pending, ?string $date = null): Appointment
    {
        $at = Carbon::parse(($date ?: $this->date).' '.$time, 'Asia/Kolkata');

        return Appointment::create(['reference_no' => $reference, 'customer_name' => 'Customer', 'mobile' => '9876543210', 'email' => 'customer@example.com', 'service_id' => $this->service->id, 'scheduled_at' => $at, 'status' => $status, 'source' => 'admin', 'slot_key' => in_array($status->value, AppointmentStatus::active(), true) ? $at->format('Y-m-d H:i') : null]);
    }
}
