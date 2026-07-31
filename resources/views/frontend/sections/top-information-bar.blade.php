<div class="top-info-bar">
    <div class="container py-2">
        <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-lg-between gap-2 gap-lg-4 small">
            <span class="top-tagline">વિશ્વાસપાત્ર દસ્તાવેજ ડ્રાફ્ટિંગ અને કન્સલ્ટિંગ સેવા</span>
            <div class="d-flex flex-wrap align-items-center justify-content-center gap-3">
                @if($site['email'])
                    <a href="mailto:{{ $site['email'] }}"><x-public-icon name="mail" size="16" /> {{ $site['email'] }}</a>
                @endif
                @if($site['whatsappUrl'])
                    <a href="{{ $site['whatsappUrl'] }}" target="_blank" rel="noopener"><x-public-icon name="message" size="16" /> WhatsApp</a>
                @endif
                @if($site['workingHoursLabel'])<span class="working-hours-pill"><x-public-icon name="clock" size="16" /> <span>{{ $site['workingHoursLabel'] }}</span></span>@endif
            </div>
        </div>
    </div>
</div>
