@extends('layouts.app')
@section('title', 'સંપર્ક કરો | Sai Consulting')
@section('description', 'Sai Consultingનો configured WhatsApp, Email, office address અને office timing દ્વારા સંપર્ક કરો.')
@section('canonical', \App\Support\Seo::route('contact'))
@section('content')
<x-public-page-heading title-gu="સંપર્ક કરો" title-en="Contact Us" intro="જો તમને દસ્તાવેજો, મિલકતનું ટાઇટલ ચેકિંગ અથવા અમારી સેવાઓ વિશે વધુ માહિતી જોઈએ, તો નીચે દર્શાવેલ માધ્યમથી અમારો સંપર્ક કરી શકો છો." />
<section class="section-space information-page-body"><div class="container information-content-wide">
    <div class="contact-page-brand"><strong>{{ $pageData['businessName'] }}</strong><span>Trusted Documentation Partner</span></div>
    <div class="row g-4 mt-2">
        @if($pageData['whatsappUrl'])<div class="col-md-6"><a class="public-contact-card h-100" href="{{ $pageData['whatsappUrl'] }}" target="_blank" rel="noopener"><x-public-icon name="message" size="26" /><span><small>WhatsApp</small><strong>{{ $pageData['whatsappNumber'] }}</strong></span></a></div>@endif
        @if($pageData['email'])<div class="col-md-6"><a class="public-contact-card h-100" href="mailto:{{ $pageData['email'] }}"><x-public-icon name="mail" size="26" /><span><small>Email</small><strong>{{ $pageData['email'] }}</strong></span></a></div>@endif
        @if($pageData['address'])<div class="col-md-6"><div class="public-contact-card h-100"><x-public-icon name="location" size="26" /><span><small>Office Address</small><strong>{{ $pageData['address'] }}</strong></span></div></div>@endif
        <div class="col-md-6"><div class="public-contact-card h-100"><x-public-icon name="clock" size="26" /><span><small>Office Timings</small><strong>{{ $pageData['workingHoursLabel'] ? str($pageData['workingHoursLabel'])->after('Working Hours: ') : 'ઓફિસનો કાર્ય સમય હાલમાં ઉપલબ્ધ નથી.' }}</strong></span></div></div>
    </div>
    @if($pageData['timings']->isNotEmpty())<section class="information-section mt-4"><h2>અઠવાડિયાનો કાર્ય સમય</h2>@php($days = ['રવિવાર', 'સોમવાર', 'મંગળવાર', 'બુધવાર', 'ગુરુવાર', 'શુક્રવાર', 'શનિવાર'])<div class="hours-list">@foreach($pageData['timings'] as $timing)<div class="hours-row"><span>{{ $days[$timing->day_of_week] }}</span><strong>@if($timing->day_of_week === 0 || $timing->is_closed || !$timing->opens_at || !$timing->closes_at)બંધ @else{{ \Illuminate\Support\Carbon::parse($timing->opens_at)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($timing->closes_at)->format('g:i A') }}@endif</strong></div>@endforeach</div></section>@endif
    @if($pageData['holidayNotice'])<div class="holiday-notice mt-4"><strong>આગામી રજા</strong><span>{{ $pageData['holidayNotice']['title'] }} · {{ $pageData['holidayNotice']['date'] }}</span>@if($pageData['holidayNotice']['description'])<small>{{ $pageData['holidayNotice']['description'] }}</small>@endif</div>@endif
    <p class="contact-coordination-note">મુલાકાત પહેલાં શક્ય હોય ત્યાં સુધી WhatsApp દ્વારા સમય નક્કી કરવાથી વધુ સુવ્યવસ્થિત સેવા આપી શકાય.</p>
    <section class="information-section"><h2>ઓનલાઇન સેવાઓ</h2><div class="online-service-links"><a href="{{ route('request.create') }}">ઓનલાઇન અરજી</a><a href="{{ route('request.track') }}">અરજી ટ્રેકિંગ</a><a href="{{ route('required-documents') }}">જરૂરી દસ્તાવેજોની માહિતી</a></div></section>
    <div class="identity-document-warning"><i class="bi bi-shield-exclamation" aria-hidden="true"></i><div><strong>સુરક્ષા સૂચના</strong><span>જાહેર વેબસાઇટ પર Aadhaar, PAN, Passport, Voter ID, Bank Documents અથવા અન્ય ઓળખ/KYC દસ્તાવેજો અપલોડ કરશો નહીં.</span></div></div>
    <div class="information-page-actions">@if($pageData['whatsappUrl'])<a href="{{ $pageData['whatsappUrl'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp-outline btn-lg rounded-pill px-4">WhatsApp દ્વારા સંપર્ક કરો</a>@endif<a href="{{ route('request.create') }}" class="btn btn-primary btn-lg rounded-pill px-4">ઓનલાઇન અરજી કરો</a><a href="{{ route('request.track') }}" class="btn btn-outline-primary btn-lg rounded-pill px-4">અરજી ટ્રેક કરો</a></div>
</div></section>
@endsection
