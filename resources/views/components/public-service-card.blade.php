@props(['service', 'number' => null, 'compact' => false, 'showFee' => true, 'showDescription' => true, 'showProcessingTime' => true, 'gujaratiActions' => false])
@php($icon = str_contains(strtolower($service->name_en), 'token') ? 'bi-building-check' : (str_contains(strtolower($service->name_en), 'verification') ? 'bi-shield-check' : (str_contains(strtolower($service->name_en), 'consult') ? 'bi-chat-square-text' : 'bi-file-earmark-text')))
<article {{ $attributes->class(['service-card premium-card w-100', 'service-card-compact' => $compact]) }} aria-labelledby="service-{{ $service->id }}">
    <div class="service-card-top"><span class="icon-box"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>@if($number)<span class="service-number">{{ str_pad($number, 2, '0', STR_PAD_LEFT) }}</span>@endif</div>
    <h3 id="service-{{ $service->id }}">{{ $service->name_gu }}</h3>
    <h4>{{ $service->name_en }}</h4>
    @if($showDescription && ($service->short_description || $service->description_en || $service->description_gu || $service->description))<p>{{ \Illuminate\Support\Str::limit($service->short_description ?: ($service->description_en ?: $service->description_gu ?: $service->description), $compact ? 120 : 180) }}</p>@endif
    <div class="service-meta">
        @if($showProcessingTime && ($service->processing_time_label || $service->estimated_days))<span><i class="bi bi-clock" aria-hidden="true"></i> {{ $service->processing_time_label ?: $service->estimated_days.' days' }}</span>@endif
        @if($showFee && !is_null($service->service_fee))<span><i class="bi bi-currency-rupee" aria-hidden="true"></i> {{ number_format((float) $service->service_fee, 2) }}</span>@endif
        @if($service->required_documents_count)<span><i class="bi bi-files" aria-hidden="true"></i> {{ $service->required_documents_count }} documents</span>@endif
    </div>
    <div class="service-card-actions">
        <a class="btn btn-outline-primary rounded-pill" href="{{ route('services.show', $service->slug) }}">{{ $gujaratiActions ? 'વિગતો જુઓ' : 'View Details' }}</a>
        @if($service->available_online)<a class="btn btn-primary rounded-pill" href="{{ route('request.create', ['service' => $service->id]) }}">{{ $gujaratiActions ? 'ઓનલાઇન અરજી કરો' : 'Apply Online' }}</a>@endif
    </div>
</article>
