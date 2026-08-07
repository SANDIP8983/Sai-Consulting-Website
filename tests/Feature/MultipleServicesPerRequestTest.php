<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultipleServicesPerRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_request_snapshots_multiple_services_and_uses_one_reference_number(): void
    {
        [$first, $second] = $this->services();
        $request = app(RequestWorkflowService::class)->submit([
            'service_ids' => [$first->id, $second->id], 'name' => 'Customer', 'mobile' => '9999999999',
        ], []);

        $this->assertCount(2, $request->requestServices);
        $this->assertSame($first->id, $request->service_id);
        $this->assertSame('3710.00', $request->amount_due);
        $snapshot = $request->requestServices->firstWhere('service_id', $first->id);
        $this->assertSame('1000.00', $snapshot->professional_fee);
        $this->assertSame('18.00', $snapshot->gst_rate);
        $this->assertSame('0.00', $snapshot->government_charges);
        $this->assertSame('Record', $snapshot->required_documents_snapshot[0]['name_en']);
    }

    public function test_legacy_single_service_submission_still_creates_child_snapshot(): void
    {
        [$service] = $this->services();
        $request = app(RequestWorkflowService::class)->submit(['service_id' => $service->id, 'name' => 'Legacy', 'mobile' => '9999999999'], []);
        $this->assertDatabaseHas('request_services', ['request_id' => $request->id, 'service_id' => $service->id]);
    }

    public function test_admin_can_decide_each_service_independently(): void
    {
        [$first, $second] = $this->services();
        $request = app(RequestWorkflowService::class)->submit(['service_ids' => [$first->id, $second->id], 'name' => 'Customer', 'mobile' => '9999999999'], []);
        $admin = User::factory()->create();
        $items = $request->requestServices;

        $this->actingAs($admin)->patch(route('admin.requests.services.decision', [$request, $items[0]]), ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('admin.requests.services.decision', [$request, $items[1]]), ['decision' => 'rejected', 'decision_notes' => 'Not eligible'])->assertSessionHasNoErrors();

        $this->assertSame('approved', $items[0]->fresh()->status);
        $this->assertSame('rejected', $items[1]->fresh()->status);
        $this->assertSame('received', $request->fresh()->status);
    }

    public function test_public_tracking_shows_all_services_under_the_reference_number(): void
    {
        [$first, $second] = $this->services();
        $request = app(RequestWorkflowService::class)->submit(['service_ids' => [$first->id, $second->id], 'name' => 'Customer', 'mobile' => '9999999999'], []);
        $this->post(route('request.track.lookup'), ['reference_no' => $request->reference_no, 'mobile' => $request->mobile])
            ->assertOk()->assertSee($first->name_en)->assertSee($second->name_en);
    }

    private function services(): array
    {
        $first = Service::query()->create(['name_en' => 'First Service', 'name_gu' => 'First', 'slug' => 'first', 'service_fee' => 1000, 'gst_rate' => 18, 'government_charges' => 200, 'estimated_days' => 5, 'is_active' => true, 'available_online' => true]);
        $first->requiredDocuments()->create(['name_en' => 'Record', 'name_gu' => 'Record', 'sort_order' => 1, 'is_active' => true, 'is_mandatory' => true]);
        $second = Service::query()->create(['name_en' => 'Second Service', 'name_gu' => 'Second', 'slug' => 'second', 'service_fee' => 2000, 'gst_rate' => 5, 'government_charges' => 230, 'estimated_days' => 10, 'is_active' => true, 'available_online' => true]);

        return [$first, $second];
    }
}
