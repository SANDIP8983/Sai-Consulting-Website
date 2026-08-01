<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRequiredDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_and_service_selection_show_only_active_documents_with_requirement_type(): void
    {
        $service = Service::query()->create(['name_en' => 'Document Service', 'name_gu' => 'દસ્તાવેજ સેવા', 'slug' => 'document-service', 'is_active' => true, 'available_online' => true]);
        $service->requiredDocuments()->create(['name_en' => 'Required Copy', 'name_gu' => 'જરૂરી નકલ', 'is_mandatory' => true, 'is_active' => true, 'sort_order' => 2]);
        $service->requiredDocuments()->create(['name_en' => 'Optional Copy', 'name_gu' => 'વૈકલ્પિક નકલ', 'is_mandatory' => false, 'is_active' => true, 'sort_order' => 1]);
        $service->requiredDocuments()->create(['name_en' => 'Hidden Copy', 'name_gu' => 'છુપાયેલી નકલ', 'is_active' => false, 'sort_order' => 0]);

        $this->get(route('services.show', $service->slug))->assertOk()
            ->assertSeeInOrder(['Required Copy', 'Optional Copy'])->assertSee('text-bg-danger')->assertSee('Required')->assertSee('Optional')->assertDontSee('Hidden Copy');

        $this->get(route('request.create', ['service' => $service->id]))->assertOk()
            ->assertSee('Required Copy')->assertSee('Optional Copy')->assertDontSee('Hidden Copy')
            ->assertSee('document-required')->assertSee('document-optional');
    }
}
