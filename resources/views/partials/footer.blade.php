<footer class="site-footer" aria-labelledby="footer-title">
    @php($hasPublicOfficeInfo = $site['email'] || $site['whatsappUrl'] || $site['address'] || $site['workingHoursLabel'])
    <div class="container">
        <div class="row g-5 footer-main">
            <div class="col-sm-6 {{ $hasPublicOfficeInfo ? 'col-lg-3' : 'col-lg-5' }}">
                <a class="footer-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                    @if($site['darkLogoUrl'])<span class="footer-logo-surface"><img src="{{ $site['darkLogoUrl'] }}" alt="{{ $site['businessName'] }} logo"></span>@else<span class="brand-mark">SC</span>@endif
                    <span><strong id="footer-title">{{ $site['businessName'] }}</strong><small>Trusted Documentation Partner</small></span>
                </a>
                <p class="footer-summary mt-4">મિલકત સંબંધિત દસ્તાવેજીકરણ અને માર્ગદર્શન માટે ગ્રાહક-કેન્દ્રિત સેવા.</p>
            </div>
            <div class="col-6 col-sm-3 {{ $hasPublicOfficeInfo ? 'col-lg-2' : 'col-lg-3' }}"><h2>Quick Links</h2><ul>
                <li><a href="{{ route('home') }}">Home</a></li><li><a href="{{ route('services.index') }}">Services</a></li><li><a href="{{ route('required-documents') }}">Required Documents</a></li><li><a href="{{ route('about') }}">About Us</a></li><li><a href="{{ route('faq') }}">FAQ</a></li><li><a href="{{ route('contact') }}">Contact</a></li>
            </ul></div>
            <div class="col-6 col-sm-3 {{ $hasPublicOfficeInfo ? 'col-lg-2' : 'col-lg-4' }}"><h2>Requests</h2><ul><li><a href="{{ route('request.create') }}">Apply Online</a></li><li><a href="{{ route('request.track') }}">Track Request</a></li></ul></div>
            @if($hasPublicOfficeInfo)<div class="col-lg-5"><h2>સંપર્ક / ઓફિસ માહિતી</h2>
                @if($site['email'])<div class="footer-info-row"><x-public-icon name="mail" size="21" /><div><small>Email</small><strong><a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a></strong></div></div>@endif
                @if($site['whatsappUrl'])<div class="footer-info-row"><x-public-icon name="message" size="21" /><div><small>WhatsApp</small><strong><a href="{{ $site['whatsappUrl'] }}" target="_blank" rel="noopener">{{ $site['whatsappNumber'] }}</a></strong></div></div>@endif
                @if($site['address'])<div class="footer-info-row"><x-public-icon name="location" size="21" /><div><small>Office Address</small><strong>{{ $site['address'] }}</strong></div></div>@endif
                @if($site['workingHoursLabel'] && !($site['officeStatus']['isOpen'] ?? false))<div class="footer-info-row"><x-public-icon name="clock" size="21" /><div><small>કાર્ય સમય</small><strong>આજે ઓફિસ બંધ છે</strong></div></div>@elseif($site['workingHoursLabel'])<div class="footer-info-row"><x-public-icon name="clock" size="21" /><div><small>કાર્ય સમય</small><strong>{{ str($site['workingHoursLabel'])->after('Working Hours: ') }}</strong></div></div>@endif
            </div>@endif
        </div>
        <div class="footer-disclaimer"><x-public-icon name="shield" size="20" /><span>Sai Consulting provides documentation and advisory services but does not practice as an advocate.</span></div>
        <nav class="footer-legal-links" aria-label="Legal"><a href="{{ route('privacy-policy') }}">Privacy Policy</a><a href="{{ route('terms') }}">Terms &amp; Conditions</a><a href="{{ route('refund-policy') }}">Refund Policy</a><a href="{{ route('disclaimer') }}">Disclaimer</a></nav>
        <div class="footer-bottom"><span>© {{ date('Y') }} {{ $site['businessName'] }}. All rights reserved.</span><span>Trusted Documentation Partner</span></div>
    </div>
</footer>
