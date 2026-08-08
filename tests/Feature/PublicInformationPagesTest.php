<?php

namespace Tests\Feature;

use App\Models\CommonRequiredDocument;
use App\Models\OfficeTiming;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInformationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_documents_page_uses_active_deduplicated_configuration_and_safe_labels(): void
    {
        $common = CommonRequiredDocument::query()->create([
            'name_en' => 'Property Card', 'name_gu' => 'પ્રોપર્ટી કાર્ડ', 'normalized_name' => 'property card',
            'is_active' => true, 'is_common' => true,
        ]);
        $active = Service::query()->create(['name_en' => 'Sale Deed', 'name_gu' => 'વેચાણ દસ્તાવેજ', 'slug' => 'sale-deed', 'is_active' => true, 'available_online' => true, 'service_fee' => 3500]);
        $inactive = Service::query()->create(['name_en' => 'Hidden Service', 'name_gu' => 'છુપાયેલી સેવા', 'slug' => 'hidden-service', 'is_active' => false]);
        $active->requiredDocuments()->where('common_required_document_id', $common->id)->update(['is_mandatory' => true]);
        $active->requiredDocuments()->create(['name_en' => 'Previous Deed', 'name_gu' => 'અગાઉનો દસ્તાવેજ', 'is_mandatory' => false, 'is_active' => true, 'sort_order' => 2]);

        $response = $this->get(route('required-documents'))->assertOk()
            ->assertSee($active->name_gu)->assertSee($active->name_en)
            ->assertDontSee($inactive->name_en)
            ->assertSee('Required')->assertSee('Optional')
            ->assertSee('Aadhaar')->assertSee('PAN')->assertSee('Passport')->assertSee('Voter ID')->assertSee('Bank Documents')
            ->assertDontSee('₹')->assertDontSee('Professional Fee')->assertDontSee('tel:');

        $this->assertSame(1, substr_count($response->getContent(), 'Property Card'));
        $this->assertSame(1, substr_count($response->getContent(), 'Previous Deed'));
        $this->assertSingleH1($response->getContent());
    }

    public function test_required_documents_page_has_gujarati_empty_fallback(): void
    {
        Service::query()->create(['name_en' => 'Empty Service', 'name_gu' => 'ખાલી સેવા', 'slug' => 'empty-service', 'is_active' => true]);

        $this->get(route('required-documents'))->assertOk()
            ->assertSee('આ સેવા માટે દસ્તાવેજોની યાદી હાલમાં ઉપલબ્ધ નથી.');
    }

    public function test_about_page_has_approved_scope_and_omits_rejected_sections(): void
    {
        $response = $this->get(route('about'))->assertOk()
            ->assertSee('Sai Consulting વિશે')
            ->assertSee('વકીલાતની પ્રેક્ટિસ કરવામાં આવતી નથી')
            ->assertDontSee('અમારી કાર્યપદ્ધતિ')
            ->assertDontSee('પારદર્શક પ્રક્રિયા')
            ->assertDontSee('Professional Fee');

        $this->assertSingleH1($response->getContent());
    }

    public function test_faq_page_renders_approved_accessible_questions_without_payment_credentials(): void
    {
        $response = $this->get(route('faq'))->assertOk()
            ->assertSee('Sai Consulting કઈ સેવાઓ આપે છે?')
            ->assertSee('શું Aadhaar, PAN અથવા અન્ય KYC દસ્તાવેજો વેબસાઇટ પર અપલોડ કરવા પડે?')
            ->assertSee('Government Charges અને Professional Feeમાં શું તફાવત છે?')
            ->assertSee('aria-expanded=', false)
            ->assertSee('aria-controls=', false)
            ->assertDontSee('account_number')
            ->assertDontSee('ifsc')
            ->assertDontSee('tel:');

        $this->assertSame(15, substr_count($response->getContent(), 'class="accordion-item"'));
        $this->assertSingleH1($response->getContent());
    }

    public function test_contact_page_uses_only_configured_public_values_and_office_timings(): void
    {
        $this->setting('website.name', 'Sai Consulting');
        $this->setting('contact.email', 'office@sai.test');
        $this->setting('contact.whatsapp_number', '919999999999');
        $this->setting('contact.phone', '07911111111', false);
        $this->setting('office.city', 'Chanasma');
        OfficeTiming::query()->create(['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00', 'is_closed' => false]);

        $response = $this->get(route('contact'))->assertOk()
            ->assertSee('office@sai.test')->assertSee('919999999999')->assertSee('Chanasma')
            ->assertSee('સોમવાર')->assertSee('9:00 AM')
            ->assertDontSee('07911111111')->assertDontSee('tel:');

        $this->assertSingleH1($response->getContent());
    }

    public function test_contact_page_hides_missing_values_and_shows_timing_fallback(): void
    {
        $this->get(route('contact'))->assertOk()
            ->assertSee('ઓફિસનો કાર્ય સમય હાલમાં ઉપલબ્ધ નથી.')
            ->assertDontSee('mailto:')->assertDontSee('wa.me')->assertDontSee('tel:');
    }

    public function test_all_legal_pages_use_one_template_and_required_customer_safe_content(): void
    {
        $expectations = [
            'privacy-policy' => ['ગોપનીયતા નીતિ', 'Aadhaar', 'સંપૂર્ણ સુરક્ષાની ખાતરી આપી શકાતી નથી'],
            'terms' => ['નિયમો અને શરતો', 'વકીલાતની પ્રેક્ટિસ કરવામાં આવતી નથી', 'Government Charges'],
            'refund-policy' => ['રિફંડ નીતિ', 'Government Charges', 'Professional Feeથી અલગ'],
            'disclaimer' => ['અસ્વીકરણ', 'સ્પષ્ટ Title', 'Registration outcome'],
        ];

        foreach ($expectations as $route => $texts) {
            $response = $this->get(route($route))->assertOk()->assertDontSee('tel:');
            foreach ($texts as $text) {
                $response->assertSee($text);
            }
            $this->assertSingleH1($response->getContent());
            $this->assertStringContainsString('class="legal-section"', $response->getContent());
        }
    }

    public function test_shared_header_footer_link_to_every_information_page(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach (['required-documents', 'about', 'faq', 'contact', 'privacy-policy', 'terms', 'refund-policy', 'disclaimer'] as $route) {
            $response->assertSee(route($route), false);
        }
    }

    private function assertSingleH1(string $content): void
    {
        $this->assertSame(1, substr_count($content, '<h1'));
    }

    private function setting(string $key, string $value, bool $public = true): void
    {
        Setting::query()->create([
            'setting_key' => $key,
            'setting_value' => $value,
            'value_type' => 'string',
            'setting_group' => str($key)->before('.')->toString(),
            'is_public' => $public,
        ]);
    }
}
