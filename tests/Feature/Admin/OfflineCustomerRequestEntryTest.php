<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRequest;
use App\Models\Holiday;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfflineCustomerRequestEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-17 10:00:00 Asia/Kolkata');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_offline_entry_routes_are_admin_only(): void
    {
        $this->get(route('admin.requests.create'))->assertRedirect(route('login'));
        $this->post(route('admin.requests.store'), [])->assertRedirect(route('login'));
    }

    public function test_admin_can_create_an_offline_request_using_the_existing_workflow(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $service = $this->service();

        $response = $this->actingAs($admin)->post(route('admin.requests.store'), $this->payload($service));

        $request = CustomerRequest::query()->sole();
        $response->assertRedirect(route('admin.requests.show', $request));
        $this->assertSame('offline', $request->request_origin);
        $this->assertSame('received', $request->status);
        $this->assertMatchesRegularExpression('/^SC\/\d{4}\/000001$/', $request->reference_no);
        $this->assertSame('750.00', $request->amount_due);
        $this->assertSame('2026-08-24', $request->estimated_completion_date->toDateString());
        $document = $request->documents()->sole();
        $this->assertSame('admin', $document->source);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertDatabaseHas('request_status_histories', [
            'request_id' => $request->id,
            'to_status' => 'received',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_request_estimate_skips_configured_holiday_and_automatic_closed_days(): void
    {
        Carbon::setTestNow('2026-05-08 10:00:00 Asia/Kolkata');
        Storage::fake('local');
        Holiday::query()->create(['holiday_date' => '2026-05-11', 'title' => 'Government Holiday', 'is_closed' => true]);
        $service = $this->service();
        $service->update(['estimated_days' => 1]);

        $this->actingAs(User::factory()->create())->post(route('admin.requests.store'), $this->payload($service))->assertRedirect();

        $this->assertSame('2026-05-12', CustomerRequest::query()->sole()->estimated_completion_date->toDateString());
    }

    public function test_offline_entry_reuses_public_validation_without_requiring_declaration(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $service = $this->service();

        $this->actingAs($admin)->from(route('admin.requests.create'))->post(route('admin.requests.store'), [
            ...$this->payload($service),
            'mobile' => '123',
        ])->assertRedirect(route('admin.requests.create'))->assertSessionHasErrors('mobile')->assertSessionDoesntHaveErrors('declaration');
    }

    public function test_admin_request_source_filter_badges_details_and_dashboard_counts(): void
    {
        $admin = User::factory()->create();
        $service = $this->service();
        $online = $this->request($service, 'SC/2026/000010', 'online');
        $offline = $this->request($service, 'SC/2026/000011', 'offline');

        $this->actingAs($admin)->get(route('admin.requests.index', ['source' => 'offline']))
            ->assertOk()->assertSee($offline->reference_no)->assertDontSee($online->reference_no)->assertSee('Offline');
        $this->actingAs($admin)->get(route('admin.requests.index', ['source' => 'online']))
            ->assertOk()->assertSee($online->reference_no)->assertDontSee($offline->reference_no)->assertSee('Online');
        $this->actingAs($admin)->get(route('admin.requests.show', $offline))
            ->assertOk()->assertSee('Request Source')->assertSee('Offline');
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Total Online Requests')->assertSee('Total Offline Requests')->assertSee('Total Active Services')->assertSee('Total Online Services')->assertSee('Total Offline Services');
    }

    public function test_offline_request_tracking_remains_reference_and_mobile_based(): void
    {
        $request = $this->request($this->service(), 'SC/2026/000020', 'offline');

        $this->post(route('request.track.lookup'), [
            'reference_no' => $request->reference_no,
            'mobile' => $request->mobile,
        ])->assertOk()->assertSee($request->reference_no);
    }

    private function payload(Service $service): array
    {
        return [
            'service_id' => $service->id,
            'name' => 'Offline Customer',
            'mobile' => '9876543210',
            'email' => 'offline@example.com',
            'address' => 'Patan, Gujarat',
            'property_village' => 'Patan',
            'property_taluka' => 'Patan',
            'property_district' => 'Patan',
            'survey_numbers' => '42/1',
            'khata_number' => 'KH-42',
            'details' => 'Walk-in request details.',
            'documents' => [UploadedFile::fake()->create('property.pdf', 100, 'application/pdf')],
        ];
    }

    private function service(): Service
    {
        return Service::query()->create([
            'name_en' => 'Offline Test Service',
            'name_gu' => 'Offline Test Service',
            'slug' => 'offline-test-service',
            'service_fee' => 750,
            'estimated_days' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function request(Service $service, string $reference, string $origin): CustomerRequest
    {
        return CustomerRequest::query()->create([
            'reference_no' => $reference,
            'request_origin' => $origin,
            'service_id' => $service->id,
            'name' => ucfirst($origin).' Customer',
            'mobile' => '9999999999',
            'address' => 'Patan, Gujarat',
            'property_village' => 'Patan',
            'property_taluka' => 'Patan',
            'property_district' => 'Patan',
            'survey_numbers' => '12/1',
            'khata_number' => 'KH-1',
            'details' => 'Test request',
            'status' => 'received',
            'payment_status' => 'not_required',
            'last_status_changed_at' => now(),
        ]);
    }

    public function test_offline_disabled_service_is_hidden_and_rejected(): void
    {
        $admin = User::factory()->create();
        $service = $this->service();
        $service->update(['available_offline' => false]);
        $this->actingAs($admin)->get(route('admin.requests.create'))->assertOk()->assertDontSee('<option value="'.$service->id.'"', false);
        $this->post(route('admin.requests.store'), $this->payload($service))->assertSessionHasErrors('service_id');
    }
}
