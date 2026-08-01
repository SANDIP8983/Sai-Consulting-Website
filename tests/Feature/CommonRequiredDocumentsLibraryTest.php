<?php

namespace Tests\Feature;

use App\Models\CommonRequiredDocument;
use App\Models\Service;
use App\Models\User;
use App\Services\RequestWorkflowService;
use App\Services\ServiceRequiredDocumentManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommonRequiredDocumentsLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_master_document_is_shared_by_every_service_with_independent_configuration(): void
    {
        $first = $this->service('first');
        $second = $this->service('second');
        $configuration = app(ServiceRequiredDocumentManagementService::class)->create([
            'service_id' => $first->id, 'name_en' => 'Property Card', 'name_gu' => 'Property Card',
            'is_mandatory' => true, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->assertDatabaseCount('common_required_documents', 1);
        $this->assertDatabaseHas('service_required_documents', ['service_id' => $first->id, 'common_required_document_id' => $configuration->common_required_document_id, 'is_mandatory' => true, 'is_active' => true]);
        $this->assertDatabaseHas('service_required_documents', ['service_id' => $second->id, 'common_required_document_id' => $configuration->common_required_document_id, 'is_mandatory' => false, 'is_active' => true]);
    }

    public function test_new_service_automatically_inherits_the_common_library(): void
    {
        CommonRequiredDocument::query()->create(['name_en' => 'Village Form', 'name_gu' => 'Village Form', 'normalized_name' => 'village form', 'is_active' => true]);
        $service = $this->service('later');
        $this->assertCount(1, $service->requiredDocuments()->get());
        $this->assertFalse($service->requiredDocuments()->first()->is_mandatory);
        $this->assertTrue($service->requiredDocuments()->first()->is_active);
    }

    public function test_multi_service_request_keeps_each_services_library_snapshot(): void
    {
        $first = $this->service('snapshot-first');
        $second = $this->service('snapshot-second');
        app(ServiceRequiredDocumentManagementService::class)->create(['service_id' => $first->id, 'name_en' => 'Shared Copy', 'name_gu' => 'Shared Copy', 'is_mandatory' => true, 'is_active' => true, 'sort_order' => 1]);
        $second->requiredDocuments()->update(['is_active' => true, 'is_mandatory' => false]);

        $request = app(RequestWorkflowService::class)->submit(['service_ids' => [$first->id, $second->id], 'name' => 'Customer', 'mobile' => '9999999999'], []);
        $snapshots = $request->requestServices->keyBy('service_id');
        $this->assertTrue($snapshots[$first->id]->required_documents_snapshot[0]['is_mandatory']);
        $this->assertFalse($snapshots[$second->id]->required_documents_snapshot[0]['is_mandatory']);
    }

    public function test_admin_cannot_add_personal_kyc_document_to_public_library(): void
    {
        $service = $this->service('safe-library');
        $this->actingAs(User::factory()->create())->post(route('admin.required-documents.store'), [
            'service_id' => $service->id, 'name_en' => 'Aadhaar Card', 'name_gu' => 'Aadhaar',
            'is_mandatory' => false, 'is_active' => true, 'sort_order' => 1,
        ])->assertSessionHasErrors('name_en');
        $this->assertDatabaseCount('common_required_documents', 0);
    }

    private function service(string $slug): Service
    {
        return Service::query()->create(['name_en' => $slug, 'name_gu' => $slug, 'slug' => $slug, 'is_active' => true, 'available_online' => true]);
    }
}
