<?php

namespace Tests\Feature;

use App\Services\HomepageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicResponseHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_admin_auth_responses_have_conservative_security_headers(): void
    {
        foreach ([route('home'), route('login')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        }
    }

    public function test_public_site_data_is_reused_only_within_the_current_request_scope(): void
    {
        $homepage = app(HomepageService::class);
        $this->assertSame($homepage, app(HomepageService::class));

        DB::enableQueryLog();
        $first = $homepage->publicSiteData();
        $queryCount = count(DB::getQueryLog());
        $second = $homepage->publicSiteData();

        $this->assertSame($first, $second);
        $this->assertGreaterThan(0, $queryCount);
        $this->assertCount($queryCount, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
