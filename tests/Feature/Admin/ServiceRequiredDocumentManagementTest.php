<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\ServiceRequiredDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequiredDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_manage_required_documents(): void
    {
        $this->get(route('admin.required-documents.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_list_search_create_edit_and_delete_unused_documents(): void
    {
        $admin = User::factory()->create();
        $service = $this->service();

        $this->actingAs($admin)->post(route('admin.required-documents.store'), [
            'service_id' => $service->id,
            'name_gu' => 'મિલકત કાર્ડ',
            'name_en' => 'Property Card',
            'is_mandatory' => true,
            'sort_order' => 4,
            'is_active' => true,
        ])->assertRedirect(route('admin.required-documents.index'));

        $document = ServiceRequiredDocument::query()->firstOrFail();
        $this->get(route('admin.required-documents.index', ['q' => 'Property']))
            ->assertOk()->assertSee('Property Card')->assertSee('Required');

        $this->put(route('admin.required-documents.update', $document), [
            'service_id' => $service->id,
            'name_gu' => 'જૂનો દસ્તાવેજ',
            'name_en' => 'Previous Deed',
            'is_mandatory' => false,
            'sort_order' => 2,
            'is_active' => false,
        ])->assertRedirect(route('admin.required-documents.index'));

        $this->assertDatabaseHas('service_required_documents', ['id' => $document->id, 'name_en' => 'Previous Deed', 'is_mandatory' => false, 'is_active' => false]);
        $this->delete(route('admin.required-documents.destroy', $document))->assertRedirect(route('admin.required-documents.index'));
        $this->assertDatabaseMissing('service_required_documents', ['id' => $document->id]);
    }

    public function test_used_document_is_soft_deleted_and_request_link_is_preserved(): void
    {
        $admin = User::factory()->create();
        $service = $this->service();
        $document = $service->requiredDocuments()->create(['name_gu' => 'નકલ', 'name_en' => 'Copy', 'sort_order' => 1]);
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/900001', 'service_id' => $service->id, 'name' => 'Customer', 'mobile' => '9999999999', 'status' => 'received']);
        $requestDocument = $request->documents()->create(['service_required_document_id' => $document->id, 'file_name' => 'copy.pdf', 'file_path' => 'private/copy.pdf']);

        $this->actingAs($admin)->delete(route('admin.required-documents.destroy', $document))->assertSessionHas('success');

        $this->assertSoftDeleted('service_required_documents', ['id' => $document->id]);
        $this->assertDatabaseHas('request_documents', ['id' => $requestDocument->id, 'service_required_document_id' => $document->id]);
    }

    public function test_admin_can_reorder_only_documents_for_the_selected_service(): void
    {
        $service = $this->service();
        $first = $service->requiredDocuments()->create(['name_gu' => 'એક', 'name_en' => 'One', 'sort_order' => 1]);
        $second = $service->requiredDocuments()->create(['name_gu' => 'બે', 'name_en' => 'Two', 'sort_order' => 2]);

        $this->actingAs(User::factory()->create())->patch(route('admin.required-documents.reorder', $service), [
            'documents' => [$second->id, $first->id],
        ])->assertSessionHas('success');

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    private function service(): Service
    {
        return Service::query()->create(['name_en' => 'Title Search', 'name_gu' => 'ટાઇટલ સર્ચ', 'slug' => 'title-search-docs', 'is_active' => true, 'available_online' => true]);
    }
}
