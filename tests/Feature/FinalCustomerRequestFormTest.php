<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalCustomerRequestFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_service_submission_preserves_fee_and_government_charge_snapshots(): void
    {
        $first = $this->service('sale-deed', 1000, 18, 5);
        $second = $this->service('mutation', 500, 5, 9);
        $first->governmentChargeItems()->create(['name' => 'Stamp Duty', 'amount' => 250, 'description' => 'As applicable', 'is_active' => true]);
        $second->governmentChargeItems()->create(['name' => 'Mutation Fee', 'amount' => 100, 'is_active' => true]);
        $propertyCard = $first->requiredDocuments()->create(['name_en' => 'Property Card', 'name_gu' => 'પ્રોપર્ટી કાર્ડ', 'is_mandatory' => true]);

        Storage::fake('local');
        $payload = $this->payload([$first->id, $second->id]);
        $payload['document_uploads'] = [$propertyCard->id => UploadedFile::fake()->createWithContent('property-card.pdf', $this->pdfContent())];
        $response = $this->post(route('request.store'), $payload);

        $request = CustomerRequest::query()->with('requestServices')->sole();
        $response->assertRedirect(route('request.success'));
        $this->assertCount(2, $request->requestServices);
        $gstTotal = $request->requestServices->sum(fn ($item) => (float) $item->professional_fee * (float) $item->gst_rate / 100);
        $this->assertSame(205.0, $gstTotal);
        $this->assertSame(0.0, (float) $request->requestServices->sum('government_charges'));
        $this->assertSame(2055.0, (float) $request->amount_due);
        $this->assertSame([], $request->requestServices->firstWhere('service_id', $first->id)->government_charges_snapshot);
        $this->assertSame('Property Card', $request->requestServices->firstWhere('service_id', $first->id)->required_documents_snapshot[0]['name_en']);
        $this->get(route('request.success'))->assertOk()->assertSee($request->reference_no)->assertSee('Sale Deed')->assertSee('Mutation');
    }

    public function test_request_form_and_snapshot_use_the_same_effective_document_mappings(): void
    {
        $service = $this->service('mapped-documents', 1000, 18, 5);
        $required = $service->requiredDocuments()->create(['name_en' => 'Required Record', 'name_gu' => 'Required Record', 'requirement_type' => 'required', 'is_mandatory' => true, 'is_active' => true, 'sort_order' => 1]);
        $anyOne = $service->requiredDocuments()->create(['name_en' => 'Any One Record', 'name_gu' => 'Any One Record', 'requirement_type' => 'any_one_required', 'is_mandatory' => false, 'is_active' => true, 'sort_order' => 2]);
        $optional = $service->requiredDocuments()->create(['name_en' => 'Optional Record', 'name_gu' => 'Optional Record', 'requirement_type' => 'optional', 'is_mandatory' => false, 'is_active' => true, 'sort_order' => 3]);

        $this->get(route('request.create'))->assertOk()
            ->assertSee('<dt>Required Documents</dt><dd>3</dd>', false)
            ->assertSee('id="required-documents"', false)
            ->assertSee('id="any-one-required-documents"', false)
            ->assertSee('id="optional-documents"', false)
            ->assertSee('Any One Required')
            ->assertSee('any_one_required');

        Storage::fake('local');
        $payload = $this->payload([$service->id]);
        $payload['document_uploads'] = [
            $required->id => UploadedFile::fake()->createWithContent('required-record.pdf', $this->pdfContent()),
            $anyOne->id => UploadedFile::fake()->createWithContent('any-one-record.pdf', $this->pdfContent()."\n% distinct"),
        ];
        $this->post(route('request.store'), $payload)
            ->assertRedirect(route('request.success'));

        $snapshot = collect(CustomerRequest::query()->sole()->requestServices()->sole()->required_documents_snapshot);
        $this->assertSame(
            [
                $required->id => 'required',
                $anyOne->id => 'any_one_required',
                $optional->id => 'optional',
            ],
            $snapshot->pluck('requirement_type', 'id')->all(),
        );
        $this->assertDatabaseCount('request_documents', 2);
    }

    public function test_duplicate_files_and_duplicate_submission_are_rejected(): void
    {
        Storage::fake('local');
        $service = $this->service('title-search', 100, 18, 3);
        $first = $service->requiredDocuments()->create(['name_en' => 'First Record', 'name_gu' => 'First Record', 'is_active' => true]);
        $second = $service->requiredDocuments()->create(['name_en' => 'Second Record', 'name_gu' => 'Second Record', 'is_active' => true]);
        $payload = $this->payload([$service->id]);
        $payload['document_uploads'] = [
            $first->id => UploadedFile::fake()->createWithContent('one.pdf', $this->pdfContent()),
            $second->id => UploadedFile::fake()->createWithContent('two.pdf', $this->pdfContent()),
        ];

        $this->post(route('request.store'), $payload)->assertSessionHasErrors('document_uploads');
        $this->assertDatabaseCount('requests', 0);

        unset($payload['document_uploads']);
        $this->post(route('request.store'), $payload)->assertRedirect(route('request.success'));
        $this->post(route('request.store'), $payload)->assertSessionHasErrors('request');
        $this->assertDatabaseCount('requests', 1);
    }

    private function service(string $slug, float $fee, float $gst, int $days): Service
    {
        return Service::query()->create(['name_en' => str($slug)->replace('-', ' ')->title(), 'name_gu' => 'સેવા '.$slug, 'slug' => $slug, 'service_fee' => $fee, 'gst_rate' => $gst, 'estimated_days' => $days, 'is_active' => true, 'available_online' => true]);
    }

    private function payload(array $serviceIds): array
    {
        return ['service_ids' => $serviceIds, 'name' => 'Production Customer', 'mobile' => '9999999999', 'whatsapp' => '9888888888', 'property_village' => 'Chanasma', 'property_taluka' => 'Chanasma', 'property_district' => 'Patan', 'declaration' => '1'];
    }

    private function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }
}
