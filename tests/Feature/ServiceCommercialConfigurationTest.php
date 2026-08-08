<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\RequestWorkflowService;
use Database\Seeders\ServiceCommercialConfigurationSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\WorkScopeItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceCommercialConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED = [
        'sale-deed' => [1999, 4, ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'relinquishment-deed' => [1199, 4, ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'partition-deed' => [1399, 5, ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'rent-agreement' => [499, 3, ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'power-of-attorney' => [899, 4, ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'gift-deed' => [2199, 4, ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'mortgage' => [999, 4, ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'mortgage-release' => [999, 4, ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'banakhat-agreement-to-sell' => [499, 3, ['initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft']],
        'property-title-verification' => [2499, 7, ['initial-review', 'property-record-check', 'title-verification', 'final-title-report']],
        'legal-consulting' => [1499, 4, ['initial-review', 'information-document-review', 'guidance']],
        'sub-registrar-office-token-booking' => [399, 3, ['initial-review', 'garvi-portal-work', 'garvi-token-booking']],
        'other' => [1499, 5, []],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ServiceSeeder::class, WorkScopeItemSeeder::class]);
    }

    public function test_owner_approved_commercial_values_and_default_scopes_are_applied_idempotently(): void
    {
        $this->seed(ServiceCommercialConfigurationSeeder::class);
        $this->seed(ServiceCommercialConfigurationSeeder::class);

        $this->assertSame(13, Service::query()->count());
        foreach (self::EXPECTED as $slug => [$fee, $days, $scopeCodes]) {
            $service = Service::query()->where('slug', $slug)->sole();
            $this->assertSame((float) $fee, (float) $service->service_fee, $slug.' fee');
            $this->assertSame(18.0, (float) $service->gst_rate, $slug.' GST');
            $this->assertSame($days, $service->estimated_days, $slug.' days');
            $this->assertSame($days.' Working Days', $service->processing_time_label, $slug.' time label');
            $this->assertSame(100, $service->advance_percentage, $slug.' advance');
            $this->assertSame(0.0, (float) $service->government_charges, $slug.' government charges');
            $this->assertSame(
                $scopeCodes,
                $service->defaultWorkScopes()->pluck('normalized_name')->all(),
                $slug.' default scopes',
            );
        }

        $this->assertDatabaseCount('service_government_charges', 0);
        $this->assertDatabaseCount('service_work_scope_defaults', 46);
    }

    public function test_drafting_defaults_exclude_optional_registration_and_garvi_scopes(): void
    {
        $this->seed(ServiceCommercialConfigurationSeeder::class);
        $optional = [
            'stamp-registration-fee-assistance',
            'garvi-portal-work',
            'garvi-token-booking',
            'registration-preparation',
            'other-work',
        ];

        foreach (array_slice(array_keys(self::EXPECTED), 0, 9) as $slug) {
            $defaults = Service::query()->where('slug', $slug)->sole()->defaultWorkScopes()->pluck('normalized_name');
            $this->assertEmpty($defaults->intersect($optional), $slug.' optional defaults');
        }
        $this->assertCount(0, Service::query()->where('slug', 'other')->sole()->defaultWorkScopes);
    }

    public function test_configuration_does_not_change_required_documents_or_historical_pricing_and_payments(): void
    {
        $sale = Service::query()->where('slug', 'sale-deed')->sole();
        $document = $sale->requiredDocuments()->create([
            'name_en' => 'Preserved Property Record',
            'name_gu' => 'Preserved Property Record',
            'is_mandatory' => false,
            'is_active' => true,
            'allowed_file_types' => ['pdf'],
            'max_upload_size_kb' => 2048,
        ]);
        $request = CustomerRequest::query()->create([
            'reference_no' => 'SC/2026/990001',
            'service_id' => $sale->id,
            'name' => 'Historical Customer',
            'mobile' => '9999999999',
            'status' => 'payment_received',
            'payment_status' => 'received',
            'amount_due' => 4130,
            'amount_paid' => 4130,
        ]);
        $snapshot = $request->requestServices()->create([
            'service_id' => $sale->id,
            'professional_fee' => 3500,
            'original_professional_fee' => 3500,
            'gst_rate' => 18,
            'gst_amount' => 630,
            'final_total' => 4130,
            'pricing_locked_at' => now(),
            'status' => 'approved',
        ]);
        $billing = $request->billing()->create([
            'total_original_professional_fee' => 3500,
            'discount_type' => 'none',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_professional_fee' => 3500,
            'gst_rate' => 18,
            'gst_amount' => 630,
            'government_charges_total' => 0,
            'grand_total' => 4130,
            'pricing_locked_at' => now(),
        ]);
        $payment = $request->payments()->create([
            'amount' => 4130,
            'payment_status' => 'received',
            'payment_method' => 'upi',
            'received_at' => now(),
        ]);

        $beforeDocuments = DB::table('service_required_documents')->orderBy('id')->get()->toJson();
        $snapshotFields = ['professional_fee', 'original_professional_fee', 'gst_rate', 'gst_amount', 'final_total', 'pricing_locked_at'];
        $beforeSnapshot = collect($snapshotFields)->mapWithKeys(fn (string $field) => [$field => $snapshot->getRawOriginal($field)])->all();
        $beforeBilling = (array) DB::table('request_billings')->where('id', $billing->id)->sole();
        $beforePayment = (array) DB::table('request_payments')->where('id', $payment->id)->sole();
        $this->seed(ServiceCommercialConfigurationSeeder::class);

        $this->assertSame($beforeDocuments, DB::table('service_required_documents')->orderBy('id')->get()->toJson());
        $freshSnapshot = $snapshot->fresh();
        $this->assertSame($beforeSnapshot, collect($snapshotFields)->mapWithKeys(fn (string $field) => [$field => $freshSnapshot->getRawOriginal($field)])->all());
        $this->assertSame($beforeBilling, (array) DB::table('request_billings')->where('id', $billing->id)->sole());
        $this->assertSame($beforePayment, (array) DB::table('request_payments')->where('id', $payment->id)->sole());
        $this->assertDatabaseHas('service_required_documents', ['id' => $document->id]);
    }

    public function test_new_request_snapshots_current_fee_gst_days_and_selected_defaults_only(): void
    {
        $this->seed(ServiceCommercialConfigurationSeeder::class);
        $service = Service::query()->where('slug', 'sale-deed')->sole();
        $request = app(RequestWorkflowService::class)->submit([
            'service_id' => $service->id,
            'name' => 'New Customer',
            'mobile' => '9999999999',
        ], []);
        $selected = $request->requestServices()->sole();

        $this->assertSame(1999.0, (float) $selected->professional_fee);
        $this->assertSame(1999.0, (float) $selected->original_professional_fee);
        $this->assertSame(18.0, (float) $selected->gst_rate);
        $this->assertSame(4, $selected->estimated_days);

        app(RequestWorkflowService::class)->decideService(
            $request,
            $selected,
            ['decision' => 'approved'],
            User::factory()->create(),
        );
        $this->assertSame(
            self::EXPECTED['sale-deed'][2],
            $selected->workScopes()->orderBy('display_order')->pluck('name_en_snapshot')
                ->map(fn (string $name) => $service->defaultWorkScopes()->where('name_en', $name)->value('normalized_name'))
                ->all(),
        );
    }

    public function test_public_estimates_include_the_approved_non_guarantee_explanation(): void
    {
        $this->seed(ServiceCommercialConfigurationSeeder::class);

        $this->get(route('request.create'))->assertOk()->assertSee(
            'દર્શાવેલ સમય જરૂરી માહિતી અને દસ્તાવેજો ઉપલબ્ધ થયા પછીનો અંદાજિત કાર્ય સમય છે. કેસની પરિસ્થિતિ મુજબ સમયમાં ફેરફાર થઈ શકે છે.',
        );
    }
}
