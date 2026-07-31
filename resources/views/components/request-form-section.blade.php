@props([
    'id',
    'step',
    'icon',
    'titleGu',
    'titleEn',
    'description' => null,
])

<section id="{{ $id }}" {{ $attributes->class(['request-form-section premium-card']) }} aria-labelledby="{{ $id }}-title">
    <div class="request-section-heading">
        <span class="request-section-icon" aria-hidden="true"><i class="bi bi-{{ $icon }}"></i></span>
        <div>
            <span class="request-section-step">પગલું {{ $step }} · Step {{ $step }}</span>
            <h2 id="{{ $id }}-title">{{ $titleGu }} <small>{{ $titleEn }}</small></h2>
            @if($description)<p>{{ $description }}</p>@endif
        </div>
    </div>
    {{ $slot }}
</section>
