<section id="services" class="section-space bg-soft-blue" aria-labelledby="services-title">
    <div class="container">
        <div class="section-heading text-center mx-auto">
            <span class="eyebrow justify-content-center"><span></span> અમારી સેવાઓ</span>
            <h2 id="services-title">દસ્તાવેજ સંબંધિત વ્યાવસાયિક સેવાઓ</h2>
            <p>Active property-documentation and consulting services, presented in Gujarati and English.</p>
        </div>
        <div class="row g-4 mt-3">
            @forelse($homepage['services'] as $index => $service)
                <div class="col-md-6 col-lg-4 d-flex"><x-public-service-card :service="$service" :number="$index + 1" compact /></div>
            @empty
                <div class="col-12"><div class="empty-services premium-card text-center"><x-public-icon name="document" size="34" /><h3>Services will be available shortly.</h3></div></div>
            @endforelse
        </div>
        <div class="text-center mt-5"><a href="{{ route('services.index') }}" class="btn btn-outline-primary rounded-pill px-4">View All Services <span aria-hidden="true">→</span></a></div>
    </div>
</section>
