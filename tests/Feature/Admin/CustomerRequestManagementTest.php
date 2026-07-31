<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerRequestManagementTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_private_document_download_is_authenticated_scoped_and_hides_path(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $request = $this->customerRequest();
        $other = $this->customerRequest(['reference_no' => 'SC/2026/000002']);
        Storage::disk('local')->put('customer-requests/record.pdf', 'private content');
        $document = $request->documents()->create(['file_name' => 'record.pdf', 'file_path' => 'customer-requests/record.pdf', 'file_type' => 'application/pdf']);

        $this->actingAs($admin)->get(route('admin.requests.documents.download', [$request, $document]))->assertOk()->assertDownload('record.pdf');
        $this->actingAs($admin)->get(route('admin.requests.documents.download', [$other, $document]))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertDontSee('customer-requests/record.pdf');
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

    private function customerRequest(array $attributes = []): CustomerRequest
    {
        $service = Service::query()->firstOrCreate(['slug' => 'sale-deed'], ['name_en' => 'Sale Deed', 'name_gu' => 'વેચાણ દસ્તાવેજ', 'is_active' => true, 'sort_order' => 1]);
        return CustomerRequest::query()->create(['reference_no' => 'SC/2026/000001', 'service_id' => $service->id, 'name' => 'Test Customer', 'mobile' => '9999999999', 'address' => 'Patan, Gujarat', 'survey_numbers' => '12/1', 'khata_number' => 'KH-1', 'details' => 'Draft request', 'status' => 'received', 'payment_status' => 'not_required', 'last_status_changed_at' => now(), ...$attributes]);
    }
}
