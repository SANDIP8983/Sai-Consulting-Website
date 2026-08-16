<?php

namespace Tests\Feature;

use App\Enums\NotificationMilestone;
use App\Models\CommonRequiredDocument;
use App\Models\CustomerRequest;
use App\Models\OfficeTiming;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkScopeItem;
use Database\Seeders\ProductionBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'TestBootstrapOnly!42';

    private const CURRENT_SCOPES = [
        'initial-review', 'draft-preparation', 'draft-check-correction', 'final-draft',
        'property-record-check', 'title-verification', 'final-title-report',
        'stamp-registration-fee-assistance', 'garvi-portal-work', 'garvi-token-booking',
        'registration-preparation', 'other-work', 'information-document-review', 'guidance',
    ];

    private const BASE_SETTING_KEYS = [
        'website.name', 'website.status', 'website.maintenance_message',
        'business.tagline', 'business.website_url', 'business.gstin',
        'office.name', 'office.address_line_1', 'office.address_line_2', 'office.city',
        'office.state', 'office.postal_code', 'office.timezone',
        'contact.phone', 'contact.whatsapp_number', 'contact.email',
        'branding.primary_logo_path', 'branding.dark_logo_path', 'branding.favicon_path',
        'branding.pdf_logo_path', 'branding.stamp_path', 'branding.signature_path',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'admin.name' => 'Bootstrap Administrator',
            'admin.username' => 'admin',
            'admin.mobile' => '9876543206',
            'admin.email' => null,
            'admin.password' => self::PASSWORD,
        ]);
    }

    public function test_fresh_production_bootstrap_contains_only_approved_master_data_and_is_idempotent(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);
        $configured = Service::query()->firstOrFail()->requiredDocuments()->firstOrFail();
        $configured->update(['requirement_type' => 'required', 'is_mandatory' => true]);
        $this->seed(ProductionBootstrapSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(1, User::query()->where('role', 'super_admin')->where('is_active', true)->count());
        $this->assertDatabaseCount('services', 15);
        $this->assertSame(15, Service::query()->where('gst_rate', 18)->where('advance_percentage', 100)->count());
        $this->assertDatabaseCount('common_required_documents', 18);
        $this->assertDatabaseCount('service_required_documents', 270);
        $this->assertSame(18, CommonRequiredDocument::query()->whereNotNull('code')->distinct()->count('code'));
        $this->assertSame(0, CommonRequiredDocument::query()->whereNull('code')->count());
        $this->assertSame('required', $configured->fresh()->requirement_type);
        $this->assertTrue($configured->fresh()->is_mandatory);
        foreach (Service::query()->get() as $service) {
            $this->assertSame(18, $service->requiredDocuments()->count(), $service->slug.' document mappings');
        }
        $this->assertDatabaseCount('work_scope_items', 14);
        $this->assertEqualsCanonicalizing(self::CURRENT_SCOPES, WorkScopeItem::query()->pluck('normalized_name')->all());
        $this->assertDatabaseCount('service_work_scope_defaults', 54);
        $this->assertDatabaseCount('settings', 55);
        $this->assertSame(32, Setting::query()->where('setting_group', 'customer_notifications')->count());
        $this->assertSame(1, Setting::query()->where('setting_group', 'admin_notifications')->count());
        $this->assertSame(22, Setting::query()->whereNotIn('setting_group', ['customer_notifications', 'admin_notifications'])->count());
        $this->assertSame(0, Setting::query()->whereIn('setting_group', ['customer_notifications', 'admin_notifications'])->where('setting_value', '!=', '0')->count());
        $this->assertEqualsCanonicalizing(self::BASE_SETTING_KEYS, Setting::query()->whereNotIn('setting_group', ['customer_notifications', 'admin_notifications'])->pluck('setting_key')->all());
        $expectedNotificationKeys = collect(NotificationMilestone::cases())->flatMap(
            fn (NotificationMilestone $milestone): array => ["notifications.{$milestone->value}.email", "notifications.{$milestone->value}.whatsapp"],
        )->all();
        $this->assertEqualsCanonicalizing($expectedNotificationKeys, Setting::query()->where('setting_group', 'customer_notifications')->pluck('setting_key')->all());
        $this->assertDatabaseCount('office_timings', 7);
        $this->assertSame(range(0, 6), OfficeTiming::query()->orderBy('day_of_week')->pluck('day_of_week')->all());
        $this->assertSame(7, OfficeTiming::query()->where('is_closed', true)->whereNull('opens_at')->whereNull('closes_at')->count());
        $this->assertDatabaseCount('service_government_charges', 0);
        $this->assertDatabaseCount('holidays', 0);
        $this->assertDatabaseCount('file_number_sequences', 0);

        foreach ($this->transactionalTables() as $table) {
            $this->assertDatabaseCount($table, 0);
        }

        $settings = Setting::query()->pluck('setting_value', 'setting_key');
        $this->assertSame('Sai Consulting', $settings['website.name']);
        $this->assertSame('maintenance', $settings['website.status']);
        $this->assertSame('Asia/Kolkata', $settings['office.timezone']);
        foreach (['contact.phone', 'contact.whatsapp_number', 'contact.email', 'business.gstin', 'branding.signature_path'] as $key) {
            $this->assertNull($settings[$key]);
        }
        $this->assertFalse($settings->contains(self::PASSWORD));
        $this->assertFalse($settings->keys()->contains(fn (string $key): bool => str_contains($key, 'password') || str_contains($key, 'secret')));
    }

    public function test_bootstrap_rerun_preserves_owner_configuration_and_historical_business_data(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);
        Setting::query()->where('setting_key', 'contact.email')->update(['setting_value' => 'owner@example.test']);
        Setting::query()->where('setting_key', 'notifications.completed.email')->update(['setting_value' => '1']);
        Setting::query()->where('setting_key', 'notifications.admin_new_online_request.email')->update(['setting_value' => '1']);
        OfficeTiming::query()->where('day_of_week', 1)->update(['opens_at' => '09:30', 'closes_at' => '17:30', 'is_closed' => false]);
        $customScope = WorkScopeItem::query()->create(['name_en' => 'Owner Custom Scope', 'name_gu' => 'Owner Custom Scope', 'normalized_name' => 'owner-custom-scope', 'is_active' => true]);
        $service = Service::query()->where('slug', 'sale-deed')->sole();
        $request = CustomerRequest::query()->create(['reference_no' => 'SC/2026/990001', 'service_id' => $service->id, 'name' => 'Preserved Customer', 'mobile' => '9999999999']);
        $payment = $request->payments()->create(['amount' => 100, 'payment_method' => 'cash', 'received_at' => now()]);
        DB::table('file_number_sequences')->insert(['year' => 2026, 'last_number' => 91, 'created_at' => now(), 'updated_at' => now()]);

        $this->seed(ProductionBootstrapSeeder::class);

        $this->assertSame('owner@example.test', Setting::query()->where('setting_key', 'contact.email')->value('setting_value'));
        $this->assertSame('1', Setting::query()->where('setting_key', 'notifications.completed.email')->value('setting_value'));
        $this->assertSame('1', Setting::query()->where('setting_key', 'notifications.admin_new_online_request.email')->value('setting_value'));
        $monday = OfficeTiming::query()->where('day_of_week', 1)->sole();
        $this->assertSame('09:30', substr((string) $monday->opens_at, 0, 5));
        $this->assertSame('17:30', substr((string) $monday->closes_at, 0, 5));
        $this->assertFalse($monday->is_closed);
        $this->assertDatabaseHas('work_scope_items', ['id' => $customScope->id, 'normalized_name' => 'owner-custom-scope']);
        $this->assertDatabaseHas('requests', ['id' => $request->id, 'reference_no' => 'SC/2026/990001']);
        $this->assertDatabaseHas('request_payments', ['id' => $payment->id, 'amount' => 100]);
        $this->assertDatabaseHas('file_number_sequences', ['year' => 2026, 'last_number' => 91]);
    }

    /** @return array<int, string> */
    private function transactionalTables(): array
    {
        return [
            'requests', 'request_services', 'request_documents', 'request_status_histories',
            'request_payments', 'request_processing_details', 'request_processing_histories',
            'request_service_work_scopes', 'request_service_work_scope_histories',
            'request_service_approval_histories', 'request_case_action_histories',
            'request_billings', 'request_billing_government_charges', 'request_billing_histories',
            'request_dispatches', 'request_dispatch_proofs', 'request_dispatch_histories',
            'request_assignment_histories', 'request_contact_change_histories',
            'customer_notification_events', 'customer_notification_deliveries', 'appointments', 'blogs',
        ];
    }
}
