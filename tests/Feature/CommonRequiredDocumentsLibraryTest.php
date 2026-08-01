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

    public function test_new_service_receives_each_safe_active_common_document_once_as_optional(): void
    {
        CommonRequiredDocument::query()->create(['name_en' => 'Property Card', 'name_gu' => 'Property Card', 'normalized_name' => 'property card', 'is_active' => true, 'is_common' => true]);
        CommonRequiredDocument::query()->create(['name_en' => 'Previous Deed', 'name_gu' => 'Previous Deed', 'normalized_name' => 'previous deed', 'is_active' => true, 'is_common' => true]);
        CommonRequiredDocument::query()->create(['name_en' => 'Inactive Record', 'name_gu' => 'Inactive', 'normalized_name' => 'inactive record', 'is_active' => false, 'is_common' => true]);
        CommonRequiredDocument::query()->create(['name_en' => 'Passport', 'name_gu' => 'Passport', 'normalized_name' => 'passport', 'is_active' => true, 'is_common' => true]);

        $service = $this->service('auto-attachment');
        $service->save();

        $this->assertSame(2, $service->requiredDocuments()->count());
        $this->assertSame(2, $service->requiredDocuments()->distinct('common_required_document_id')->count('common_required_document_id'));
        $this->assertFalse($service->requiredDocuments()->where('is_mandatory', true)->exists());
        $this->assertFalse($service->requiredDocuments()->where('name_en', 'Inactive Record')->exists());
        $this->assertFalse($service->requiredDocuments()->where('name_en', 'Passport')->exists());
    }

    public function test_service_specific_master_does_not_propagate_to_unrelated_services(): void
    {
        $this->actingAs(User::factory()->create())->post(route('admin.services.store'), [
            'name_en' => 'Specific First', 'name_gu' => 'Specific First Gujarati', 'sort_order' => 1, 'is_active' => true,
            'documents' => [['name_en' => 'Internal Local Record', 'name_gu' => 'Local', 'sort_order' => 1, 'is_mandatory' => false]],
        ])->assertRedirect(route('admin.services.index'));
        $first = Service::query()->where('name_en', 'Specific First')->firstOrFail();
        $specific = CommonRequiredDocument::query()->where('normalized_name', 'internal local record')->firstOrFail();
        $this->assertFalse($specific->is_common);

        $second = $this->service('specific-second');

        $this->assertDatabaseHas('service_required_documents', ['service_id' => $first->id, 'common_required_document_id' => $specific->id]);
        $this->assertDatabaseMissing('service_required_documents', ['service_id' => $second->id, 'common_required_document_id' => $specific->id]);
    }

    public function test_new_active_common_document_synchronizes_only_to_active_services(): void
    {
        $active = $this->service('active-target');
        $inactive = $this->service('inactive-target');
        $inactive->update(['is_active' => false]);

        $document = CommonRequiredDocument::query()->create(['name_en' => 'New Common Record', 'name_gu' => 'New', 'normalized_name' => 'new common record', 'is_active' => true, 'is_common' => true]);

        $this->assertDatabaseHas('service_required_documents', ['service_id' => $active->id, 'common_required_document_id' => $document->id, 'is_mandatory' => false]);
        $this->assertDatabaseMissing('service_required_documents', ['service_id' => $inactive->id, 'common_required_document_id' => $document->id]);
    }

    private function service(string $slug): Service
    {
        return Service::query()->create(['name_en' => $slug, 'name_gu' => $slug, 'slug' => $slug, 'is_active' => true, 'available_online' => true]);
    }
}
