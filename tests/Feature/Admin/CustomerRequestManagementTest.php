<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerRequestManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_entry_redirects_guests_to_login_and_users_to_dashboard(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin')->assertRedirect(route('admin.dashboard'));
    }

    public function test_guest_cannot_access_admin_requests_or_private_documents(): void
    {
        $request = $this->customerRequest();
        $document = $request->documents()->create(['file_name' => 'record.pdf', 'file_path' => 'private/record.pdf']);
        $this->get(route('admin.requests.index'))->assertRedirect(route('login'));
        $this->get(route('admin.requests.show', $request))->assertRedirect(route('login'));
        $this->get(route('admin.requests.documents.download', [$request, $document]))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_search_and_filter_request_list_and_details(): void
    {
        $admin = User::factory()->create();
        $match = $this->customerRequest(['reference_no' => 'SC/2026/000111', 'name' => 'Matching Customer', 'status' => 'under_review']);
        $hidden = $this->customerRequest(['reference_no' => 'SC/2026/000222', 'name' => 'Other Customer']);

        $this->actingAs($admin)->get(route('admin.requests.index', ['q' => 'Matching', 'status' => 'under_review']))
            ->assertOk()->assertSee($match->reference_no)->assertDontSee($hidden->reference_no);
        $this->actingAs($admin)->get(route('admin.requests.show', $match))
            ->assertOk()->assertSee('Matching Customer')->assertSee($match->address)->assertSee($match->service->name_en);
    }

    public function test_payment_service_and_date_filters_are_applied_together(): void
    {
        $admin = User::factory()->create();
        $match = $this->customerRequest(['reference_no' => 'SC/2026/000311', 'payment_status' => 'pending']);
        $hidden = $this->customerRequest(['reference_no' => 'SC/2026/000322', 'payment_status' => 'received']);
        $match->forceFill(['created_at' => '2026-08-01 10:00:00'])->save();
        $hidden->forceFill(['created_at' => '2026-07-01 10:00:00'])->save();

        $this->actingAs($admin)->get(route('admin.requests.index', [
            'payment_status' => 'pending',
            'service_id' => $match->service_id,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-01',
        ]))->assertOk()->assertSee($match->reference_no)->assertDontSee($hidden->reference_no);
    }

    public function test_private_document_download_is_authenticated_scoped_and_hides_path(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $request = $this->customerRequest();
        $other = $this->customerRequest(['reference_no' => 'SC/2026/000002']);
        $content = "%PDF-1.4\nprivate content\n%%EOF";
        Storage::disk('local')->put('customer-requests/record.pdf', $content);
        $document = $request->documents()->create(['file_name' => 'record.pdf', 'file_path' => 'customer-requests/record.pdf', 'file_type' => 'application/pdf']);

        $response = $this->actingAs($admin)->get(route('admin.requests.documents.download', [$request, $document]));
        $response->assertOk()->assertDownload('record.pdf')->assertHeader('content-type', 'application/pdf')->assertHeader('x-content-type-options', 'nosniff');
        $this->assertSame($content, $response->baseResponse->getFile()->getContent());
        $this->actingAs($admin)->get(route('admin.requests.documents.download', [$other, $document]))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertDontSee('customer-requests/record.pdf');
    }

    public function test_private_image_documents_download_with_detected_safe_content_types(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $request = $this->customerRequest();

        foreach (['jpg' => 'image/jpeg', 'png' => 'image/png'] as $extension => $mimeType) {
            $file = UploadedFile::fake()->image("property.{$extension}", 20, 20);
            $path = "customer-requests/{$request->id}/property.{$extension}";
            Storage::disk('local')->put($path, $file->getContent());
            $document = $request->documents()->create(['file_name' => "../property.{$extension}", 'file_path' => $path, 'file_type' => 'application/octet-stream']);

            $this->actingAs($admin)->get(route('admin.requests.documents.download', [$request, $document]))
                ->assertOk()->assertDownload("property.{$extension}")->assertHeader('content-type', $mimeType);
        }
    }

    public function test_private_document_download_returns_not_found_for_missing_or_unsafe_paths(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $request = $this->customerRequest();
        $missing = $request->documents()->create(['file_name' => 'missing.pdf', 'file_path' => 'customer-requests/missing.pdf']);
        $unsafe = $request->documents()->create(['file_name' => 'unsafe.pdf', 'file_path' => '../unsafe.pdf']);

        $this->actingAs($admin)->get(route('admin.requests.documents.download', [$request, $missing]))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.requests.documents.download', [$request, $unsafe]))->assertNotFound();
    }

    public function test_approval_generates_one_unique_file_number_and_history(): void
    {
        $admin = User::factory()->create();
        $first = $this->customerRequest(['status' => 'under_review']);
        $second = $this->customerRequest(['reference_no' => 'SC/2026/000002', 'status' => 'under_review']);

        $this->actingAs($admin)->patch(route('admin.requests.transition', $first), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.transition', $second), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertMatchesRegularExpression('/^SC\/\d{4}\/F000001$/', $first->fresh()->file_number);
        $this->assertMatchesRegularExpression('/^SC\/\d{4}\/F000002$/', $second->fresh()->file_number);
        $original = $first->fresh()->file_number;
        $this->actingAs($admin)->patch(route('admin.requests.transition', $first), ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->assertSame($original, $first->fresh()->file_number);
        $this->assertDatabaseHas('request_status_histories', ['request_id' => $first->id, 'from_status' => 'under_review', 'to_status' => 'approved', 'changed_by' => $admin->id]);
    }

    public function test_forbidden_transition_and_rejection_without_reason_are_rejected(): void
    {
        $admin = User::factory()->create();
        $received = $this->customerRequest();
        $review = $this->customerRequest(['reference_no' => 'SC/2026/000002', 'status' => 'under_review']);
        $this->actingAs($admin)->patch(route('admin.requests.transition', $received), ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->actingAs($admin)->patch(route('admin.requests.transition', $review), ['status' => 'rejected'])->assertSessionHasErrors('remarks');
    }

    public function test_need_documents_and_standalone_notes_respect_public_visibility(): void
    {
        $admin = User::factory()->create();
        $request = $this->customerRequest(['status' => 'under_review']);
        $this->actingAs($admin)->patch(route('admin.requests.transition', $request), ['status' => 'need_documents', 'remarks' => 'Upload a clearer Property Card.']);
        $this->actingAs($admin)->post(route('admin.requests.remarks.store', $request), ['remarks' => 'Internal review note.', 'is_visible_to_customer' => 0]);

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()->assertSee('Upload a clearer Property Card.')->assertDontSee('Internal review note.');
    }

    public function test_admin_can_update_estimate_and_record_manual_payment_safely(): void
    {
        $admin = User::factory()->create();
        $request = $this->customerRequest(['status' => 'payment_pending', 'payment_status' => 'pending', 'amount_due' => 500, 'file_number' => 'SC/2026/F000001']);
        $request->billing()->create(['total_original_professional_fee' => 500, 'discount_type' => 'none', 'discount_value' => 0, 'discount_amount' => 0, 'net_professional_fee' => 500, 'gst_rate' => 0, 'gst_amount' => 0, 'government_charges_total' => 0, 'grand_total' => 500, 'pricing_locked_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.requests.estimate', $request), ['estimated_completion_date' => '2026-08-15'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.requests.payments.store', $request), ['amount' => 500, 'payment_status' => 'received', 'payment_method' => 'upi', 'received_at' => '2026-08-01 11:00:00'])->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('2026-08-15', $request->estimated_completion_date->toDateString());
        $this->assertSame('awaiting_staff_assignment', $request->status);
        $this->assertSame('received', $request->payment_status);
        $this->assertDatabaseHas('request_payments', ['request_id' => $request->id, 'amount' => 500]);
        $this->assertDatabaseHas('request_status_histories', ['request_id' => $request->id, 'to_status' => 'payment_received']);
    }

    public function test_dashboard_displays_request_summary_cards(): void
    {
        $admin = User::factory()->create();
        $this->customerRequest(['status' => 'received']);
        $this->customerRequest(['reference_no' => 'SC/2026/000002', 'status' => 'completed']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('New / Received')->assertSee('Under Review')->assertSee('Need Documents')
            ->assertSee('Payment Pending')->assertSee('In Progress')->assertSee('Completed');
    }

    private function customerRequest(array $attributes = []): CustomerRequest
    {
        $service = Service::query()->firstOrCreate(['slug' => 'sale-deed'], ['name_en' => 'Sale Deed', 'name_gu' => 'વેચાણ દસ્તાવેજ', 'is_active' => true, 'sort_order' => 1]);

        return CustomerRequest::query()->create(['reference_no' => 'SC/2026/000001', 'service_id' => $service->id, 'name' => 'Test Customer', 'mobile' => '9999999999', 'address' => 'Patan, Gujarat', 'survey_numbers' => '12/1', 'khata_number' => 'KH-1', 'details' => 'Draft request', 'status' => 'received', 'payment_status' => 'not_required', 'last_status_changed_at' => now(), ...$attributes]);
    }
}
