<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\FileDocumentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestRegistrationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_record_registration_and_post_registration_information(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();
        app(FileDocumentProcessingService::class)->open($request, [], $admin);
        $this->actingAs($admin)->patch(route('admin.requests.processing.registration.update', $request), ['token_booking_status' => 'booked', 'token_number' => 'T-100', 'token_scheduled_at' => '2026-08-02 10:00', 'sub_registrar_office_name' => 'Chanasma SRO', 'registration_appointment_at' => '2026-08-03 11:00', 'registration_date' => '2026-08-03', 'registration_number' => 'REG-100', 'registration_number_public' => 1, 'registration_internal_note' => 'Private registration note.', 'registration_customer_remark' => 'Your registration is complete.'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.processing.post-registration.update', $request), ['certified_copy_status' => 'received', 'certified_copy_received_date' => '2026-08-05', 'ready_for_dispatch_date' => '2026-08-06'])->assertSessionHasNoErrors();
        $p = $request->processing->fresh();
        $this->assertSame('REG-100', $p->registration_number);
        $this->assertTrue($p->registration_number_public);
        $this->assertSame('received', $p->certified_copy_status);
    }

    public function test_registered_scan_uses_private_request_document_storage(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $request = $this->request();
        app(FileDocumentProcessingService::class)->open($request, [], $admin);
        $this->actingAs($admin)->post(route('admin.requests.processing.registered-scan.store', $request), ['registered_document' => UploadedFile::fake()->create('registered.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();
        $document = $request->documents()->sole();
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame('admin', $document->source);
        $this->assertSame($document->id, $request->processing->fresh()->registered_document_id);
    }

    public function test_non_registration_service_hides_registration_sections(): void
    {
        $admin = User::factory()->create();
        $request = $this->request(false);
        app(FileDocumentProcessingService::class)->open($request, [], $admin);
        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertOk()->assertDontSee('Registration / Document Number')->assertDontSee('Certified Copy Status');
    }

    private function request(bool $registration = true): CustomerRequest
    {
        $service = Service::query()->create(['name_en' => 'Registration Service', 'name_gu' => 'Registration Service', 'slug' => 'registration-service-'.($registration ? 'yes' : 'no'), 'is_active' => true, 'sort_order' => 1, 'requires_token_booking' => $registration, 'requires_registration' => $registration, 'requires_certified_copy' => $registration]);

        return CustomerRequest::query()->create(['reference_no' => 'SC/2026/000700', 'file_number' => 'SC/2026/F000700', 'service_id' => $service->id, 'name' => 'Customer', 'mobile' => '9999999999', 'status' => 'payment_received', 'payment_status' => 'received']);
    }
}
