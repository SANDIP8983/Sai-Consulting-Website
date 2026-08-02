<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Pdf\PdfCompanyContext;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsBrandingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
    }

    public function test_settings_management_requires_admin_authentication(): void
    {
        $this->get(route('admin.settings.company-branding'))->assertRedirect(route('login'));
        $this->put(route('admin.settings.company-branding.update'), [])->assertRedirect(route('login'));
        $this->get(route('admin.settings.branding.asset', 'stamp'))->assertRedirect(route('login'));
    }

    public function test_admin_can_update_company_information_without_duplicate_keys_and_cache_is_invalidated(): void
    {
        $admin = User::factory()->create();
        $service = app(SettingsService::class);
        $this->assertNull($service->companyBrandingSettings()['business_name']);

        $payload = $this->companyPayload();
        $this->actingAs($admin)->put(route('admin.settings.company-branding.update'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('admin.settings.company-branding.update'), [...$payload, 'tagline' => 'Updated Tagline'])->assertSessionHasNoErrors();

        $this->assertSame('Sai Consulting', $service->companyBrandingSettings()['business_name']);
        $this->assertSame('Updated Tagline', $service->companyBrandingSettings()['tagline']);
        $this->assertSame(1, Setting::query()->where('setting_key', 'website.name')->count());
        $this->assertSame(1, Setting::query()->where('setting_key', 'business.tagline')->count());
    }

    public function test_branding_uploads_use_unique_private_paths_and_can_be_detached_without_deleting_files(): void
    {
        $admin = User::factory()->create();
        $uploads = [];
        foreach (['primary_logo', 'dark_logo', 'favicon', 'pdf_logo', 'stamp', 'signature'] as $field) {
            $uploads[$field] = UploadedFile::fake()->image($field.'.png', 300, 150);
        }
        $this->actingAs($admin)->put(route('admin.settings.company-branding.update'), [...$this->companyPayload(), ...$uploads])->assertSessionHasNoErrors();

        $paths = Setting::query()->where('setting_key', 'like', 'branding.%_path')->pluck('setting_value', 'setting_key');
        $this->assertCount(6, $paths);
        $this->assertCount(6, $paths->unique());
        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $stampPath = $paths['branding.stamp_path'];
        $this->actingAs($admin)->put(route('admin.settings.company-branding.update'), [...$this->companyPayload(), 'remove_stamp' => 1])->assertSessionHasNoErrors();
        $this->assertNull(Setting::query()->where('setting_key', 'branding.stamp_path')->value('setting_value'));
        Storage::disk('local')->assertExists($stampPath);
    }

    public function test_invalid_branding_files_and_company_values_are_rejected(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->put(route('admin.settings.company-branding.update'), [
            ...$this->companyPayload(), 'email' => 'invalid', 'gstin' => 'INVALID',
            'primary_logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            'stamp' => UploadedFile::fake()->image('stamp.png')->size(2050),
        ])->assertSessionHasErrors(['email', 'gstin', 'primary_logo', 'stamp']);
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_public_brand_assets_are_allowlisted_and_private_assets_require_authentication(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->put(route('admin.settings.company-branding.update'), [
            ...$this->companyPayload(),
            'primary_logo' => UploadedFile::fake()->image('logo.png'),
            'stamp' => UploadedFile::fake()->image('stamp.png'),
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $this->get(route('branding.asset', 'primary-logo'))->assertOk()->assertHeader('x-content-type-options', 'nosniff');
        $this->get(route('branding.asset', 'stamp'))->assertNotFound();
        $this->get(route('admin.settings.branding.asset', 'stamp'))->assertRedirect(route('login'));
        $this->actingAs($admin)->get(route('admin.settings.branding.asset', 'stamp'))->assertOk();
    }

    public function test_company_branding_integrates_with_public_header_and_pdf_context_without_exposing_gstin_publicly(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->put(route('admin.settings.company-branding.update'), [
            ...$this->companyPayload(), 'primary_logo' => UploadedFile::fake()->image('logo.png'), 'pdf_logo' => UploadedFile::fake()->image('pdf.png'),
            'stamp' => UploadedFile::fake()->image('stamp.png'), 'signature' => UploadedFile::fake()->image('signature.png'),
        ])->assertSessionHasNoErrors();

        $this->get(route('home'))->assertOk()->assertSee('Configured Tagline')->assertSee(route('branding.asset', 'primary-logo'), false)->assertDontSee('24ABCDE1234F1Z5');
        $company = app(PdfCompanyContext::class)->get();
        $this->assertSame('Sai Consulting', $company['name']);
        $this->assertSame('Configured Tagline', $company['tagline']);
        $this->assertSame('24ABCDE1234F1Z5', $company['gst_number']);
        $this->assertStringStartsWith('data:image/', $company['logo']);
        $this->assertStringStartsWith('data:image/', $company['stamp']);
        $this->assertStringStartsWith('data:image/', $company['signature']);
    }

    private function companyPayload(): array
    {
        return ['business_name' => 'Sai Consulting', 'tagline' => 'Configured Tagline', 'address' => 'Patan, Gujarat', 'mobile' => '9876543210', 'whatsapp' => '9876543210', 'email' => 'office@sai.test', 'website_url' => 'https://sai.test', 'gstin' => '24ABCDE1234F1Z5'];
    }
}
