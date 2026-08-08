@extends('layouts.app')
@section('title', 'અમારા વિશે | Sai Consulting')
@section('description', 'Sai Consultingની દસ્તાવેજીકરણ સેવા, અનુભવ, મૂલ્યો અને વ્યાવસાયિક કાર્યક્ષેત્ર વિશે જાણો.')
@section('canonical', \App\Support\Seo::route('about'))
@section('content')
<x-public-page-heading title-gu="અમારા વિશે" title-en="About Sai Consulting" />
<section class="section-space information-page-body"><div class="container information-content">
    <section class="information-section"><h2>Sai Consulting વિશે</h2><p>Sai Consulting મિલકત સંબંધિત દસ્તાવેજો, દસ્તાવેજનું લખાણ, ટાઇટલ ચેકિંગ અને અનુભવ આધારિત માર્ગદર્શન માટે વિશ્વસનીય સેવા આપે છે. અમારું મુખ્ય લક્ષ્ય ગ્રાહકને દસ્તાવેજીકરણની પ્રક્રિયામાં યોગ્ય માર્ગદર્શન અને સુવ્યવસ્થિત સેવા પૂરી પાડવાનું છે.</p></section>
    <section class="information-section"><h2>અમારો અનુભવ</h2><p>મિલકતના દસ્તાવેજો અને ટાઇટલ ચેકિંગના ક્ષેત્રમાં {{ 20 }}થી વધુ વર્ષના અનુભવના આધારે અમે દરેક કામની વિગતો ધ્યાનપૂર્વક તપાસવાનો અને ગ્રાહકને જરૂરિયાત મુજબ માર્ગદર્શન આપવાનો પ્રયત્ન કરીએ છીએ.</p></section>
    <section class="information-section"><h2>અમારી સેવાઓ</h2><p>દસ્તાવેજનું લખાણ, મિલકતનું ટાઇટલ ચેકિંગ અને સંબંધિત માર્ગદર્શન માટે ઉપલબ્ધ વિકલ્પો જુઓ.</p><a href="{{ route('services.index') }}" class="information-link">બધી સેવાઓ જુઓ →</a></section>
    <div class="row g-4"><section class="col-md-6"><div class="information-section h-100"><h2>અમારી વિશેષતા</h2><ul class="information-check-list"><li>અનુભવ આધારિત માર્ગદર્શન</li><li>સુરક્ષિત દસ્તાવેજ વ્યવસ્થા</li><li>ઓનલાઇન અરજી અને ટ્રેકિંગ</li><li>વ્યક્તિગત માર્ગદર્શન</li></ul></div></section><section class="col-md-6"><div class="information-section h-100"><h2>અમારા મૂલ્યો</h2><ul class="information-check-list"><li>વિશ્વાસ</li><li>ચોકસાઈ</li><li>ગોપનીયતા</li><li>સમયસર સેવા</li></ul></div></section></div>
    <section class="professional-clarification"><x-public-icon name="shield" size="26" /><div><h2>મહત્વપૂર્ણ સ્પષ્ટતા</h2><p class="mb-0">Sai Consulting દસ્તાવેજનું લખાણ, મિલકતનું ટાઇટલ ચેકિંગ અને અનુભવ આધારિત કાનૂની માર્ગદર્શન આપે છે. Sai Consulting દ્વારા વકીલાતની પ્રેક્ટિસ કરવામાં આવતી નથી.</p></div></section>
    <div class="row g-4"><section class="col-md-6"><div class="information-section h-100"><h2>અમારું ધ્યેય</h2><p>ગ્રાહકોને વિશ્વસનીય, સુરક્ષિત અને સમયસર દસ્તાવેજીકરણ સેવા પૂરી પાડવી.</p></div></section><section class="col-md-6"><div class="information-section h-100"><h2>અમારું વિઝન</h2><p>ટેક્નોલોજી અને અનુભવના સંયોજન દ્વારા મિલકત દસ્તાવેજીકરણ પ્રક્રિયાને વધુ સરળ, સુરક્ષિત અને સુવ્યવસ્થિત બનાવવી.</p></div></section></div>
    <div class="information-page-actions"><a href="{{ route('request.create') }}" class="btn btn-primary btn-lg rounded-pill px-4">ઓનલાઇન અરજી કરો</a><a href="{{ route('request.track') }}" class="btn btn-outline-primary btn-lg rounded-pill px-4">અરજી ટ્રેક કરો</a>@if($pageData['whatsappUrl'])<a href="{{ $pageData['whatsappUrl'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp-outline btn-lg rounded-pill px-4">WhatsApp</a>@endif</div>
</div></section>
@endsection
