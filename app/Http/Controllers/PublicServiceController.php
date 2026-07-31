<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchServicesRequest;
use App\Services\HomepageService;
use App\Services\PublicServiceCatalogService;
use Illuminate\Contracts\View\View;

class PublicServiceController extends Controller
{
    public function __construct(
        private readonly PublicServiceCatalogService $catalog,
        private readonly HomepageService $homepage,
    ) {}

    public function index(SearchServicesRequest $request): View
    {
        return view('frontend.services.index', [
            'services' => $this->catalog->paginate($request->validated('q')),
            'search' => $request->validated('q'),
        ]);
    }

    public function show(string $slug): View
    {
        $service = $this->catalog->findActiveBySlug($slug);
        $site = $this->homepage->publicSiteData();

        return view('frontend.services.show', [
            'service' => $service,
            'relatedServices' => $this->catalog->relatedTo($service),
            'whatsappUrl' => $site['whatsappUrl'],
        ]);
    }
}
