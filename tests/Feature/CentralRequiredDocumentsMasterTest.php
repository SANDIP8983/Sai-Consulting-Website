<?php

namespace Tests\Feature;

use App\Models\CommonRequiredDocument;
use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\CentralRequiredDocumentsSeeder;
use Database\Seeders\ServiceCommercialConfigurationSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CentralRequiredDocumentsMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ServiceSeeder::class, ServiceCommercialConfigurationSeeder::class, CentralRequiredDocumentsSeeder::class]);
    }

    public function test_exact_master_and_all_service_defaults_are_idempotent(): void
    {
        $this->seed(CentralRequiredDocumentsSeeder::class);
        $this->assertDatabaseCount('common_required_documents', 18);
        $this->assertDatabaseCount('service_required_documents', 234);
        $this->assertSame(13, Service::query()->count());
        $this->assertSame(1, CommonRequiredDocument::query()->where('code', 'hak-patrak-village-form-6')->count());
        $this->assertSame(0, CommonRequiredDocument::query()->where('code', 'village-form-6')->count());

        foreach (Service::query()->get() as $service) {
            $this->assertSame(3, $service->requiredDocuments()->where('requirement_type', 'any_one_required')->count());
            $this->assertSame(15, $service->requiredDocuments()->where('requirement_type', 'optional')->count());
        }
    }

    public function test_admin_mapping_states_are_independent_and_master_edits_propagate_names(): void
    {
        $admin = User::factory()->create();
        [$first, $second] = Service::query()->orderBy('id')->limit(2)->get();
        $firstMapping = $first->requiredDocuments()->whereHas('commonDocument', fn ($q) => $q->where('code', 'previous-deed'))->sole();
        $secondMapping = $second->requiredDocuments()->where('common_required_document_id', $firstMapping->common_required_document_id)->sole();

        $this->actingAs($admin)->put(route('admin.required-documents.mappings.update', $first), [
            'mappings' => [$firstMapping->id => ['requirement_type' => 'required', 'sort_order' => 2]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('required', $firstMapping->fresh()->requirement_type);
        $this->assertSame('optional', $secondMapping->fresh()->requirement_type);

        $this->actingAs($admin)->put(route('admin.required-documents.mappings.update', $first), [
            'mappings' => [$firstMapping->id => ['requirement_type' => 'optional', 'sort_order' => 2]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('admin.required-documents.mappings.update', $first), [
            'mappings' => [$firstMapping->id => ['requirement_type' => 'not_applicable', 'sort_order' => 2]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('not_applicable', $firstMapping->fresh()->requirement_type);

        $master = $firstMapping->commonDocument;
        $this->actingAs($admin)->put(route('admin.required-document-master.update', $master), [
            'code' => $master->code, 'name_gu' => 'સુધારેલ અગાઉનો દસ્તાવેજ', 'name_en' => 'Updated Previous Deed',
            'display_order' => $master->display_order, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Updated Previous Deed', $secondMapping->fresh()->name_en);
    }

    public function test_public_grouping_hides_inactive_and_not_applicable_without_blocking_submission(): void
    {
        $service = Service::query()->where('slug', 'sale-deed')->sole();
        $hidden = $service->requiredDocuments()->whereHas('commonDocument', fn ($q) => $q->where('code', 'tax-bill'))->sole();
        $hidden->update(['requirement_type' => 'not_applicable', 'is_active' => false]);
        $inactiveMaster = CommonRequiredDocument::query()->where('code', 'bank-noc')->sole();
        $inactiveMaster->update(['is_active' => false]);

        $this->get(route('services.show', $service->slug))->assertOk()
            ->assertSee('આમાંથી કોઈ એક જરૂરી')->assertSee('Any One Required')
            ->assertSee('7/12 Extract')->assertSee('Property Card')->assertSee('Assessment Register / Village Form No. 2')
            ->assertDontSee('Tax Bill')->assertDontSee('Bank NOC');

        $this->post(route('request.store'), [
            'service_ids' => [$service->id], 'name' => 'No Upload Customer', 'mobile' => '9999999999', 'declaration' => 1,
            'property_village' => 'Village', 'property_taluka' => 'Taluka', 'property_district' => 'District',
        ])->assertRedirect(route('request.success'));
        $this->assertDatabaseCount('requests', 1);
        $snapshot = CustomerRequest::query()->sole()->requestServices()->sole()->required_documents_snapshot;
        $this->assertSame(3, collect($snapshot)->where('requirement_type', 'any_one_required')->count());
    }

    public function test_conversion_does_not_touch_historical_request_upload_or_financial_tables(): void
    {
        $service = Service::query()->firstOrFail();
        $mapping = $service->requiredDocuments()->firstOrFail();
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/880001', 'service_id' => $service->id, 'name' => 'History', 'mobile' => '9999999999', 'status' => 'received']);
        $upload = $request->documents()->create(['service_required_document_id' => $mapping->id, 'file_name' => 'record.pdf', 'file_path' => 'private/record.pdf']);
        $before = DB::table('request_documents')->where('id', $upload->id)->first();

        $this->seed(CentralRequiredDocumentsSeeder::class);

        $this->assertEquals($before, DB::table('request_documents')->where('id', $upload->id)->first());
        $this->assertDatabaseHas('service_required_documents', ['id' => $mapping->id]);
        $this->assertDatabaseCount('request_billings', 0);
        $this->assertDatabaseCount('request_payments', 0);
    }
}
