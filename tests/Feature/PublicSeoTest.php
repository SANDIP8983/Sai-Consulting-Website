<?php

namespace Tests\Feature;

use App\Models\Service;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://saiconsultingchanasma.in']);
        $this->seed(ServiceSeeder::class);
    }

    public function test_indexable_pages_have_page_metadata_and_one_configured_canonical(): void
    {
        $pages = [
            'home' => 'Sai Consulting | દસ્તાવેજીકરણ અને મિલકત માર્ગદર્શન',
            'services.index' => 'સેવાઓ | Sai Consulting',
            'required-documents' => 'જરૂરી દસ્તાવેજો | Sai Consulting',
            'about' => 'અમારા વિશે | Sai Consulting',
            'faq' => 'વારંવાર પૂછાતા પ્રશ્નો | Sai Consulting',
            'contact' => 'સંપર્ક કરો | Sai Consulting',
            'privacy-policy' => 'ગોપનીયતા નીતિ | Sai Consulting',
            'terms' => 'નિયમો અને શરતો | Sai Consulting',
            'refund-policy' => 'રિફંડ નીતિ | Sai Consulting',
            'disclaimer' => 'અસ્વીકરણ | Sai Consulting',
        ];

        foreach ($pages as $routeName => $title) {
            $response = $this->get(route($routeName));
            $response->assertOk()->assertSee("<title>{$title}</title>", false)
                ->assertSee('<meta name="description"', false)
                ->assertSee('<meta name="robots" content="index, follow">', false);

            $content = $response->getContent();
            $this->assertSame(1, substr_count($content, 'rel="canonical"'), $routeName);
            $this->assertStringContainsString('href="https://saiconsultingchanasma.in', $content);
            preg_match('/<link rel="canonical" href="([^"]+)">/', $content, $canonical);
            $this->assertStringNotContainsString('localhost', $canonical[1] ?? '');
            $this->assertSame(1, substr_count($content, '<h1'), $routeName);
        }
    }

    public function test_active_service_has_specific_metadata_and_inactive_service_is_not_public(): void
    {
        $active = Service::query()->where('is_active', true)->firstOrFail();
        $response = $this->get(route('services.show', $active->slug));

        $response->assertOk()
            ->assertSee('<title>'.$active->name_gu.' | Sai Consulting</title>', false)
            ->assertSee('https://saiconsultingchanasma.in/services/'.$active->slug, false);
        $this->assertSame(1, substr_count($response->getContent(), 'rel="canonical"'));
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));

        $inactive = Service::query()->create([
            'name_en' => 'Inactive SEO Service', 'name_gu' => 'નિષ્ક્રિય સેવા',
            'slug' => 'inactive-seo-service', 'is_active' => false, 'sort_order' => 999,
        ]);
        $this->get(route('services.show', $inactive->slug))->assertNotFound();
    }

    public function test_sitemap_is_valid_and_contains_only_indexable_public_routes_and_active_services(): void
    {
        $active = Service::query()->where('is_active', true)->firstOrFail();
        $inactive = Service::query()->create([
            'name_en' => 'Inactive SEO Service', 'name_gu' => 'નિષ્ક્રિય સેવા',
            'slug' => 'inactive-seo-service', 'is_active' => false, 'sort_order' => 999,
        ]);

        $response = $this->get(route('sitemap'))->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = $response->getContent();
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString("/services/{$active->slug}", $xml);
        $this->assertStringNotContainsString("/services/{$inactive->slug}", $xml);

        foreach (['/admin', '/login', '/request', '/track', '/payment', '/pdf'] as $excluded) {
            $this->assertStringNotContainsString('<loc>https://saiconsultingchanasma.in'.$excluded, $xml);
        }
    }

    public function test_workflow_pages_are_noindex_and_do_not_leak_query_data_into_metadata(): void
    {
        foreach (['request.create', 'request.track'] as $routeName) {
            $response = $this->get(route($routeName, [
                'reference_no' => 'PRIVATE-REFERENCE-900', 'mobile' => '9999999999',
            ]))->assertOk();
            $head = $this->extractHead($response->getContent());
            $this->assertStringContainsString('name="robots" content="noindex', $head);
            $this->assertStringNotContainsString('rel="canonical"', $head);
            $this->assertStringNotContainsString('PRIVATE-REFERENCE-900', $head);
            $this->assertStringNotContainsString('9999999999', $head);
        }

        $this->get(route('login'))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false);

        $this->withSession(['submitted_request' => [
            'reference_no' => 'PRIVATE-REFERENCE-901', 'services' => [],
            'status' => 'received', 'estimated_days' => null,
        ]])->get(route('request.success'))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false);
    }

    public function test_robots_references_configured_sitemap_without_blocking_public_pages(): void
    {
        $content = $this->get(route('robots'))->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('Sitemap: https://saiconsultingchanasma.in/sitemap.xml', $content);
        $this->assertStringContainsString('Disallow: /admin', $content);
        $this->assertStringContainsString('Disallow: /request', $content);
        foreach (['/', '/services', '/required-documents', '/about', '/faq', '/contact'] as $path) {
            $this->assertStringNotContainsString("Disallow: {$path}\n", $content);
        }
    }

    public function test_open_graph_and_conservative_organization_schema_are_safe(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();
        foreach (['og:title', 'og:description', 'og:type', 'og:url', 'og:site_name'] as $property) {
            $this->assertSame(1, substr_count($content, 'property="'.$property.'"'));
        }

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);
        $structuredData = implode('', $matches[1]);
        $this->assertNotSame('', $structuredData);
        $this->assertStringContainsString('"@type":"Organization"', $structuredData);
        $this->assertDoesNotMatchRegularExpression('/lawfirm|legalservice|advocate/i', $structuredData);
    }

    private function extractHead(string $html): string
    {
        preg_match('/<head>(.*?)<\/head>/s', $html, $matches);

        return $matches[1] ?? '';
    }
}
