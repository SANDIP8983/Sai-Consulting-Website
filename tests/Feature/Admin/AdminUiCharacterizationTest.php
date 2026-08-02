<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUiCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_detail_rendering_does_not_mutate_workflow_data(): void
    {
        $admin = User::factory()->create();
        $request = $this->request(['status' => 'payment_pending', 'payment_status' => 'pending']);
        $before = $request->only(['reference_no', 'file_number', 'status', 'payment_status', 'amount_due', 'amount_paid']);

        $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertOk();

        $this->assertSame($before, $request->fresh()->only(array_keys($before)));
        $this->assertDatabaseCount('request_payments', 0);
        $this->assertDatabaseCount('request_dispatches', 0);
    }

    public function test_existing_detail_page_keeps_customer_property_service_and_private_note_controls(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();

        $this->actingAs($admin)->get(route('admin.requests.show', $request))
            ->assertOk()
            ->assertSee($request->name)
            ->assertSee($request->mobile)
            ->assertSee($request->survey_numbers)
            ->assertSee($request->khata_number)
            ->assertSee($request->service->name_en)
            ->assertSee('Admin-only internal note');
    }

    public function test_existing_search_fields_and_workflow_filters_are_available(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();

        $this->actingAs($admin)->get(route('admin.requests.index', ['q' => $request->reference_no]))
            ->assertOk()
            ->assertSee($request->reference_no)
            ->assertSee('name="status"', false)
            ->assertSee('name="payment_status"', false)
            ->assertSee('name="processing_state"', false)
            ->assertSee('name="dispatch_state"', false)
            ->assertSee('name="date_from"', false)
            ->assertSee('name="date_to"', false);
    }

    public function test_refined_detail_uses_single_open_accordion_and_sticky_workflow_shortcuts(): void
    {
        $admin = User::factory()->create();
        $request = $this->request();

        $response = $this->actingAs($admin)->get(route('admin.requests.show', $request))->assertOk();
        $response->assertSee('id="request-detail-sections"', false)
            ->assertSee('id="panel-file" class="accordion-collapse collapse show"', false)
            ->assertSee('id="panel-customer" class="accordion-collapse collapse"', false)
            ->assertSee('aria-label="Request actions"', false)
            ->assertSee('Overall Progress')
            ->assertSee('Activity History');
        $this->assertSame(1, substr_count($response->getContent(), 'accordion-collapse collapse show'));
    }

    public function test_extended_property_search_and_operational_dashboard_widgets_are_read_only(): void
    {
        $admin = User::factory()->create();
        $match = $this->request(['property_village' => 'Santej', 'survey_numbers' => 'SURVEY-88', 'khata_number' => 'KHATA-88']);
        $hidden = $this->request(['property_village' => 'Kalol', 'survey_numbers' => 'SURVEY-99', 'khata_number' => 'KHATA-99']);

        $this->actingAs($admin)->get(route('admin.requests.index', ['village' => 'Santej', 'survey_number' => '88', 'khata_number' => 'KHATA-88']))
            ->assertOk()->assertSee($match->reference_no)->assertDontSee($hidden->reference_no);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee("Today's Requests")
            ->assertSee('Pending Approval')
            ->assertSee('Pending Payment')
            ->assertSee('Ready For Dispatch')
            ->assertSee('Overdue Cases');
    }

    private function request(array $attributes = []): CustomerRequest
    {
        $suffix = fake()->unique()->numerify('######');
        $service = Service::query()->create([
            'name_en' => 'UI Review Service '.$suffix,
            'name_gu' => 'UI Review Service '.$suffix,
            'slug' => 'ui-review-service-'.$suffix,
            'is_active' => true,
        ]);

        return CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/'.fake()->unique()->numerify('######'),
            'file_number' => 'SC/2026/F'.fake()->unique()->numerify('######'),
            'request_origin' => 'online',
            'service_id' => $service->id,
            'name' => 'UI Customer',
            'mobile' => '9999999999',
            'email' => 'ui@example.com',
            'address' => 'Patan, Gujarat',
            'property_village' => 'Patan',
            'survey_numbers' => '12/1',
            'khata_number' => 'KH-12',
            'details' => 'UI-only characterization request',
            'status' => 'received',
            'payment_status' => 'not_required',
            'amount_due' => 1000,
            'amount_paid' => 0,
            'last_status_changed_at' => now(),
            ...$attributes,
        ]);
    }
}
