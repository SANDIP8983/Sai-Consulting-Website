<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicCustomerRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_submit_a_request_and_see_reference_and_estimate(): void
    {
        Storage::fake('local');
        $service = $this->createService();

        $response = $this->post(route('request.store'), $this->validPayload($service));

        $request = CustomerRequest::query()->sole();
        $this->assertSame('online', $request->request_origin);
        $this->assertSame('Chanasma, Chanasma, Patan, Gujarat 384220', $request->address);
        $this->assertNull($request->village);
        $this->assertNull($request->taluka);
        $this->assertNull($request->district);
        $response->assertRedirect(route('request.success'));
        $this->get(route('request.success'))
            ->assertOk()
            ->assertSee($request->reference_no)
            ->assertSee('Approximately 7 working day(s)')
            ->assertSee('દર્શાવેલ સમય જરૂરી માહિતી અને દસ્તાવેજો ઉપલબ્ધ થયા પછીનો અંદાજિત કાર્ય સમય છે.');
        $this->assertNull($request->file_number);
    }

    public function test_submission_validation_rejects_missing_required_input(): void
    {
        $response = $this->from(route('request.create'))->post(route('request.store'), []);

        $response->assertRedirect(route('request.create'));
        $response->assertSessionHasErrors(['service_id', 'name', 'mobile', 'declaration']);
        $response->assertSessionDoesntHaveErrors('documents');
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_request_form_preselects_active_service_and_shows_its_documents(): void
    {
        $service = $this->createService();
        $service->requiredDocuments()->create(['name_en' => 'Property Card', 'name_gu' => 'પ્રોપર્ટી કાર્ડ', 'sort_order' => 1]);

        $this->get(route('request.create', ['service' => $service->id]))
            ->assertOk()
            ->assertSee('value="'.$service->id.'" class="service-choice-input" checked', false)
            ->assertSee('Property Card')
            ->assertSee('તમામ દસ્તાવેજો અપલોડ કરવાનું ફરજિયાત નથી.')
            ->assertSee('Public KYC uploads are prohibited.')
            ->assertDontSee('tel:', false);
    }

    public function test_inactive_or_unknown_service_cannot_be_selected(): void
    {
        $inactive = $this->createService();
        $inactive->update(['is_active' => false]);

        $this->get(route('request.create', ['service' => $inactive->id]))
            ->assertOk()
            ->assertDontSee('<option value="'.$inactive->id.'"', false);

        $payload = $this->validPayload($inactive);
        $this->post(route('request.store'), $payload)->assertSessionHasErrors('service_id');
    }

    public function test_old_input_is_preserved_after_validation_failure(): void
    {
        $service = $this->createService();

        $this->from(route('request.create'))->post(route('request.store'), [
            'service_id' => $service->id,
            'name' => 'Preserved Customer',
            'mobile' => 'invalid',
            'address' => 'Preserved full address',
        ])->assertRedirect(route('request.create'));

        $this->get(route('request.create'))
            ->assertSee('value="Preserved Customer"', false)
            ->assertSee('Preserved full address')
            ->assertSee('value="'.$service->id.'" class="service-choice-input" checked', false);
    }

    public function test_submission_rejects_an_invalid_file_type(): void
    {
        Storage::fake('local');
        $payload = $this->validPayload($this->createService());
        $payload['documents'] = [UploadedFile::fake()->create('identity.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')];

        $this->post(route('request.store'), $payload)
            ->assertSessionHasErrors('documents.0');
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_customer_can_submit_without_uploading_documents(): void
    {
        Storage::fake('local');
        $payload = $this->validPayload($this->createService());
        unset($payload['documents']);

        $this->post(route('request.store'), $payload)->assertRedirect(route('request.success'));
        $this->assertDatabaseCount('requests', 1);
        $this->assertDatabaseCount('request_documents', 0);
    }

    public function test_public_submission_rejects_personal_kyc_documents(): void
    {
        Storage::fake('local');
        $payload = $this->validPayload($this->createService());
        $payload['documents'] = [UploadedFile::fake()->create('aadhaar-card.pdf', 20, 'application/pdf')];

        $this->post(route('request.store'), $payload)->assertSessionHasErrors('documents.0');
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_submission_rejects_more_than_ten_files(): void
    {
        Storage::fake('local');
        $payload = $this->validPayload($this->createService());
        $payload['documents'] = collect(range(1, 11))
            ->map(fn (int $number) => UploadedFile::fake()->create("record-{$number}.pdf", 20, 'application/pdf'))
            ->all();

        $this->post(route('request.store'), $payload)
            ->assertSessionHasErrors('documents');
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_uploaded_documents_are_stored_on_the_private_disk_with_metadata(): void
    {
        Storage::fake('local');
        $this->post(route('request.store'), $this->validPayload($this->createService()));

        $document = CustomerRequest::query()->sole()->documents()->sole();
        Storage::disk('local')->assertExists($document->file_path);
        Storage::disk('public')->assertMissing($document->file_path);
        $this->assertSame('property-card.pdf', $document->file_name);
        $this->assertSame('application/pdf', $document->file_type);
        $this->assertGreaterThan(0, $document->file_size);
        $this->assertStringStartsWith('customer-requests/', $document->file_path);
    }

    public function test_submission_generates_the_existing_unique_reference_number_format(): void
    {
        Storage::fake('local');
        $service = $this->createService();
        $this->post(route('request.store'), $this->validPayload($service));
        $secondPayload = $this->validPayload($service);
        $secondPayload['mobile'] = '9888888888';
        $this->post(route('request.store'), $secondPayload);

        $references = CustomerRequest::query()->orderBy('id')->pluck('reference_no');
        $this->assertCount(2, $references);
        $this->assertMatchesRegularExpression('/^SC\/\d{4}\/000001$/', $references[0]);
        $this->assertMatchesRegularExpression('/^SC\/\d{4}\/000002$/', $references[1]);
        $this->assertNotSame($references[0], $references[1]);
    }

    public function test_submission_creates_initial_status_history(): void
    {
        Storage::fake('local');
        $this->post(route('request.store'), $this->validPayload($this->createService()));

        $request = CustomerRequest::query()->sole();
        $this->assertDatabaseHas('request_status_histories', [
            'request_id' => $request->id,
            'from_status' => null,
            'to_status' => 'received',
            'is_visible_to_customer' => true,
        ]);
    }

    public function test_customer_can_track_request_and_only_visible_remarks_are_shown(): void
    {
        $request = $this->createTrackedRequest();
        $request->statusHistory()->create(['to_status' => 'received', 'remarks' => 'Public update.', 'is_visible_to_customer' => true]);
        $request->statusHistory()->create(['from_status' => 'received', 'to_status' => 'under_review', 'remarks' => 'Private internal note.', 'is_visible_to_customer' => false]);

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()
            ->assertSee($request->reference_no)
            ->assertSee('Title Search')
            ->assertSee('Under Review')
            ->assertSee('Billing Pending Approval')
            ->assertSee('Public update.')
            ->assertSee('SC/2026/F000001')
            ->assertDontSee('Private internal note.')
            ->assertDontSee('private-document.pdf');
    }

    public function test_customer_can_track_by_file_number_with_public_details_only(): void
    {
        $request = $this->createTrackedRequest();
        $request->update(['status' => 'dispatched', 'last_status_changed_at' => now()]);
        $request->service->requiredDocuments()->create(['name_en' => 'Property Card', 'name_gu' => 'પ્રોપર્ટી કાર્ડ', 'sort_order' => 1]);
        $request->statusHistory()->create(['from_status' => 'ready_for_registration', 'to_status' => 'dispatched', 'remarks' => 'Sent through registered post.', 'is_visible_to_customer' => true]);
        $request->statusHistory()->create(['from_status' => 'dispatched', 'to_status' => 'dispatched', 'remarks' => 'Private courier tracking detail.', 'is_visible_to_customer' => false]);
        Setting::query()->create(['setting_key' => 'contact.whatsapp_number', 'setting_value' => '919687621876', 'value_type' => 'string', 'setting_group' => 'contact', 'is_public' => true]);

        $this->post(route('request.track.lookup'), ['reference_no' => $request->file_number, 'mobile' => $request->mobile])
            ->assertOk()
            ->assertSee('Private Customer Name')
            ->assertSee($request->reference_no)
            ->assertSee($request->file_number)
            ->assertSee('Property Card')
            ->assertSee('Sent through registered post.')
            ->assertSee('Dispatch Information')
            ->assertSee('https://wa.me/919687621876', false)
            ->assertDontSee('Private courier tracking detail.')
            ->assertDontSee('private/path.pdf');
    }

    public function test_customer_can_track_an_existing_legacy_file_number(): void
    {
        $request = $this->createTrackedRequest();
        $request->update(['file_number' => 'LEGACY-FILE-42']);

        $this->post(route('request.track.lookup'), ['reference_no' => 'legacy-file-42', 'mobile' => $request->mobile])
            ->assertOk()
            ->assertSee('LEGACY-FILE-42')
            ->assertSee($request->reference_no);
    }

    public function test_tracking_fails_for_an_incorrect_mobile_number_without_exposing_request_data(): void
    {
        $request = $this->createTrackedRequest();

        $this->from(route('request.track'))->post(route('request.track.lookup'), [
            'reference_no' => $request->reference_no,
            'mobile' => '9000000000',
        ])->assertRedirect(route('request.track'))
            ->assertSessionHasErrors('reference_no');
    }

    public function test_tracking_shows_progress_without_exposing_internal_checklist_items(): void
    {
        $request = $this->createTrackedRequest();
        $selected = $request->requestServices()->create(['service_id' => $request->service_id, 'service_name_en_snapshot' => 'Title Search', 'professional_fee' => 1000, 'status' => 'approved']);
        $selected->workScopes()->create(['name_en_snapshot' => 'PRIVATE INTERNAL CHECKLIST ITEM', 'status' => 'completed']);

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()->assertSee('100%')->assertDontSee('PRIVATE INTERNAL CHECKLIST ITEM');
    }

    public function test_only_missing_required_documents_are_shown_as_pending(): void
    {
        $request = $this->createTrackedRequest();
        $service = $request->service;
        $uploaded = $service->requiredDocuments()->create(['name_en' => 'Uploaded Record', 'name_gu' => 'Uploaded Record', 'is_mandatory' => true, 'sort_order' => 1]);
        $service->requiredDocuments()->create(['name_en' => 'Still Required', 'name_gu' => 'Still Required', 'is_mandatory' => true, 'sort_order' => 2]);
        $request->documents()->create(['service_required_document_id' => $uploaded->id, 'file_name' => 'record.pdf', 'file_path' => 'requests/record.pdf']);

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()->assertSee('Still Required')->assertDontSee('Uploaded Record');
    }

    public function test_customer_safe_pdf_download_requires_a_verified_tracking_session(): void
    {
        $request = $this->createTrackedRequest();
        $url = route('request.track.pdf', [$request, 'request-acknowledgement']);

        $this->get($url)->assertNotFound();
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk();
        $response = $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $other = CustomerRequest::query()->create(['reference_no' => 'SC/2026/000002', 'service_id' => $request->service_id, 'name' => 'Other Customer', 'mobile' => '9888888888', 'status' => 'received', 'payment_status' => 'not_required']);
        $this->get(route('request.track.pdf', [$other, 'request-acknowledgement']))->assertNotFound();
    }

    public function test_rejected_and_archived_requests_show_customer_appropriate_messages(): void
    {
        $request = $this->createTrackedRequest();
        $request->update(['status' => 'rejected']);
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()
            ->assertSee('રદ કરેલ')
            ->assertSee('Rejected')
            ->assertSee('This request was not approved.')
            ->assertDontSee('Processing Progress')
            ->assertDontSee('Payment Pending');

        $request->update(['status' => 'archived']);
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])->assertOk()->assertSee('This request has been archived.');
    }

    public function test_initial_and_approved_customer_statuses_remain_unchanged(): void
    {
        $request = $this->createTrackedRequest();
        $request->update(['status' => 'received']);

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()
            ->assertSee('Received')
            ->assertDontSee('This request was not approved.');

        $request->update(['status' => 'approved']);
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()
            ->assertSee('Approved')
            ->assertSee('Processing Progress')
            ->assertDontSee('This request was not approved.');
    }

    public function test_all_rejected_service_decisions_override_stale_active_tracking_state(): void
    {
        $request = $this->createTrackedRequest();
        $request->requestServices()->create([
            'service_id' => $request->service_id,
            'service_name_en_snapshot' => 'Title Search',
            'professional_fee' => 1000,
            'status' => 'rejected',
            'decision_notes' => 'INTERNAL REJECTION NOTE',
            'internal_note' => 'PRIVATE ADMIN NOTE',
            'customer_decision_message' => 'Please contact us about another suitable service.',
        ]);
        $request->processing()->create(['processing_stage' => 'ready_for_registration']);
        $request->processingHistory()->create([
            'to_stage' => 'ready_for_registration',
            'remarks' => 'STALE PUBLIC PROCESSING UPDATE',
            'is_visible_to_customer' => true,
        ]);
        $request->statusHistory()->create([
            'from_status' => 'received',
            'to_status' => 'under_review',
            'remarks' => 'STALE ACTIVE STATUS UPDATE',
            'is_visible_to_customer' => true,
        ]);

        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()
            ->assertSee('રદ કરેલ')
            ->assertSee('Rejected')
            ->assertSee('Please contact us about another suitable service.')
            ->assertDontSee('Processing Progress')
            ->assertDontSee('Payment Pending')
            ->assertDontSee('Under Review')
            ->assertDontSee('STALE PUBLIC PROCESSING UPDATE')
            ->assertDontSee('STALE ACTIVE STATUS UPDATE')
            ->assertDontSee('INTERNAL REJECTION NOTE')
            ->assertDontSee('PRIVATE ADMIN NOTE');
    }

    public function test_completed_and_dispatched_customer_statuses_remain_authoritative(): void
    {
        $service = $this->createService();

        foreach (['completed' => 'Completed', 'dispatched' => 'Dispatched'] as $status => $label) {
            $request = CustomerRequest::query()->create([
                'reference_no' => 'SC/2026/'.fake()->unique()->numerify('######'),
                'service_id' => $service->id,
                'name' => 'Lifecycle Customer',
                'mobile' => '9999999999',
                'status' => $status,
            ]);
            $request->requestServices()->create([
                'service_id' => $request->service_id,
                'professional_fee' => 1000,
                'status' => 'approved',
            ]);

            $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
                ->assertOk()
                ->assertSee($label)
                ->assertDontSee('This request was not approved.');
        }
    }

    private function createService(): Service
    {
        return Service::query()->create([
            'name_en' => 'Title Search',
            'name_gu' => 'ટાઇટલ સર્ચ',
            'slug' => 'title-search-'.fake()->unique()->numberBetween(1, 999999),
            'estimated_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function validPayload(Service $service): array
    {
        return [
            'service_id' => $service->id,
            'name' => 'Test Customer',
            'mobile' => '9999999999',
            'email' => 'customer@example.com',
            'address' => 'Chanasma, Chanasma, Patan, Gujarat 384220',
            'property_village' => 'Chanasma',
            'property_taluka' => 'Chanasma',
            'property_district' => 'Patan',
            'survey_numbers' => '12/1, Block 15',
            'khata_number' => 'KH-100',
            'details' => 'Please review the land record.',
            'documents' => [UploadedFile::fake()->createWithContent('property-card.pdf', $this->pdfContent())],
            'declaration' => '1',
        ];
    }

    private function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }

    private function createTrackedRequest(): CustomerRequest
    {
        $request = CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/000001',
            'file_number' => 'SC/2026/F000001',
            'service_id' => $this->createService()->id,
            'name' => 'Private Customer Name',
            'mobile' => '9999999999',
            'status' => 'under_review',
            'payment_status' => 'not_required',
            'estimated_completion_date' => now()->addDays(7),
            'last_status_changed_at' => now(),
        ]);
        $request->documents()->create(['file_name' => 'private-document.pdf', 'file_path' => 'private/path.pdf']);

        return $request;
    }

    public function test_online_disabled_service_is_hidden_and_rejected(): void
    {
        $service = $this->createService();
        $service->update(['available_online' => false]);
        $this->get(route('request.create'))->assertOk()->assertDontSee('<option value="'.$service->id.'"', false);
        $this->post(route('request.store'), $this->validPayload($service))->assertSessionHasErrors('service_id');
    }

    public function test_service_without_property_documents_accepts_request_without_uploads(): void
    {
        Storage::fake('local');
        $service = $this->createService();
        $service->update(['requires_property_documents' => false]);
        $payload = $this->validPayload($service);
        unset($payload['documents']);
        $this->post(route('request.store'), $payload)->assertRedirect(route('request.success'));
        $this->assertDatabaseCount('requests', 1);
        $this->assertDatabaseCount('request_documents', 0);
    }
}
