<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Jobs\SendAppointmentNotificationJob;
use App\Models\Appointment;
use App\Models\AppointmentBlock;
use App\Models\Holiday;
use App\Models\OfficeTiming;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private string $date = '2026-08-18';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-14 10:00:00 Asia/Kolkata');
        $this->service = Service::create(['name_en' => 'Consultation', 'name_gu' => 'પરામર્શ', 'slug' => 'consultation', 'is_active' => true]);
        OfficeTiming::create(['day_of_week' => 2, 'opens_at' => '10:00', 'closes_at' => '17:00', 'is_closed' => false]);
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
        Queue::assertPushed(SendAppointmentNotificationJob::class);
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
        OfficeTiming::where('day_of_week', 2)->update(['is_closed' => true]);
        $this->post(route('appointments.store'), $this->payload())->assertStatus(422);
        OfficeTiming::where('day_of_week', 2)->update(['is_closed' => false]);
        $this->post(route('appointments.store'), $this->payload(['appointment_time' => '09:30']))->assertStatus(422);
    }

    public function test_admin_can_create_confirm_reschedule_cancel_and_complete_with_history(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.appointments.store'), $this->payload())->assertRedirect();
        $a = Appointment::sole();
        $this->patch(route('admin.appointments.transition', $a), ['status' => 'confirmed'])->assertRedirect();
        $this->patch(route('admin.appointments.transition', $a), ['status' => 'rescheduled', 'appointment_date' => $this->date, 'appointment_time' => '10:30'])->assertRedirect();
        $this->patch(route('admin.appointments.transition', $a), ['status' => 'cancelled', 'note' => 'Customer asked'])->assertRedirect();
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
        Carbon::setTestNow('2026-08-17 10:00:00 Asia/Kolkata');
        $due = $this->createAppointment('APT/2026/000010', '10:00', AppointmentStatus::Confirmed, '2026-08-18');
        $this->createAppointment('APT/2026/000011', '11:00', AppointmentStatus::Cancelled, '2026-08-18');
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->assertNotNull($due->fresh()->reminder_sent_at);
        Queue::assertPushed(SendAppointmentNotificationJob::class, 1);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['customer_name' => 'Test Customer', 'mobile' => '9876543210', 'email' => 'customer@example.com', 'service_id' => $this->service->id, 'appointment_date' => $this->date, 'appointment_time' => '10:00', 'declaration' => '1'], $overrides);
    }

    private function createAppointment(string $reference, string $time, AppointmentStatus $status = AppointmentStatus::Pending, ?string $date = null): Appointment
    {
        $at = Carbon::parse(($date ?: $this->date).' '.$time, 'Asia/Kolkata')->utc();

        return Appointment::create(['reference_no' => $reference, 'customer_name' => 'Customer', 'mobile' => '9876543210', 'email' => 'customer@example.com', 'service_id' => $this->service->id, 'scheduled_at' => $at, 'status' => $status, 'source' => 'admin', 'slot_key' => in_array($status->value, AppointmentStatus::active(), true) ? $at->format('Y-m-d H:i') : null]);
    }
}
