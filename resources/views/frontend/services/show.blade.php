@extends('layouts.app')

@section('title', $service->name_gu.' | '.$service->name_en.' | Sai Consulting')
@section('description', \Illuminate\Support\Str::limit($aboutService, 155))

@section('content')
<section class="service-detail-hero" aria-labelledby="service-title">
    <div class="container py-5">
        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb service-breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('services.index') }}">Services</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $service->name_en }}</li>
            </ol>
        </nav>
        <div class="service-detail-heading">
            <span class="service-detail-icon" aria-hidden="true"><i class="bi bi-file-earmark-richtext"></i></span>
            <div>
                <h1 id="service-title">{{ $service->name_gu }}</h1>
                <p class="service-english-name mb-0">{{ $service->name_en }}</p>
            </div>
        </div>
    </div>
</section>

<section class="section-space service-detail-content">
    <div class="container service-detail-simple">
        <section class="detail-section premium-card" aria-labelledby="about-service-title">
            <span class="detail-kicker">Service Information</span>
            <h2 id="about-service-title">સેવા વિશે</h2>
            <p class="service-about-copy mb-0">{{ $aboutService }}</p>
            @if($service->slug === 'legal-consulting')
                <p class="service-scope-note mb-0"><x-public-icon name="shield" size="20" /> Sai Consulting દસ્તાવેજીકરણ અને સલાહકાર સેવાઓ આપે છે; એડવોકેટ તરીકે પ્રેક્ટિસ કરતું નથી.</p>
            @endif
        </section>

        <section class="detail-section premium-card" aria-labelledby="required-documents-title">
            <span class="detail-kicker">Required Documents</span>
            <h2 id="required-documents-title">જરૂરી દસ્તાવેજો</h2>
            @if($documents->isNotEmpty())
                <ul class="required-document-grid service-document-list list-unstyled mb-0">
                    @foreach($documents as $document)
                        <li class="required-document {{ $document->is_mandatory ? 'document-required' : 'document-optional' }}">
                            <span aria-hidden="true"><i class="bi {{ $document->is_mandatory ? 'bi-file-earmark-check' : 'bi-file-earmark-plus' }}"></i></span>
                            <div>
                                <strong>{{ $document->name_gu }}</strong>
                                <small>{{ $document->name_en }} <span class="badge {{ $document->is_mandatory ? 'text-bg-danger' : 'text-bg-secondary' }}">{{ $document->is_mandatory ? 'Required' : 'Optional' }}</span></small>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="service-documents-empty"><x-public-icon name="document" size="24" /><p class="mb-0">આ સેવા માટે જરૂરી દસ્તાવેજોની યાદી હાલમાં ઉપલબ્ધ નથી. અરજી પછી જરૂરી માહિતી આપવામાં આવશે.</p></div>
            @endif
        </section>

        <section class="service-detail-actions" aria-label="Customer actions">
            @if($service->available_online)
                <a href="{{ route('request.create', ['service' => $service->id]) }}" class="btn btn-primary btn-lg rounded-pill px-4">ઓનલાઇન અરજી કરો</a>
            @endif
            <a href="{{ route('request.track') }}" class="btn btn-outline-primary btn-lg rounded-pill px-4">અરજી ટ્રેક કરો</a>
            @if($whatsappUrl)
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-whatsapp-outline btn-lg rounded-pill px-4"><i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp</a>
            @endif
        </section>
    </div>
</section>
@endsection
