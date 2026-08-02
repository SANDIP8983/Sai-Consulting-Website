<?php

namespace Tests\Feature\Admin;

use App\Enums\PdfDocumentType;
use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\Pdf\CustomerSafePdfDataFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_downloads_require_authentication(): void
    {
        $request = $this->request();

        $this->get(route('admin.requests.pdf.download', [$request, PdfDocumentType::RequestAcknowledgement->value]))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_download_all_customer_safe_pdf_types(): void
    {
        $admin = User::factory()->create();
        $request = $this->request([
            'name' => $this->unicode('&#xA97;&#xAC1;&#xA9C;&#xAB0;&#xABE;&#xAA4;&#xAC0; &#xA97;&#xACD;&#xAB0;&#xABE;&#xAB9;&#xA95;'),
            'details' => $this->unicode('&#xAAE;&#xABF;&#xAB2;&#xA95;&#xAA4; &#xAA6;&#xAB8;&#xACD;&#xAA4;&#xABE;&#xAB5;&#xAC7;&#xA9C;&#xAA8;&#xAC0; &#xAB5;&#xABF;&#xAA8;&#xA82;&#xAA4;&#xAC0;'),
        ]);

        foreach (PdfDocumentType::cases() as $type) {
            $response = $this->actingAs($admin)->get(route('admin.requests.pdf.download', [$request, $type->value]));

            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
            $cacheControl = (string) $response->headers->get('cache-control');
            $this->assertStringContainsString('private', $cacheControl);
            $this->assertStringContainsString('no-store', $cacheControl);
        }
    }

    public function test_admin_request_page_shows_pdf_buttons_and_invalid_type_is_rejected(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();

        $this->actingAs($admin)->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee('Download Request Acknowledgement')
            ->assertSee('Download Payment Summary')
            ->assertSee('Download Case Summary')
            ->assertSee('Download Dispatch Slip');

        $this->actingAs($admin)->get(route('admin.requests.pdf.download', [$request, 'not-a-document']))->assertNotFound();
    }

    public function test_projection_excludes_private_operational_data(): void
    {
        $admin = User::factory()->create(['name' => 'PRIVATE ADMIN IDENTITY']);
        $request = $this->request([
            'completion_internal_note' => 'PRIVATE COMPLETION NOTE',
            'closure_internal_note' => 'PRIVATE CLOSURE NOTE',
        ]);
        $selectedService = $request->requestServices()->create(['service_id' => $request->service_id, 'service_name_en_snapshot' => 'Safe Service', 'professional_fee' => 1000, 'status' => 'approved']);
        $selectedService->workScopes()->create(['name_en_snapshot' => 'PRIVATE CHECKLIST ITEM', 'status' => 'completed']);
        $request->dispatches()->create([
            'dispatch_method' => 'courier',
            'dispatch_status' => 'dispatched',
            'dispatch_date' => now(),
            'document_description' => 'Customer package',
            'customer_remark' => 'CUSTOMER SAFE REMARK',
            'internal_note' => 'PRIVATE DISPATCH NOTE',
            'failure_reason' => 'PRIVATE FAILURE REASON',
            'performed_by' => $admin->id,
        ]);

        $document = app(CustomerSafePdfDataFactory::class)->make(PdfDocumentType::CaseSummary, $request);
        $projection = json_encode($document->content, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('CUSTOMER SAFE REMARK', $projection);
        $this->assertStringNotContainsString('PRIVATE COMPLETION NOTE', $projection);
        $this->assertStringNotContainsString('PRIVATE CLOSURE NOTE', $projection);
        $this->assertStringNotContainsString('PRIVATE DISPATCH NOTE', $projection);
        $this->assertStringNotContainsString('PRIVATE FAILURE REASON', $projection);
        $this->assertStringNotContainsString('PRIVATE ADMIN IDENTITY', $projection);
        $this->assertArrayNotHasKey('internal_note', $document->content['dispatches'][0]);
        $this->assertArrayNotHasKey('scopes', $document->content['services'][0]);
    }

    public function test_no_public_pdf_download_endpoint_is_exposed(): void
    {
        $request = $this->request();

        $this->get('/requests/'.$request->reference_no.'/pdf/request-acknowledgement')->assertNotFound();
    }

    private function request(array $attributes = []): CustomerRequest
    {
        $suffix = fake()->unique()->numerify('######');
        $service = Service::query()->create([
            'name_en' => 'PDF Service '.$suffix,
            'name_gu' => $this->unicode('&#xAA6;&#xAB8;&#xACD;&#xAA4;&#xABE;&#xAB5;&#xAC7;&#xA9C; &#xAB8;&#xAC7;&#xAB5;&#xABE;').' '.$suffix,
            'slug' => 'pdf-service-'.$suffix,
            'is_active' => true,
        ]);

        return CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/'.fake()->unique()->numerify('######'),
            'file_number' => 'SC/2026/F'.fake()->unique()->numerify('######'),
            'request_origin' => 'online',
            'service_id' => $service->id,
            'name' => 'PDF Customer',
            'mobile' => '9999999999',
            'email' => 'customer@example.com',
            'address' => 'Patan, Gujarat',
            'status' => 'completed',
            'payment_status' => 'received',
            'amount_due' => 1500,
            'amount_paid' => 1500,
            'completed_at' => now(),
            'last_status_changed_at' => now(),
            ...$attributes,
        ]);
    }

    private function unicode(string $entities): string
    {
        return html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
