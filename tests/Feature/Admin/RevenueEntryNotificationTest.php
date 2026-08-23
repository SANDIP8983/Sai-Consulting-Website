<?php

namespace Tests\Feature\Admin;

use App\Enums\NotificationMilestone;
use App\Jobs\SendCustomerNotificationJob;
use App\Mail\CustomerMilestoneMail;
use App\Models\CustomerNotificationEvent;
use App\Models\CustomerRequest;
use App\Models\RequestRevenueEntry;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\Notifications\CustomerMessageFactory;
use App\Services\Notifications\CustomerNotificationService;
use App\Services\Notifications\DisabledWhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RevenueEntryNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_transition_queues_and_sends_one_customer_email_without_duplicates(): void
    {
        Queue::fake();
        Mail::fake();
        $this->enable(NotificationMilestone::RevenueEntrySubmitted);
        [$request, $entry, $admin] = $this->fixture('application_preparation');

        $this->transition($admin, $request, $entry, 'submitted')->assertSessionHasNoErrors();
        $this->transition($admin, $request, $entry->fresh(), 'submitted', ['customer_note' => 'Unrelated safe note edit'])->assertSessionHasNoErrors();

        $this->assertSame('submitted', $entry->fresh()->status);
        $this->assertSame(1, $this->events($entry, NotificationMilestone::RevenueEntrySubmitted)->count());
        Queue::assertPushed(SendCustomerNotificationJob::class, 1);
        $this->deliver($entry, NotificationMilestone::RevenueEntrySubmitted);
        Mail::assertSent(CustomerMilestoneMail::class, 1);
    }

    public function test_entry_number_received_email_contains_entry_number_date_survey_service_and_tracking_link(): void
    {
        Queue::fake();
        Mail::fake();
        URL::forceRootUrl('https://saiconsultingchanasma.in');
        URL::forceScheme('https');
        $this->enable(NotificationMilestone::RevenueEntryNumberReceived);
        [$request, $entry, $admin] = $this->fixture('submitted');

        $this->transition($admin, $request, $entry, 'entry_number_received', ['entry_number' => 'ME-1234', 'entry_date' => '2026-08-22'])->assertSessionHasNoErrors();
        $this->deliver($entry, NotificationMilestone::RevenueEntryNumberReceived);

        Mail::assertSent(CustomerMilestoneMail::class, function (CustomerMilestoneMail $mail) use ($request): bool {
            $body = $mail->messageData['body'];

            return str_contains($mail->messageData['subject'], 'ફેરફાર નોંધ નંબર મળ્યો')
                && str_contains($body, 'ME-1234') && str_contains($body, '22 Aug 2026')
                && str_contains($body, '101/1') && str_contains($body, 'વેચાણ દસ્તાવેજ')
                && str_contains($body, $request->reference_no)
                && str_contains($body, 'https://saiconsultingchanasma.in/request/track')
                && ! str_contains($body, '?');
        });
    }

    public function test_certified_email_contains_entry_and_certified_date_once(): void
    {
        Queue::fake();
        Mail::fake();
        $this->enable(NotificationMilestone::RevenueEntryCertified);
        [$request, $entry, $admin] = $this->fixture('certification_pending', ['entry_number' => 'ME-88']);
        $this->transition($admin, $request, $entry, 'certified', ['certified_at' => '2026-08-23 10:00:00'])->assertSessionHasNoErrors();
        $this->deliver($entry, NotificationMilestone::RevenueEntryCertified);
        Mail::assertSent(CustomerMilestoneMail::class, fn (CustomerMilestoneMail $mail): bool => str_contains($mail->messageData['body'], 'ME-88') && str_contains($mail->messageData['body'], '23 Aug 2026'));
        $this->assertSame(1, $this->events($entry, NotificationMilestone::RevenueEntryCertified)->count());
    }

    public function test_rejected_email_includes_customer_note_but_excludes_internal_information(): void
    {
        Queue::fake();
        Mail::fake();
        $this->enable(NotificationMilestone::RevenueEntryRejected);
        [$request, $entry, $admin] = $this->fixture('submitted', ['admin_notes' => 'PRIVATE STAFF DISCUSSION']);
        $this->transition($admin, $request, $entry, 'rejected', ['rejection_reason' => 'INTERNAL REJECTION REASON', 'admin_notes' => 'PRIVATE STAFF DISCUSSION', 'customer_note' => 'Please contact our office with the corrected village form.'])->assertSessionHasNoErrors();
        $this->deliver($entry, NotificationMilestone::RevenueEntryRejected);
        Mail::assertSent(CustomerMilestoneMail::class, function (CustomerMilestoneMail $mail): bool {
            $body = $mail->messageData['body'];

            return str_contains($mail->messageData['subject'], 'કાર્યવાહી જરૂરી')
                && str_contains($body, 'Please contact our office with the corrected village form.')
                && ! str_contains($body, 'PRIVATE STAFF DISCUSSION')
                && ! str_contains($body, 'INTERNAL REJECTION REASON');
        });
    }

    public function test_multiple_entries_use_specific_entry_context_and_independent_idempotency(): void
    {
        Queue::fake();
        Mail::fake();
        $this->enable(NotificationMilestone::RevenueEntrySubmitted);
        [$request, $first, $admin] = $this->fixture('submitted', ['survey_numbers' => ['OLD-10']]);
        $second = $request->revenueEntries()->create($this->entry(['status' => 'application_preparation', 'survey_numbers' => ['NEW-20', 'NEW-21']]));
        $this->transition($admin, $request, $second, 'submitted')->assertSessionHasNoErrors();
        $event = $this->events($second, NotificationMilestone::RevenueEntrySubmitted)->sole();
        $message = app(CustomerMessageFactory::class)->make($request, $event->milestone, $event->safe_context);
        $this->assertStringContainsString('NEW-20, NEW-21', $message['body']);
        $this->assertStringNotContainsString('OLD-10', $message['body']);
        $this->assertSame($second->id, $event->source_id);
        $this->assertDatabaseMissing('customer_notification_events', ['source_id' => $first->id, 'milestone' => NotificationMilestone::RevenueEntrySubmitted->value]);
    }

    public function test_missing_customer_email_skips_safely_without_blocking_transition(): void
    {
        Queue::fake();
        $this->enable(NotificationMilestone::RevenueEntrySubmitted);
        [$request, $entry, $admin] = $this->fixture('application_preparation');
        $request->update(['email' => null]);
        $this->transition($admin, $request, $entry, 'submitted')->assertSessionHasNoErrors();
        $event = $this->events($entry, NotificationMilestone::RevenueEntrySubmitted)->sole();
        $this->assertDatabaseHas('customer_notification_deliveries', ['notification_event_id' => $event->id, 'channel' => 'email', 'status' => 'skipped', 'failure_category' => 'missing_or_invalid_recipient']);
        $this->assertSame('submitted', $entry->fresh()->status);
    }

    public function test_non_milestone_transitions_never_create_notification_events(): void
    {
        Queue::fake();
        [$request, $entry, $admin] = $this->fixture('not_started');
        $this->transition($admin, $request, $entry, 'application_preparation')->assertSessionHasNoErrors();
        $entry->update(['status' => 'submitted']);
        $this->transition($admin, $request, $entry->fresh(), 'certification_pending')->assertSessionHasNoErrors();
        $entry->update(['status' => 'not_started']);
        $this->transition($admin, $request, $entry->fresh(), 'cancelled')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_notification_events', 0);
        Queue::assertNothingPushed();
    }

    public function test_email_setting_off_suppresses_queue_and_on_allows_it_while_whatsapp_stays_off(): void
    {
        Queue::fake();
        [$request, $entry, $admin] = $this->fixture('application_preparation');
        $this->transition($admin, $request, $entry, 'submitted')->assertSessionHasNoErrors();
        $event = $this->events($entry, NotificationMilestone::RevenueEntrySubmitted)->sole();
        $this->assertDatabaseHas('customer_notification_deliveries', ['notification_event_id' => $event->id, 'channel' => 'email', 'status' => 'skipped', 'failure_category' => 'channel_disabled']);
        Queue::assertNothingPushed();

        $this->enable(NotificationMilestone::RevenueEntrySubmitted);
        [$secondRequest, $secondEntry] = $this->fixture('application_preparation');
        $this->transition($admin, $secondRequest, $secondEntry, 'submitted')->assertSessionHasNoErrors();
        Queue::assertPushed(SendCustomerNotificationJob::class, 1);
        $secondEvent = $this->events($secondEntry, NotificationMilestone::RevenueEntrySubmitted)->sole();
        $this->assertDatabaseHas('customer_notification_deliveries', ['notification_event_id' => $secondEvent->id, 'channel' => 'whatsapp', 'status' => 'skipped', 'failure_category' => 'channel_disabled']);
    }

    public function test_historical_records_are_not_replayed_and_settings_page_exposes_four_email_toggles(): void
    {
        Queue::fake();
        [$request] = $this->fixture('certified');
        $this->assertDatabaseCount('customer_notification_events', 0);
        Queue::assertNothingPushed();
        $response = $this->actingAs(User::factory()->create())->get(route('admin.settings.customer-notifications'))->assertOk();
        foreach ([NotificationMilestone::RevenueEntrySubmitted, NotificationMilestone::RevenueEntryNumberReceived, NotificationMilestone::RevenueEntryCertified, NotificationMilestone::RevenueEntryRejected] as $milestone) {
            $response->assertSee($milestone->label())->assertSee('milestones['.$milestone->value.'][email]', false);
            $this->assertFalse($milestone->defaults()['email']);
            $this->assertFalse(app(CustomerNotificationService::class)->enabled($milestone, 'whatsapp'));
        }
    }

    public function test_queue_transport_failure_does_not_roll_back_revenue_entry_status(): void
    {
        config(['queue.default' => 'sync']);
        $this->enable(NotificationMilestone::RevenueEntrySubmitted);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Temporary SMTP failure'));
        [$request, $entry, $admin] = $this->fixture('application_preparation');
        $this->transition($admin, $request, $entry, 'submitted')->assertSessionHasNoErrors();
        $this->assertSame('submitted', $entry->fresh()->status);
        $event = $this->events($entry, NotificationMilestone::RevenueEntrySubmitted)->sole();
        $this->assertDatabaseHas('customer_notification_deliveries', ['notification_event_id' => $event->id, 'channel' => 'email', 'status' => 'failed', 'failure_category' => 'transport_failure']);
    }

    private function enable(NotificationMilestone $milestone): void
    {
        Setting::query()->updateOrCreate(['setting_key' => "notifications.{$milestone->value}.email"], ['setting_value' => '1', 'value_type' => 'boolean', 'setting_group' => 'customer_notifications', 'is_public' => false]);
    }

    private function fixture(string $status, array $entryAttributes = []): array
    {
        $suffix = fake()->unique()->numerify('######');
        $service = Service::query()->create(['name_en' => 'Sale Deed '.$suffix, 'name_gu' => 'વેચાણ દસ્તાવેજ '.$suffix, 'slug' => 'sale-deed-'.$suffix, 'is_active' => true]);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/'.$suffix, 'request_origin' => 'online', 'service_id' => $service->id, 'name' => 'Revenue Customer', 'mobile' => '9999999999', 'email' => 'customer@example.com', 'status' => 'under_review', 'payment_status' => 'not_required', 'revenue_entry_required' => true]);
        $request->requestServices()->create(['service_id' => $service->id, 'service_name_en_snapshot' => 'Sale Deed', 'service_name_gu_snapshot' => 'વેચાણ દસ્તાવેજ', 'status' => 'approved']);
        $entry = $request->revenueEntries()->create($this->entry(['status' => $status, ...$entryAttributes]));

        return [$request, $entry, User::factory()->create(['role' => 'admin'])];
    }

    private function entry(array $attributes = []): array
    {
        return ['status' => 'not_started', 'survey_numbers' => ['101/1'], 'village' => 'Patan', 'admin_notes' => null, 'customer_note' => null, ...$attributes];
    }

    private function transition(User $admin, CustomerRequest $request, RequestRevenueEntry $entry, string $status, array $attributes = [])
    {
        $payload = ['status' => $status, 'survey_numbers_text' => implode("\n", $entry->survey_numbers ?? []), 'village' => $entry->village, 'entry_number' => $entry->entry_number, 'entry_date' => $entry->entry_date?->toDateString(), 'customer_note' => $entry->customer_note, 'admin_notes' => $entry->admin_notes, ...$attributes];

        return $this->actingAs($admin)->patch(route('admin.requests.revenue-entries.update', [$request, $entry]), $payload);
    }

    private function events(RequestRevenueEntry $entry, NotificationMilestone $milestone)
    {
        return CustomerNotificationEvent::query()->where('source_type', 'revenue_entry')->where('source_id', $entry->id)->where('milestone', $milestone->value)->get();
    }

    private function deliver(RequestRevenueEntry $entry, NotificationMilestone $milestone): void
    {
        $event = $this->events($entry, $milestone)->sole();
        $delivery = $event->deliveries()->where('channel', 'email')->sole();
        (new SendCustomerNotificationJob($delivery->id))->handle(app(CustomerNotificationService::class), app(CustomerMessageFactory::class), app(DisabledWhatsAppChannel::class));
    }
}
