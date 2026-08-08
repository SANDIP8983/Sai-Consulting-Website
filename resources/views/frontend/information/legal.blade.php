@extends('layouts.app')
@section('title', 'Sai Consulting '.$legalPage['title_en'].' | '.$legalPage['title_gu'])
@section('description', $legalPage['meta'])
@section('canonical', url()->current())
@section('content')
<x-public-page-heading :title-gu="$legalPage['title_gu']" :title-en="$legalPage['title_en']" />
<section class="section-space information-page-body"><div class="container legal-content">
    @foreach($legalPage['sections'] as $index => $section)
        <section class="legal-section" aria-labelledby="legal-section-{{ $index }}"><span>{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><div><h2 id="legal-section-{{ $index }}">{{ $section[0] }}</h2>@foreach($section[1] as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div></section>
    @endforeach
    @if($pageData['email'] || $pageData['whatsappUrl'])<div class="legal-contact"><strong>સંપર્ક</strong><div>@if($pageData['email'])<a href="mailto:{{ $pageData['email'] }}">{{ $pageData['email'] }}</a>@endif @if($pageData['whatsappUrl'])<a href="{{ $pageData['whatsappUrl'] }}" target="_blank" rel="noopener">WhatsApp</a>@endif</div></div>@endif
</div></section>
@endsection
