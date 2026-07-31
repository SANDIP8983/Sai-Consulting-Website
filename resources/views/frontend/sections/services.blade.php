<section id="services" class="section-space bg-soft-blue" aria-labelledby="services-title">
    <div class="container">
        <div class="section-heading text-center mx-auto">
            <span class="eyebrow justify-content-center"><span></span> અમારી સેવાઓ</span>
            <h2 id="services-title">દસ્તાવેજ સંબંધિત વ્યાવસાયિક સેવાઓ</h2>
            <p>Active property-documentation and consulting services, presented in Gujarati and English.</p>
        </div>
        <div class="row g-4 mt-3">
            @forelse($homepage['services'] as $index => $service)
                @php($serviceIcon = str_contains(strtolower($service->name_en), 'token') ? 'building' : (str_contains(strtolower($service->name_en), 'verification') ? 'shield' : (str_contains(strtolower($service->name_en), 'consult') ? 'message' : 'document')))
                <div class="col-md-6 col-lg-4 d-flex">
                    <article class="service-card premium-card w-100" aria-labelledby="service-{{ $service->id }}">
                        <div class="service-card-top"><span class="icon-box"><x-public-icon :name="$serviceIcon" size="28" /></span><span class="service-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span></div>
                        <h3 id="service-{{ $service->id }}">{{ $service->name_gu }}</h3>
                        <h4>{{ $service->name_en }}</h4>
                        <p>{{ $service->description ?: 'અનુભવી માર્ગદર્શન સાથે સુરક્ષિત અને વ્યવસ્થિત દસ્તાવેજ સેવા.' }}</p>
                        <div class="service-meta">
                            @if($service->estimated_days)<span><x-public-icon name="clock" size="16" /> Approx. {{ $service->estimated_days }} days</span>@endif
                            @if($service->required_documents_count)<span><x-public-icon name="document" size="16" /> {{ $service->required_documents_count }} document requirements</span>@endif
                        </div>
                        <a class="service-link" href="{{ route('request.create', ['service' => $service->id]) }}" aria-label="Apply online for {{ $service->name_en }}">Apply for this service <span aria-hidden="true">→</span></a>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="empty-services premium-card text-center"><x-public-icon name="document" size="34" /><h3>Services will be available shortly.</h3></div></div>
            @endforelse
        </div>
        <div class="text-center mt-5"><a href="{{ route('request.create') }}#service_id" class="btn btn-outline-primary rounded-pill px-4">View All Services <span aria-hidden="true">→</span></a></div>
    </div>
</section>
