<footer class="site-footer" aria-labelledby="footer-title">
    <div class="container">
        <div class="row g-5 footer-main">
            <div class="col-lg-4">
                <a class="footer-brand d-flex align-items-center gap-2" href="{{ route('home') }}"><span class="brand-mark">SC</span><span><strong id="footer-title">{{ $site['businessName'] }}</strong><small>Documentation & Consulting</small></span></a>
                <p class="footer-summary mt-4">વિશ્વાસપાત્ર દસ્તાવેજ ડ્રાફ્ટિંગ, મિલકત દસ્તાવેજ માર્ગદર્શન અને કન્સલ્ટિંગ સેવા.</p>
                <div class="footer-contact-grid mt-4">
                    @if($site['email'])<a class="footer-contact-card" href="mailto:{{ $site['email'] }}"><span class="footer-contact-icon"><x-public-icon name="mail" size="20" /></span><span><small>Email</small><strong>{{ $site['email'] }}</strong></span></a>@endif
                    @if($site['whatsappUrl'])<a class="footer-contact-card" href="{{ $site['whatsappUrl'] }}" target="_blank" rel="noopener"><span class="footer-contact-icon whatsapp"><x-public-icon name="message" size="20" /></span><span><small>WhatsApp</small><strong>{{ $site['whatsappNumber'] }}</strong></span></a>@endif
                </div>
            </div>
            <div class="col-6 col-lg-2"><h2>Explore</h2><ul><li><a href="{{ route('home') }}#services">Services</a></li><li><a href="{{ route('home') }}#process">How It Works</a></li><li><a href="{{ route('home') }}#about">About Us</a></li><li><a href="{{ route('home') }}#faq">FAQ</a></li></ul></div>
            <div class="col-6 col-lg-2"><h2>Requests</h2><ul><li><a href="{{ route('request.create') }}">Apply Online</a></li><li><a href="{{ route('request.track') }}">Track Request</a></li><li><a href="{{ route('home') }}#contact">Office Information</a></li></ul></div>
            <div class="col-lg-4"><h2>Office Information</h2>
                @if($site['address'])<div class="footer-info-row"><x-public-icon name="location" size="21" /><div><small>Office Address</small><strong>{{ $site['address'] }}</strong></div></div>@endif
                <div class="footer-info-row"><x-public-icon name="clock" size="21" /><div><small>Working Hours</small>@if($site['workingHoursLabel'])<strong>{{ str($site['workingHoursLabel'])->after('Working Hours: ') }}</strong>@endif<span>Second and fourth Saturday closed<br>Sunday closed</span></div></div>
            </div>
        </div>
        <div class="footer-disclaimer"><x-public-icon name="shield" size="20" /><span>Sai Consulting provides documentation and legal consulting services but does not practice as an advocate.</span></div>
        <div class="footer-bottom"><span>© {{ date('Y') }} {{ $site['businessName'] }}. All rights reserved.</span><span>Documentation handled with care · Secure · Transparent · Professional</span></div>
    </div>
</footer>
