<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicServiceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_default_to_public_and_public_surfaces_exclude_internal_only_services(): void
    {
        $public = $this->service('Relinquishment Deed', 'relinquishment-deed');
        $internal = $this->service('Internal Conveyancing Review', 'sale-deed', ['show_on_public_website' => false]);
        $internal->requiredDocuments()->create(['name_en' => 'Internal Review Sheet', 'name_gu' => 'Internal Review Sheet', 'is_active' => true]);

        $this->assertTrue($public->refresh()->show_on_public_website);

        $this->get(route('services.index'))->assertOk()->assertSee($public->name_en)->assertDontSee($internal->name_en);
        $this->get(route('services.index', ['q' => 'Internal Conveyancing']))->assertOk()->assertDontSee($internal->name_en);
        $this->get(route('home'))->assertOk()->assertSee($public->name_en)->assertDontSee($internal->name_en);
        $this->get(route('request.create'))->assertOk()->assertSee($public->name_en)->assertDontSee($internal->name_en);
        $this->get(route('appointments.create'))->assertOk()->assertSee($public->name_en)->assertDontSee($internal->name_en);
        $this->get(route('required-documents'))->assertOk()->assertDontSee($internal->name_en)->assertDontSee('Internal Review Sheet');
        $this->get(route('services.show', $internal->slug))->assertNotFound();

        $sitemap = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertStringContainsString('/services/'.$public->slug, $sitemap);
        $this->assertStringNotContainsString('/services/'.$internal->slug, $sitemap);
    }

    public function test_crafted_public_request_and_appointment_reject_internal_only_service(): void
    {
        $internal = $this->service('Internal Only Service', 'internal-only-service', ['show_on_public_website' => false]);

        $this->post(route('request.store'), [
            'service_id' => $internal->id,
            'service_ids' => [$internal->id],
            'name' => 'Public Customer',
            'mobile' => '9999999999',
            'declaration' => '1',
        ])->assertSessionHasErrors(['service_id', 'service_ids.0']);
        $this->assertDatabaseCount('requests', 0);

        $this->post(route('appointments.store'), [
            'customer_name' => 'Public Customer',
            'mobile' => '9999999999',
            'service_id' => $internal->id,
            'appointment_date' => now('Asia/Kolkata')->addWeek()->toDateString(),
            'appointment_time' => '11:00',
            'declaration' => '1',
        ])->assertSessionHasErrors('service_id');
        $this->getJson(route('appointments.availability', [
            'date' => now('Asia/Kolkata')->addWeek()->toDateString(),
            'service_id' => $internal->id,
        ]))->assertStatus(422);
    }

    public function test_admin_offline_add_on_and_historical_workflows_keep_internal_service_available(): void
    {
        $base = $this->service('Admin Base Service', 'admin-base-service');
        $internal = $this->service('Internal Historical Service', 'internal-historical-service', [
            'show_on_public_website' => false,
            'available_online' => false,
        ]);
        $base->availableAddOns()->attach($internal->id, ['is_active' => true, 'sort_order' => 1]);
        $customerRequest = CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/920001',
            'service_id' => $internal->id,
            'name' => 'Historical Customer',
            'mobile' => '9999999999',
            'request_origin' => 'offline',
            'status' => 'received',
            'payment_status' => 'not_required',
            'amount_due' => 0,
            'last_status_changed_at' => now(),
        ]);
        $customerRequest->requestServices()->create([
            'service_id' => $internal->id,
            'service_name_en_snapshot' => $internal->name_en,
            'professional_fee' => 1000,
            'original_professional_fee' => 1000,
            'status' => 'received',
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.services.index'))->assertOk()->assertSee($internal->name_en)->assertSee('Internal Only');
        $this->get(route('admin.requests.create'))->assertOk()->assertSee($internal->name_en);
        $this->get(route('admin.requests.show', $customerRequest))->assertOk()->assertSee($internal->name_en);
        $this->assertTrue($base->activeAvailableAddOns()->whereKey($internal->id)->exists());

        $this->post(route('request.track.lookup'), [
            'reference_no' => $customerRequest->reference_no,
            'mobile' => $customerRequest->mobile,
        ])->assertOk()->assertSee($internal->name_en);
    }

    public function test_authorized_admin_changes_visibility_independently_and_staff_cannot(): void
    {
        $service = $this->service('Visibility Managed Service', 'visibility-managed-service');
        $admin = User::factory()->create();
        $staff = User::factory()->create(['role' => 'staff']);
        $payload = [
            'name_en' => $service->name_en,
            'name_gu' => $service->name_gu,
            'sort_order' => 1,
            'is_active' => 1,
            'show_on_public_website' => 0,
        ];

        $this->actingAs($admin)->put(route('admin.services.update', $service), $payload)->assertRedirect(route('admin.services.index'));
        $service->refresh();
        $this->assertTrue($service->is_active);
        $this->assertFalse($service->show_on_public_website);

        $this->actingAs($staff)->put(route('admin.services.update', $service), [
            ...$payload,
            'show_on_public_website' => 1,
        ])->assertForbidden();
        $this->assertFalse($service->fresh()->show_on_public_website);
    }

    private function service(string $name, string $slug, array $attributes = []): Service
    {
        return Service::query()->create([
            'name_en' => $name,
            'name_gu' => $name,
            'slug' => $slug,
            'service_fee' => 1999,
            'gst_rate' => 18,
            'is_active' => true,
            'available_online' => true,
            'available_offline' => true,
            ...$attributes,
        ]);
    }
}
