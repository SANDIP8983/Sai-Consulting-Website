<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\HomepageService;
use Illuminate\Contracts\View\View;

class PublicInformationController extends Controller
{
    public function requiredDocuments(): View
    {
        $services = Service::query()
            ->where('is_active', true)
            ->with('activeRequiredDocuments')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->map(function (Service $service): Service {
                $documents = $service->activeRequiredDocuments
                    ->unique(fn ($document): string => $document->common_required_document_id
                        ? 'common-'.$document->common_required_document_id
                        : 'name-'.str($document->name_en)->trim()->lower())
                    ->values();

                return $service->setRelation('activeRequiredDocuments', $documents);
            });

        return view('frontend.information.required-documents', compact('services'));
    }

    public function about(HomepageService $homepage): View
    {
        return view('frontend.information.about', ['pageData' => $homepage->publicSiteData()]);
    }

    public function faq(HomepageService $homepage): View
    {
        return view('frontend.information.faq', ['pageData' => $homepage->publicSiteData()]);
    }

    public function contact(HomepageService $homepage): View
    {
        return view('frontend.information.contact', ['pageData' => $homepage->publicSiteData()]);
    }

    public function privacyPolicy(HomepageService $homepage): View
    {
        return $this->legalPage('privacy-policy', $homepage);
    }

    public function terms(HomepageService $homepage): View
    {
        return $this->legalPage('terms', $homepage);
    }

    public function refundPolicy(HomepageService $homepage): View
    {
        return $this->legalPage('refund-policy', $homepage);
    }

    public function disclaimer(HomepageService $homepage): View
    {
        return $this->legalPage('disclaimer', $homepage);
    }

    private function legalPage(string $page, HomepageService $homepage): View
    {
        return view('frontend.information.legal', [
            'legalPage' => config("public-information-pages.legal.{$page}"),
            'pageData' => $homepage->publicSiteData(),
        ]);
    }
}
