<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Support\Seo;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect(config('seo.indexable_routes'))
            ->map(fn (string $route): string => Seo::route($route));

        $serviceUrls = Service::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('slug')
            ->map(fn (string $slug): string => Seo::route('services.show', $slug));

        return response()
            ->view('seo.sitemap', ['urls' => $urls->merge($serviceUrls)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /request',
            '',
            'Sitemap: '.Seo::route('sitemap'),
            '',
        ]);

        return response($content)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
