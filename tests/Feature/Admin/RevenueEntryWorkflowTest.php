<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RevenueEntryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_requests_default_to_not_required_and_tracking_hides_section(): void
    {
        $request = $this->request();
        $this->assertFalse($request->fresh()->revenue_entry_required);
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertDontSee('Post-document mutation-entry progress');
    }

    public function test_admin_can_enable_requirement_but_staff_cannot(): void
    {
        $request = $this->request();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.requests.revenue-entries.requirement', $request), ['revenue_entry_required' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($request->fresh()->revenue_entry_required);
        $staff = User::factory()->create(['role' => 'staff']);
        $request->update(['assigned_user_id' => $staff->id]);
        $this->actingAs($staff)->patch(route('admin.requests.revenue-entries.requirement', $request), ['revenue_entry_required' => 0])->assertForbidden();
    }

    public function test_request_supports_multiple_entries_and_multiple_survey_numbers_without_adding_fee(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->requiredRequest();
        foreach (["101/1\n101/2, 102", '205'] as $surveys) {
            $this->actingAs($admin)->post(route('admin.requests.revenue-entries.store', $request), $this->entryPayload(['survey_numbers_text' => $surveys]))->assertSessionHasNoErrors();
        }
        $this->assertCount(2, $request->revenueEntries()->get());
        $this->assertSame(['101/1', '101/2', '102'], $request->revenueEntries()->first()->survey_numbers);
        $this->assertNull($request->billing);
        $this->assertSame('not_required', $request->fresh()->payment_status);
    }

    public function test_entry_fields_and_controlled_status_lifecycle_are_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->requiredRequest();
        $this->actingAs($admin)->post(route('admin.requests.revenue-entries.store', $request), $this->entryPayload())->assertSessionHasNoErrors();
        $entry = $request->revenueEntries()->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.requests.revenue-entries.update', [$request, $entry]), $this->entryPayload(['status' => 'application_preparation']))->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.revenue-entries.update', [$request, $entry]), $this->entryPayload(['status' => 'submitted', 'entry_number' => 'RE-1234', 'entry_date' => '2026-08-20', 'application_number' => 'APP-77']))->assertSessionHasNoErrors();
        $entry->refresh();
        $this->assertSame('RE-1234', $entry->entry_number);
        $this->assertSame('2026-08-20', $entry->entry_date->toDateString());
        $this->assertSame('APP-77', $entry->application_number);
        $this->assertNotNull($entry->submitted_at);
        $this->assertDatabaseHas('request_revenue_entry_histories', ['request_id' => $request->id, 'action' => 'status_changed', 'to_status' => 'submitted']);
        $this->actingAs($admin)->patch(route('admin.requests.revenue-entries.update', [$request, $entry]), $this->entryPayload(['status' => 'certified']))->assertSessionHasErrors('status');
    }

    public function test_rejected_can_be_corrected_and_resubmitted_but_is_not_complete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->requiredRequest();
        $entry = $request->revenueEntries()->create([...$this->entryPayload(['status' => 'submitted']), 'created_by' => $admin->id]);
        $this->actingAs($admin)->patch(route('admin.requests.revenue-entries.update', [$request, $entry]), $this->entryPayload(['status' => 'rejected', 'rejection_reason' => 'Correction required']))->assertSessionHasNoErrors();
        $this->assertFalse($entry->fresh()->isCompleted());
        $this->actingAs($admin)->patch(route('admin.requests.revenue-entries.update', [$request, $entry]), $this->entryPayload(['status' => 'application_preparation']))->assertSessionHasNoErrors();
        $this->assertNull($entry->fresh()->rejection_reason);
    }

    public function test_private_documents_accept_safe_types_reject_executables_and_require_auth(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->requiredRequest();
        $entry = $request->revenueEntries()->create($this->entryPayload());
        $this->actingAs($admin)->post(route('admin.requests.revenue-entries.documents.store', [$request, $entry]), ['document_type' => 'application_receipt', 'document' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
        $document = $entry->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->file_path);
        $this->post(route('admin.requests.revenue-entries.documents.store', [$request, $entry]), ['document_type' => 'other', 'document' => UploadedFile::fake()->create('malware.exe', 20)])->assertSessionHasErrors('document');
        auth()->logout();
        $this->get(route('admin.requests.revenue-entries.documents.download', [$request, $entry, $document]))->assertRedirect(route('login'));
    }

    public function test_tracking_shows_only_customer_safe_fields_for_multiple_entries(): void
    {
        $request = $this->requiredRequest();
        $request->revenueEntries()->create($this->entryPayload(['status' => 'submitted', 'survey_numbers' => ['10', '11'], 'admin_notes' => 'SECRET INTERNAL', 'customer_note' => 'Safe customer update']));
        $request->revenueEntries()->create($this->entryPayload(['status' => 'certified', 'entry_number' => 'ME-99', 'certified_at' => now()]));
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertSee('Post-document mutation-entry progress')->assertSee('10, 11')->assertSee('ME-99')->assertSee('Safe customer update')->assertDontSee('SECRET INTERNAL')->assertDontSee('Rejection Reason');
    }

    public function test_pending_and_rejected_entries_block_closure_while_certified_allows_it(): void
    {
        $super = User::factory()->create();
        foreach (['not_started', 'rejected'] as $status) {
            $request = $this->closableRequest();
            $request->update(['revenue_entry_required' => true]);
            $request->revenueEntries()->create($this->entryPayload(['status' => $status, 'rejection_reason' => $status === 'rejected' ? 'Rejected' : null]));
            $this->actingAs($super)->patch(route('admin.requests.closure.close', $request), $this->closure())->assertSessionHasErrors('revenue_entry');
        }
        $request = $this->closableRequest();
        $request->update(['revenue_entry_required' => true]);
        $request->revenueEntries()->create($this->entryPayload(['status' => 'certified', 'certified_at' => now()]));
        $this->actingAs($super)->patch(route('admin.requests.closure.close', $request), $this->closure())->assertSessionHasNoErrors();
        $this->assertSame('closed', $request->fresh()->status);
    }

    public function test_authorized_override_requires_reason_and_audits_state(): void
    {
        $request = $this->closableRequest();
        $request->update(['revenue_entry_required' => true]);
        $entry = $request->revenueEntries()->create($this->entryPayload());
        $super = User::factory()->create();
        $this->actingAs($super)->patch(route('admin.requests.closure.close', $request), [...$this->closure(), 'revenue_entry_override' => 1])->assertSessionHasErrors('revenue_entry_override_reason');
        $this->actingAs($super)->patch(route('admin.requests.closure.close', $request), [...$this->closure(), 'revenue_entry_override' => 1, 'revenue_entry_override_reason' => 'Customer requested closure'])->assertSessionHasNoErrors();
        $audit = $request->caseActionHistory()->where('action', 'revenue_entry_closure_override')->firstOrFail();
        $this->assertSame($super->id, $audit->performed_by);
        $this->assertStringContainsString('not_started', $audit->internal_note);
        $this->assertSame($entry->id, json_decode($audit->internal_note, true)['entries'][0]['id']);
    }

    public function test_ordinary_admin_cannot_override_and_no_requirement_preserves_closure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->closableRequest();
        $this->actingAs($admin)->patch(route('admin.requests.closure.close', $request), $this->closure())->assertSessionHasNoErrors();
        $pending = $this->closableRequest();
        $pending->update(['revenue_entry_required' => true]);
        $this->actingAs($admin)->patch(route('admin.requests.closure.close', $pending), [...$this->closure(), 'revenue_entry_override' => 1, 'revenue_entry_override_reason' => 'Try bypass'])->assertSessionHasErrors('revenue_entry_override');
    }

    private function request(array $attributes = []): CustomerRequest
    {
        $suffix = fake()->unique()->numerify('######');
        $service = Service::query()->create(['name_en' => 'Revenue '.$suffix, 'name_gu' => 'Revenue '.$suffix, 'slug' => 'revenue-'.$suffix, 'is_active' => true]);

        return CustomerRequest::query()->create(['reference_no' => 'SC/2026/'.$suffix, 'request_origin' => 'online', 'service_id' => $service->id, 'name' => 'Revenue Customer', 'mobile' => '9999999999', 'email' => 'customer@example.com', 'status' => 'under_review', 'payment_status' => 'not_required', 'last_status_changed_at' => now(), ...$attributes]);
    }

    private function requiredRequest(): CustomerRequest
    {
        return $this->request(['revenue_entry_required' => true]);
    }

    private function closableRequest(): CustomerRequest
    {
        $request = $this->request(['status' => 'delivered', 'file_number' => 'SC/2026/F'.fake()->unique()->numerify('######'), 'payment_status' => 'not_required', 'completed_at' => now()]);
        $request->processing()->create(['processing_stage' => 'completed', 'requires_dispatch' => true, 'requires_payment_before_processing' => false]);
        $request->dispatches()->create(['dispatch_status' => 'delivered', 'dispatch_method' => 'hand_delivery', 'dispatch_date' => now(), 'delivered_at' => now()]);

        return $request;
    }

    private function entryPayload(array $attributes = []): array
    {
        return ['status' => 'not_started', 'survey_numbers_text' => '101/1', 'survey_numbers' => ['101/1'], 'village' => 'Patan', 'admin_notes' => null, 'customer_note' => null, ...$attributes];
    }

    private function closure(): array
    {
        return ['closure_date' => now()->format('Y-m-d H:i:s'), 'customer_remark' => 'Closed', 'internal_note' => 'Checked', 'confirmed' => '1'];
    }
}
