<section class="hero-section position-relative overflow-hidden" aria-labelledby="hero-title">
    <div class="hero-grid-pattern" aria-hidden="true"></div>
    <div class="hero-orb hero-orb-one" aria-hidden="true"></div>
    <div class="hero-orb hero-orb-two" aria-hidden="true"></div>
    <div class="container position-relative hero-container">
        <div class="row align-items-center min-vh-hero g-5">
            <div class="col-lg-7 py-lg-5">
                <span class="eyebrow hero-eyebrow"><span></span> Trusted Documentation Partner</span>
                <h1 id="hero-title" class="hero-title hero-title-gu mt-4"><span class="hero-title-line">તમારો વિશ્વસનીય</span><span class="hero-title-line">દસ્તાવેજીકરણ સાથી</span></h1>
                <p class="hero-copy hero-copy-gu mt-4">મિલકત સંબંધિત દસ્તાવેજો, ટાઇટલ ચેકિંગ અને દસ્તાવેજીકરણ સેવાઓ માટે વિશ્વસનીય સહયોગી.</p>
                <div class="d-flex flex-wrap gap-3 mt-4 hero-actions">
                    <a href="{{ route('request.create') }}" class="btn btn-primary btn-lg rounded-pill px-4">ઓનલાઇન અરજી કરો <span aria-hidden="true">→</span></a>
                    <a href="{{ route('request.track') }}" class="btn btn-secondary-action btn-lg rounded-pill px-4"><x-public-icon name="search" size="19" /> અરજી ટ્રેક કરો</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-visual" aria-label="Professional property documentation service">
                    <span class="trust-float trust-float-years"><strong>20+</strong> Years</span>
                    <span class="trust-float trust-float-secure"><x-public-icon name="shield" size="16" /> Secure</span>
                    <span class="trust-float trust-float-verified"><x-public-icon name="check" size="16" /> Verified</span>
                    <div class="visual-badge"><x-public-icon name="shield" size="18" /> Secure &amp; Confidential</div>
                    <div class="document-stack">
                        <div class="document-sheet sheet-back"></div>
                        <div class="document-sheet sheet-front">
                            <div class="doc-header">
                                @if($homepage['primaryLogoUrl'])
                                    <img src="{{ $homepage['primaryLogoUrl'] }}" alt="" class="hero-document-logo">
                                @else
                                    <span class="brand-mark small-mark">SC</span>
                                @endif
                                <div><strong>Property Documentation</strong><small>Professional Review</small></div>
                            </div>
                            <div class="doc-line w-75"></div><div class="doc-line"></div><div class="doc-line w-50"></div>
                            <div class="doc-detail"><x-public-icon name="building" size="34" /><div><span>Document Type</span><strong>Property Record</strong></div></div>
                            <div class="doc-check"><x-public-icon name="check" size="24" /><span>Information verified</span></div>
                            <div class="signature-line">{{ $homepage['businessName'] }}</div>
                        </div>
                    </div>
                    <div class="floating-card floating-card-one"><x-public-icon name="clock" size="20" /><span><strong>Timely updates</strong><small>Track every step</small></span></div>
                    <div class="floating-card floating-card-two"><x-public-icon name="document" size="20" /><span><strong>Reference generated</strong><small>Secure online request</small></span></div>
                </div>
            </div>
        </div>
    </div>
</section>
