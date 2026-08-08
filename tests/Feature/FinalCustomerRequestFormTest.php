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
        $first->requiredDocuments()->create(['name_en' => 'Property Card', 'name_gu' => 'પ્રોપર્ટી કાર્ડ', 'is_mandatory' => true]);

        $response = $this->post(route('request.store'), $this->payload([$first->id, $second->id]));

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

    public function test_duplicate_files_and_duplicate_submission_are_rejected(): void
    {
        Storage::fake('local');
        $service = $this->service('title-search', 100, 18, 3);
        $payload = $this->payload([$service->id]);
        $payload['documents'] = [
            UploadedFile::fake()->createWithContent('one.pdf', $this->pdfContent()),
            UploadedFile::fake()->createWithContent('two.pdf', $this->pdfContent()),
        ];

        $this->post(route('request.store'), $payload)->assertSessionHasErrors('documents');
        $this->assertDatabaseCount('requests', 0);

        unset($payload['documents']);
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
