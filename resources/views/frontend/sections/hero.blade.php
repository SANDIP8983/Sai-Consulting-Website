<section class="hero-section position-relative overflow-hidden">
    <div class="hero-orb hero-orb-one"></div><div class="hero-orb hero-orb-two"></div>
    <div class="container position-relative hero-container">
        <div class="row align-items-center min-vh-hero g-5">
            <div class="col-lg-7 py-lg-5">
                <span class="eyebrow"><span></span> 20+ વર્ષનો વિશ્વાસ અને અનુભવ</span>
                <h1 class="hero-title mt-4">વિશ્વાસપાત્ર દસ્તાવેજ ડ્રાફ્ટિંગ અને <span>કન્સલ્ટિંગ સેવા</span></h1>
                <p class="hero-copy mt-4">મિલકત અને કાનૂની દસ્તાવેજો માટે અનુભવી માર્ગદર્શન, સુરક્ષિત પ્રક્રિયા અને સમયસર સેવા—હવે સરળ ઓનલાઇન વિનંતી સાથે.</p>
                <p class="text-secondary">Professional documentation support with a clear, secure and trackable process.</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="{{ route('request.create') }}" class="btn btn-primary btn-lg rounded-pill px-4">Apply Online <span aria-hidden="true">→</span></a>
                    <a href="{{ route('request.track') }}" class="btn btn-secondary-action btn-lg rounded-pill px-4">Track Request</a>
                    @if($homepage['whatsappUrl'])<a href="{{ $homepage['whatsappUrl'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp-outline btn-lg rounded-pill px-4"><x-public-icon name="message" size="20" /> WhatsApp</a>@endif
                </div>
                <div class="hero-trust-grid mt-5">
                    @foreach([['20+', 'Years Experience'], ['shield', 'Secure Handling'], ['clock', 'Timely Service'], ['check', 'Transparent Process']] as $point)
                        <div class="hero-trust-item">
                            @if(is_numeric(substr($point[0], 0, 1)))<strong>{{ $point[0] }}</strong>@else<x-public-icon :name="$point[0]" size="22" />@endif
                            <span>{{ $point[1] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-visual" aria-label="Secure professional documentation process illustration">
                    <span class="trust-float trust-float-years"><strong>20+</strong> Years</span>
                    <span class="trust-float trust-float-secure"><x-public-icon name="shield" size="16" /> Secure</span>
                    <span class="trust-float trust-float-verified"><x-public-icon name="check" size="16" /> Verified</span>
                    <span class="trust-float trust-float-trusted"><x-public-icon name="building" size="16" /> Trusted</span>
                    <div class="visual-badge"><x-public-icon name="shield" size="18" /> Secure & Confidential</div>
                    <div class="document-stack">
                        <div class="document-sheet sheet-back"></div>
                        <div class="document-sheet sheet-front">
                            <div class="doc-header"><span class="brand-mark small-mark">SC</span><div><strong>Property Documentation</strong><small>Professional Review</small></div></div>
                            <div class="doc-line w-75"></div><div class="doc-line"></div><div class="doc-line w-50"></div>
                            <div class="doc-detail"><x-public-icon name="building" size="34" /><div><span>Document Type</span><strong>Property Record</strong></div></div>
                            <div class="doc-check"><x-public-icon name="check" size="24" /><span>Information verified</span></div>
                            <div class="signature-line">Sai Consulting</div>
                        </div>
                    </div>
                    <div class="floating-card floating-card-one"><x-public-icon name="clock" size="20" /><span><strong>Timely updates</strong><small>Track every step</small></span></div>
                    <div class="floating-card floating-card-two"><x-public-icon name="document" size="20" /><span><strong>Reference generated</strong><small>SC/YYYY/000001</small></span></div>
                </div>
            </div>
        </div>
    </div>
</section>
