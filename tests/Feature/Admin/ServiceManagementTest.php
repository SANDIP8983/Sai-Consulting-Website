<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_service_management(): void
    {
        $this->get(route('admin.services.index'))
            ->assertRedirect(route('login'));
    }

    public function test_administrator_can_create_a_service_with_required_documents(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.services.store'), [
                'name_en' => 'Sale Deed',
                'name_gu' => 'વેચાણ દસ્તાવેજ',
                'description' => 'Professional sale deed drafting service.',
                'sort_order' => 10,
                'is_active' => true,
                'documents' => [
                    ['name_en' => 'Property Card', 'name_gu' => 'પ્રોપર્ટી કાર્ડ', 'sort_order' => 1],
                    ['name_en' => 'Previous Deed', 'name_gu' => 'અગાઉનો દસ્તાવેજ', 'sort_order' => 2],
                ],
            ])
            ->assertRedirect(route('admin.services.index'));

        $service = Service::query()->firstOrFail();

        $this->assertSame('sale-deed', $service->slug);
        $this->assertDatabaseHas('service_required_documents', [
            'service_id' => $service->id,
            'name_en' => 'Property Card',
        ]);
        $this->assertCount(2, $service->requiredDocuments);
    }

    public function test_service_requires_bilingual_names_for_each_required_document(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('admin.services.create'))
            ->post(route('admin.services.store'), [
                'name_en' => 'Sale Deed',
                'name_gu' => 'વેચાણ દસ્તાવેજ',
                'sort_order' => 1,
                'is_active' => true,
                'documents' => [
                    ['name_en' => '', 'name_gu' => '', 'sort_order' => -1],
                ],
            ])
            ->assertRedirect(route('admin.services.create'))
            ->assertSessionHasErrors([
                'documents.0.name_en',
                'documents.0.name_gu',
                'documents.0.sort_order',
            ]);

        $this->assertDatabaseCount('services', 0);
    }

    public function test_administrator_can_update_and_delete_a_service(): void
    {
        $service = Service::query()->create([
            'name_en' => 'Legal Consultation',
            'name_gu' => 'કાનૂની સલાહ',
            'slug' => 'legal-consultation',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $service->requiredDocuments()->create([
            'name_en' => 'Identity Proof',
            'name_gu' => 'ઓળખનો પુરાવો',
            'sort_order' => 1,
        ]);

        $this->actingAs(User::factory()->create())
            ->put(route('admin.services.update', $service), [
                'name_en' => 'Legal Consultation Service',
                'name_gu' => 'કાનૂની સલાહ સેવા',
                'description' => 'Professional legal consultation.',
                'sort_order' => 5,
                'is_active' => false,
                'documents' => [
                    ['name_en' => 'Case Summary', 'name_gu' => 'કેસનો સારાંશ', 'sort_order' => 1],
                ],
            ])
            ->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name_en' => 'Legal Consultation Service',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('service_required_documents', [
            'service_id' => $service->id,
            'name_en' => 'Case Summary',
        ]);
        $this->assertDatabaseMissing('service_required_documents', [
            'service_id' => $service->id,
            'name_en' => 'Identity Proof',
        ]);

        $this->delete(route('admin.services.destroy', $service))
            ->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
        $this->assertDatabaseMissing('service_required_documents', ['service_id' => $service->id]);
    }

    public function test_administrator_cannot_delete_a_service_linked_to_customer_requests(): void
    {
        $service = Service::query()->create([
            'name_en' => 'Property Verification',
            'name_gu' => 'મિલકત ચકાસણી',
            'slug' => 'property-verification',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/000001',
            'service_id' => $service->id,
            'name' => 'Test Customer',
            'mobile' => '9999999999',
            'status' => 'received',
        ]);

        $this->actingAs(User::factory()->create())
            ->from(route('admin.services.index'))
            ->delete(route('admin.services.destroy', $service))
            ->assertRedirect(route('admin.services.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }

    public function test_service_filters_and_validation_rules_are_enforced(): void
    {
        $admin = User::factory()->create();
        Service::query()->create(['name_en' => 'Online Only', 'name_gu' => 'ઓનલાઇન', 'slug' => 'online-only', 'is_active' => true, 'available_online' => true, 'available_offline' => false]);
        Service::query()->create(['name_en' => 'Offline Only', 'name_gu' => 'ઓફલાઇન', 'slug' => 'offline-only', 'is_active' => false, 'available_online' => false, 'available_offline' => true]);

        $this->actingAs($admin)->get(route('admin.services.index', ['availability' => 'online']))->assertOk()->assertSee('Online Only')->assertDontSee('Offline Only');
        $this->get(route('admin.services.index', ['availability' => 'offline']))->assertOk()->assertSee('Offline Only')->assertDontSee('Online Only');
        $this->post(route('admin.services.store'), ['name_en' => 'Online Only', 'name_gu' => 'નવી', 'service_fee' => -1, 'estimated_days' => -1, 'sort_order' => 0, 'is_active' => 1])
            ->assertSessionHasErrors(['name_en', 'service_fee', 'estimated_days']);
    }

    public function test_required_document_updates_preserve_the_document_id(): void
    {
        $service = Service::query()->create(['name_en' => 'Document Service', 'name_gu' => 'દસ્તાવેજ સેવા', 'slug' => 'document-service', 'is_active' => true]);
        $document = $service->requiredDocuments()->create(['name_en' => 'Old', 'name_gu' => 'જૂનું', 'sort_order' => 1]);
        $this->actingAs(User::factory()->create())->put(route('admin.services.update', $service), [
            'name_en' => $service->name_en, 'name_gu' => $service->name_gu, 'sort_order' => 0, 'is_active' => 1,
            'documents' => [['id' => $document->id, 'name_en' => 'Updated', 'name_gu' => 'સુધારેલ', 'is_mandatory' => 0, 'allowed_file_types' => ['pdf'], 'max_upload_size_kb' => 2048, 'sort_order' => 5]],
        ])->assertRedirect(route('admin.services.index'));
        $this->assertDatabaseHas('service_required_documents', ['id' => $document->id, 'name_en' => 'Updated', 'is_mandatory' => false, 'max_upload_size_kb' => 2048, 'sort_order' => 5]);
    }
}
