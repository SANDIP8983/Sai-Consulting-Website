<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
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
        $this->assertSame('Chanasma, Chanasma, Patan, Gujarat 384220', $request->address);
        $this->assertNull($request->village);
        $this->assertNull($request->taluka);
        $this->assertNull($request->district);
        $response->assertRedirect(route('request.success'));
        $this->get(route('request.success'))
            ->assertOk()
            ->assertSee($request->reference_no)
            ->assertSee('Approximately 7 day(s)');
        $this->assertNull($request->file_number);
    }

    public function test_submission_validation_rejects_missing_required_input(): void
    {
        $response = $this->from(route('request.create'))->post(route('request.store'), []);

        $response->assertRedirect(route('request.create'));
        $response->assertSessionHasErrors(['service_id', 'name', 'mobile', 'address', 'survey_numbers', 'khata_number', 'details', 'documents', 'declaration']);
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_request_form_preselects_active_service_and_shows_its_documents(): void
    {
        $service = $this->createService();
        $service->requiredDocuments()->create(['name_en' => 'Property Card', 'name_gu' => 'પ્રોપર્ટી કાર્ડ', 'sort_order' => 1]);

        $this->get(route('request.create', ['service' => $service->id]))
            ->assertOk()
            ->assertSee('value="'.$service->id.'" selected', false)
            ->assertSee('Property Card')
            ->assertSee('પ્રોપર્ટી કાર્ડ')
            ->assertSee('Do not upload Aadhaar, PAN, passport, voter ID, bank documents, or other identity proofs.')
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
            ->assertSee('value="'.$service->id.'" selected', false);
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
            ->assertSee('Not Required')
            ->assertSee('Public update.')
            ->assertSee('FILE-EXISTING-1')
            ->assertDontSee('Private internal note.')
            ->assertDontSee('private-document.pdf');
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
            'survey_numbers' => '12/1, Block 15',
            'khata_number' => 'KH-100',
            'details' => 'Please review the land record.',
            'documents' => [UploadedFile::fake()->create('property-card.pdf', 100, 'application/pdf')],
            'declaration' => '1',
        ];
    }

    private function createTrackedRequest(): CustomerRequest
    {
        $request = CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/000001',
            'file_number' => 'FILE-EXISTING-1',
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
}
