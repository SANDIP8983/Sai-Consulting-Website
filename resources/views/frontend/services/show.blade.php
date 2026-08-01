@extends('layouts.app')

@section('title', $service->name_gu.' | '.$service->name_en.' | Sai Consulting')
@section('description', \Illuminate\Support\Str::limit(strip_tags($service->description ?: $service->name_en.' documentation service by Sai Consulting.'), 155))

@section('content')
<section class="service-detail-hero"><div class="container py-5">
    <nav aria-label="breadcrumb"><ol class="breadcrumb service-breadcrumb"><li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li><li class="breadcrumb-item"><a href="{{ route('services.index') }}">Services</a></li><li class="breadcrumb-item active" aria-current="page">{{ $service->name_en }}</li></ol></nav>
    <div class="row align-items-center g-5"><div class="col-lg-8"><span class="service-detail-icon"><i class="bi bi-file-earmark-richtext" aria-hidden="true"></i></span><h1>{{ $service->name_gu }}</h1><p class="service-english-name">{{ $service->name_en }}</p>@if($service->description)<p class="service-lead">{{ $service->description }}</p>@endif<div class="d-flex flex-wrap gap-3 mt-4"><a href="{{ route('request.create', ['service' => $service->id]) }}" class="btn btn-primary btn-lg rounded-pill px-4">Apply Online</a><a href="{{ route('request.track') }}" class="btn btn-secondary-action btn-lg rounded-pill px-4">Track Request</a></div></div>
        <div class="col-lg-4"><div class="service-summary-card premium-card">
            @if(!is_null($service->service_fee))<div class="summary-row"><i class="bi bi-currency-rupee" aria-hidden="true"></i><div><small>Service Fee</small><strong>₹{{ number_format((float) $service->service_fee, 2) }}</strong></div></div>@endif
            @if(!is_null($service->service_fee) && !is_null($service->advance_percentage))<div class="summary-row"><i class="bi bi-percent" aria-hidden="true"></i><div><small>Advance</small><strong>{{ $service->advance_percentage }}%</strong></div></div>@endif
            @if($service->estimated_days)<div class="summary-row"><i class="bi bi-calendar-check" aria-hidden="true"></i><div><small>Estimated Completion</small><strong>{{ $service->estimated_days }} days</strong></div></div>@endif
            @if($service->required_documents_count)<div class="summary-row"><i class="bi bi-files" aria-hidden="true"></i><div><small>Required Documents</small><strong>{{ $service->required_documents_count }}</strong></div></div>@endif
            <small class="summary-note">Final scope and charges are confirmed after document review.</small>
        </div></div>
    </div>
</div></section>

<section class="section-space service-detail-content"><div class="container"><div class="row g-5">
    <div class="col-lg-8">
        @if($service->requiredDocuments->isNotEmpty())<section class="detail-section premium-card" aria-labelledby="required-documents-title"><span class="detail-kicker">Documents</span><h2 id="required-documents-title">જરૂરી દસ્તાવેજો <small>Required Documents</small></h2><div class="required-document-grid">@foreach($service->requiredDocuments as $document)<div class="required-document"><span><i class="bi bi-file-earmark-check" aria-hidden="true"></i></span><div><strong>{{ $document->name_gu }}</strong><small>{{ $document->name_en }} · {{ $document->is_mandatory ? 'Mandatory' : 'Optional' }} · {{ strtoupper(implode(', ', $document->allowed_file_types ?? ['pdf','jpg','jpeg','png'])) }} · {{ number_format($document->max_upload_size_kb / 1024, 1) }} MB</small></div></div>@endforeach</div><div class="identity-warning"><i class="bi bi-shield-exclamation" aria-hidden="true"></i><span>Do not upload Aadhaar, PAN, passport, voter ID, bank documents, or other identity proofs.</span></div></section>@endif
        <section class="detail-section premium-card mt-4" aria-labelledby="service-process-title"><span class="detail-kicker">Process</span><h2 id="service-process-title">આ સેવા કેવી રીતે કાર્ય કરે છે <small>How It Works</small></h2>@include('frontend.services._process')</section>
        @if($service->notes)<section class="detail-section service-notes premium-card mt-4" aria-labelledby="service-notes-title"><i class="bi bi-info-circle" aria-hidden="true"></i><div><h2 id="service-notes-title">નોંધ <small>Customer Instructions</small></h2><p>{{ $service->notes }}</p></div></section>@endif
    </div>
    <aside class="col-lg-4"><div class="service-side-panel premium-card"><h2>Ready to apply?</h2><p>Start a secure online request with this service already selected.</p><a href="{{ route('request.create', ['service' => $service->id]) }}" class="btn btn-primary w-100 rounded-pill">Apply Online</a><a href="{{ route('services.index') }}" class="btn btn-outline-primary w-100 rounded-pill mt-2"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Services</a></div>
        @if($whatsappUrl)<div class="whatsapp-help premium-card mt-4"><span class="icon-box"><i class="bi bi-whatsapp" aria-hidden="true"></i></span><h2>Need Help?</h2><p>Chat with Sai Consulting through the configured WhatsApp contact.</p><a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-whatsapp-outline rounded-pill">Chat on WhatsApp</a></div>@endif
    </aside>
</div></div></section>

@if($relatedServices->isNotEmpty())<section class="section-space bg-soft-blue"><div class="container"><div class="section-heading"><span class="eyebrow"><span></span> Related Services</span><h2>અન્ય સક્રિય સેવાઓ</h2></div><div class="row g-4 mt-3">@foreach($relatedServices as $related)<div class="col-md-6 col-lg-4 d-flex"><x-public-service-card :service="$related" compact /></div>@endforeach</div></div></section>@endif
@endsection
