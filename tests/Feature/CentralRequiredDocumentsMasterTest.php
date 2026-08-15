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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $configured = Service::query()->firstOrFail()->requiredDocuments()
            ->whereHas('commonDocument', fn ($query) => $query->where('code', 'previous-deed'))
            ->sole();
        $configured->update(['requirement_type' => 'required', 'is_mandatory' => true]);

        $this->seed(CentralRequiredDocumentsSeeder::class);
        $this->seed(CentralRequiredDocumentsSeeder::class);
        $this->assertDatabaseCount('common_required_documents', 18);
        $this->assertDatabaseCount('service_required_documents', 234);
        $this->assertSame(13, Service::query()->count());
        $this->assertSame(18, CommonRequiredDocument::query()->whereNotNull('code')->distinct()->count('code'));
        $this->assertSame(0, CommonRequiredDocument::query()->whereNull('code')->count());
        $this->assertSame('required', $configured->fresh()->requirement_type);
        $this->assertTrue($configured->fresh()->is_mandatory);
        $this->assertSame(1, CommonRequiredDocument::query()->where('code', 'hak-patrak-village-form-6')->count());
        $this->assertSame(0, CommonRequiredDocument::query()->where('code', 'village-form-6')->count());

        foreach (Service::query()->get() as $service) {
            $anyOneCount = $service->requires_property_documents ? 3 : 0;
            $this->assertSame($anyOneCount, $service->requiredDocuments()->where('requirement_type', 'any_one_required')->count());
            $this->assertSame(18 - $anyOneCount - ($service->is($configured->service) ? 1 : 0), $service->requiredDocuments()->where('requirement_type', 'optional')->count());
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

    public function test_public_grouping_hides_inactive_and_not_applicable_and_enforces_any_one_upload(): void
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
        ])->assertSessionHasErrors('documents');
        $this->assertDatabaseCount('requests', 0);

        Storage::fake('local');
        $this->post(route('request.store'), [
            'service_ids' => [$service->id], 'name' => 'Upload Customer', 'mobile' => '9999999999', 'declaration' => 1,
            'property_village' => 'Village', 'property_taluka' => 'Taluka', 'property_district' => 'District',
            'documents' => [UploadedFile::fake()->createWithContent('land-record.pdf', $this->pdfContent())],
        ])->assertRedirect(route('request.success'));
        $this->assertDatabaseCount('requests', 1);
        $snapshot = CustomerRequest::query()->sole()->requestServices()->sole()->required_documents_snapshot;
        $this->assertSame(3, collect($snapshot)->where('requirement_type', 'any_one_required')->count());
    }

    public function test_legal_consulting_has_only_optional_documents_and_accepts_no_upload(): void
    {
        $legal = Service::query()->where('slug', 'legal-consulting')->sole();

        $this->assertFalse($legal->requires_property_documents);
        $this->assertSame(18, $legal->activeRequiredDocuments()->count());
        $this->assertSame(0, $legal->activeRequiredDocuments()->whereIn('requirement_type', ['required', 'any_one_required'])->count());
        $this->assertSame(18, $legal->activeRequiredDocuments()->where('requirement_type', 'optional')->count());

        $this->post(route('request.store'), [
            'service_ids' => [$legal->id], 'name' => 'Legal Customer', 'mobile' => '9999999999', 'declaration' => 1,
        ])->assertRedirect(route('request.success'));

        $snapshot = collect(CustomerRequest::query()->sole()->requestServices()->sole()->required_documents_snapshot);
        $this->assertSame(0, $snapshot->where('requirement_type', 'any_one_required')->count());
        $this->assertSame(18, $snapshot->where('requirement_type', 'optional')->count());
    }

    public function test_multi_service_request_preserves_property_any_one_requirement_without_promoting_legal_documents(): void
    {
        Storage::fake('local');
        $property = Service::query()->where('slug', 'sale-deed')->sole();
        $legal = Service::query()->where('slug', 'legal-consulting')->sole();
        $payload = [
            'service_ids' => [$legal->id, $property->id], 'name' => 'Combined Customer', 'mobile' => '9999999999', 'declaration' => 1,
            'property_village' => 'Village', 'property_taluka' => 'Taluka', 'property_district' => 'District',
        ];

        $this->post(route('request.store'), $payload)->assertSessionHasErrors('documents');
        $this->assertDatabaseCount('requests', 0);

        $payload['documents'] = [UploadedFile::fake()->createWithContent('property-card.pdf', $this->pdfContent())];
        $this->post(route('request.store'), $payload)->assertRedirect(route('request.success'));

        $request = CustomerRequest::query()->with('requestServices')->sole();
        $propertySnapshot = collect($request->requestServices->firstWhere('service_id', $property->id)->required_documents_snapshot);
        $legalSnapshot = collect($request->requestServices->firstWhere('service_id', $legal->id)->required_documents_snapshot);
        $this->assertSame(3, $propertySnapshot->where('requirement_type', 'any_one_required')->count());
        $this->assertSame(0, $legalSnapshot->where('requirement_type', 'any_one_required')->count());
        $this->assertSame(18, $legalSnapshot->where('requirement_type', 'optional')->count());
    }

    public function test_data_correction_preserves_mappings_and_only_scopes_seeded_land_record_requirements(): void
    {
        $legal = Service::query()->where('slug', 'legal-consulting')->sole();
        $property = Service::query()->where('slug', 'sale-deed')->sole();
        $legal->update(['requires_property_documents' => true]);
        $landRecordIds = CommonRequiredDocument::query()
            ->whereIn('code', ['7-12-extract', 'property-card', 'assessment-register-village-form-2'])
            ->pluck('id');
        $legal->requiredDocuments()->whereIn('common_required_document_id', $landRecordIds)
            ->update(['requirement_type' => 'any_one_required']);
        $beforeCount = $legal->requiredDocuments()->count();

        $migration = require database_path('migrations/2026_08_15_120000_scope_land_record_requirements_to_property_services.php');
        $migration->up();

        $this->assertFalse($legal->fresh()->requires_property_documents);
        $this->assertSame($beforeCount, $legal->requiredDocuments()->count());
        $this->assertSame(0, $legal->requiredDocuments()->whereIn('common_required_document_id', $landRecordIds)->where('requirement_type', 'any_one_required')->count());
        $this->assertSame(3, $legal->requiredDocuments()->whereIn('common_required_document_id', $landRecordIds)->where('requirement_type', 'optional')->count());
        $this->assertSame(3, $property->requiredDocuments()->whereIn('common_required_document_id', $landRecordIds)->where('requirement_type', 'any_one_required')->count());
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

    private function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }
}
