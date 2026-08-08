@extends('layouts.app')
@section('title', 'જરૂરી દસ્તાવેજો | Sai Consulting')
@section('description', 'Sai Consultingની સક્રિય સેવાઓ માટે હાલમાં ગોઠવાયેલા જરૂરી અને વૈકલ્પિક Property Documents જુઓ.')
@section('canonical', \App\Support\Seo::route('required-documents'))
@section('content')
<x-public-page-heading title-gu="જરૂરી દસ્તાવેજો" title-en="Required Documents" intro="અરજી કરતા પહેલાં તમારી સેવા માટે જરૂરી Property Documents તપાસો." />
<section class="section-space information-page-body"><div class="container information-content-wide">
    <div class="customer-guidance-card"><x-public-icon name="document" size="24" /><p class="mb-0">નીચે દર્શાવેલ દસ્તાવેજોમાંથી તમારી પાસે હાલમાં ઉપલબ્ધ દસ્તાવેજો અપલોડ કરો. તમામ દસ્તાવેજો અપલોડ કરવાનું ફરજિયાત નથી. અરજીની ચકાસણી બાદ વધુ દસ્તાવેજોની જરૂર જણાશે તો Sai Consulting દ્વારા WhatsApp અથવા તમે આપેલા મોબાઇલ નંબર પર સંપર્ક કરવામાં આવશે.</p></div>
    <div class="identity-document-warning mt-3"><i class="bi bi-shield-exclamation" aria-hidden="true"></i><div><strong>ઓળખ/KYC દસ્તાવેજો અપલોડ કરશો નહીં</strong><span>જાહેર વેબસાઇટ Aadhaar, PAN, Passport, Voter ID, Bank Documents અથવા અન્ય ઓળખ/KYC પુરાવા સ્વીકારતી નથી.</span></div></div>
    <div class="accordion information-accordion mt-5" id="serviceDocumentsAccordion">
        @forelse($services as $index => $service)
            <div class="accordion-item">
                <h2 class="accordion-header" id="serviceDocumentsHeading{{ $service->id }}"><button class="accordion-button {{ $index ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#serviceDocuments{{ $service->id }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="serviceDocuments{{ $service->id }}"><span><strong>{{ $service->name_gu }}</strong><small>{{ $service->name_en }}</small></span></button></h2>
                <div id="serviceDocuments{{ $service->id }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="serviceDocumentsHeading{{ $service->id }}" data-bs-parent="#serviceDocumentsAccordion"><div class="accordion-body">
                    @if($service->activeRequiredDocuments->isNotEmpty())
                        @include('frontend.partials.required-document-groups', ['documents' => $service->activeRequiredDocuments])
                    @else
                        <p class="information-empty mb-0">આ સેવા માટે દસ્તાવેજોની યાદી હાલમાં ઉપલબ્ધ નથી.</p>
                    @endif
                </div></div>
            </div>
        @empty
            <div class="information-empty">સેવાઓ અને જરૂરી દસ્તાવેજોની માહિતી હાલમાં ઉપલબ્ધ નથી.</div>
        @endforelse
    </div>
    <div class="information-page-actions"><a href="{{ route('request.create') }}" class="btn btn-primary btn-lg rounded-pill px-4">ઓનલાઇન અરજી કરો</a><a href="{{ route('request.track') }}" class="btn btn-outline-primary btn-lg rounded-pill px-4">અરજી ટ્રેક કરો</a></div>
</div></section>
@endsection
