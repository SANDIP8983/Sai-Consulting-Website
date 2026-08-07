<section id="services" class="section-space bg-soft-blue" aria-labelledby="services-title">
    <div class="container">
        <div class="section-heading text-center mx-auto">
            <span class="eyebrow justify-content-center"><span></span> મુખ્ય સેવાઓ</span>
            <h2 id="services-title">અમારી સેવાઓ</h2>
            <p>મિલકત અને દસ્તાવેજીકરણ સંબંધિત અમારી મુખ્ય સેવાઓ.</p>
        </div>
        <div class="row g-4 mt-3">
            @forelse($homepage['services'] as $index => $service)
                <div class="col-md-6 col-lg-4 d-flex"><x-public-service-card :service="$service" :number="$index + 1" compact :show-fee="false" :show-description="false" :show-processing-time="false" gujarati-actions /></div>
            @empty
                <div class="col-12"><div class="empty-services premium-card text-center"><x-public-icon name="document" size="34" /><h3>Services will be available shortly.</h3></div></div>
            @endforelse
        </div>
        <div class="text-center mt-5"><a href="{{ route('services.index') }}" class="btn btn-outline-primary rounded-pill px-4">બધી સેવાઓ જુઓ <span aria-hidden="true">→</span></a></div>
    </div>
</section>
