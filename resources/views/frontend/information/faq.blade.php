@extends('layouts.app')
@section('title', 'વારંવાર પૂછાતા પ્રશ્નો | Sai Consulting')
@section('description', 'Sai Consultingની સેવાઓ, અરજી, દસ્તાવેજો, billing, payment અને tracking વિશે સામાન્ય પ્રશ્નોના જવાબ.')
@section('canonical', \App\Support\Seo::route('faq'))
@section('content')
<x-public-page-heading title-gu="વારંવાર પૂછાતા પ્રશ્નો" title-en="FAQ" />
@php($faqs = [
    ['Sai Consulting કઈ સેવાઓ આપે છે?', 'Sai Consulting મિલકત સંબંધિત દસ્તાવેજો, દસ્તાવેજનું લખાણ, મિલકતનું ટાઇટલ ચેકિંગ અને અનુભવ આધારિત માર્ગદર્શન જેવી વિવિધ સેવાઓ પૂરી પાડે છે. ઉપલબ્ધ સેવાઓ Services page પર જોઈ શકાય છે.'],
    ['શું હું ઓનલાઇન અરજી કરી શકું?', 'હા. સેવા પસંદ કરીને અરજીની માહિતી આપી શકાય છે અને મંજૂર Property Documents અપલોડ કરી શકાય છે.'],
    ['અરજી કર્યા પછી શું થશે?', 'અરજીની ચકાસણી કરવામાં આવશે. વધુ માહિતી અથવા દસ્તાવેજો જરૂરી હોય તો સંપર્ક થઈ શકે છે. મંજૂરી પછી લાગુ Fee અને Paymentની માહિતી દર્શાવવામાં આવશે.'],
    ['અરજીની સ્થિતિ કેવી રીતે જાણી શકું?', 'Reference Number અને અરજીમાં નોંધાવેલા Mobile Number વડે Track Request પેજ પર સ્થિતિ જોઈ શકાય છે.'],
    ['કયા દસ્તાવેજો અપલોડ કરી શકાય?', 'પસંદ કરેલી સેવા માટે વર્તમાન configurationમાં દર્શાવેલા સંબંધિત Property Documents જ અપલોડ કરી શકાય છે.'],
    ['શું Aadhaar, PAN અથવા અન્ય KYC દસ્તાવેજો વેબસાઇટ પર અપલોડ કરવા પડે?', 'ના. જાહેર વેબસાઇટ Aadhaar, PAN, Passport, Voter ID, Bank Documents અથવા અન્ય ઓળખ/KYC પુરાવા સ્વીકારતી નથી.'],
    ['Payment ક્યારે કરવાનું રહેશે?', 'અરજીની ચકાસણી અને મંજૂરી પછી વર્તમાન workflow મુજબ અંતિમ billing અને Paymentની માહિતી દર્શાવવામાં આવે છે.'],
    ['કયા Payment Method ઉપલબ્ધ હોઈ શકે?', 'પરિસ્થિતિ અને અરજીના પ્રકાર મુજબ UPI, Bank Transfer, Cash at Office અથવા Cheque જેવા વિકલ્પ ઉપલબ્ધ હોઈ શકે છે. અહીં કોઈ Bank Account અથવા UPI ID જાહેર કરવામાં આવતું નથી.'],
    ['Government Charges અને Professional Feeમાં શું તફાવત છે?', 'Professional Fee Sai Consultingની સેવા માટેની Fee છે. Stamp Duty, Registration Fee અને અન્ય statutory રકમ Government Charges તરીકે અલગ રહે છે.'],
    ['GST કેવી રીતે લાગુ પડે છે?', 'GST વર્તમાન billing rules મુજબ Net Professional Fee પર લાગુ પડે છે. Government Charges અલગ છે અને taxable Professional Feeનો ભાગ નથી.'],
    ['અરજીમાં ફેરફાર કરી શકાય?', 'ફેરફાર અરજીની વર્તમાન સ્થિતિ અને workflow પર આધારિત છે. સુધારો જરૂરી હોય તો Sai Consultingનો સંપર્ક કરો.'],
    ['અરજી પૂર્ણ થયા પછી શું થશે?', 'લાગુ પડતું હોય ત્યારે પૂર્ણતા અને dispatch સંબંધિત ગ્રાહક-safe માહિતી trackingમાં જોઈ શકાય છે.'],
    ['Sai Consulting શું Advocate Office છે?', 'ના. Sai Consulting દસ્તાવેજનું લખાણ, મિલકતનું ટાઇટલ ચેકિંગ અને અનુભવ આધારિત માર્ગદર્શન આપે છે; વકીલાતની પ્રેક્ટિસ કરતું નથી.'],
    ['વધુ માહિતી માટે સંપર્ક કેવી રીતે કરવો?', 'Configured WhatsApp અથવા Email દ્વારા Sai Consultingનો સંપર્ક કરી શકાય છે.'],
    ['શું ઓફિસમાં રૂબરૂ આવીને સેવા મેળવી શકાય?', 'હા, configured office timing દરમિયાન મુલાકાત લઈ શકાય છે. શક્ય હોય ત્યાં સુધી પહેલાં WhatsApp દ્વારા સમય નક્કી કરવો ઉપયોગી રહેશે.'],
])
<section class="section-space information-page-body"><div class="container information-content-wide"><div class="accordion information-accordion" id="fullFaqAccordion">@foreach($faqs as $index => $faq)<div class="accordion-item"><h2 class="accordion-header" id="fullFaqHeading{{ $index }}"><button class="accordion-button {{ $index ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#fullFaqAnswer{{ $index }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="fullFaqAnswer{{ $index }}"><span class="faq-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><span>{{ $faq[0] }}</span></button></h2><div id="fullFaqAnswer{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="fullFaqHeading{{ $index }}" data-bs-parent="#fullFaqAccordion"><div class="accordion-body">{{ $faq[1] }}</div></div></div>@endforeach</div>
<div class="information-page-actions"><a href="{{ route('services.index') }}" class="btn btn-outline-primary rounded-pill px-4">Services</a><a href="{{ route('request.create') }}" class="btn btn-primary rounded-pill px-4">ઓનલાઇન અરજી કરો</a>@if($pageData['whatsappUrl'])<a href="{{ $pageData['whatsappUrl'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp-outline rounded-pill px-4">WhatsApp</a>@endif</div></div></section>
@endsection
