<section id="contact" class="section-space bg-soft-blue">
    <div class="container">
        <div class="section-heading text-center mx-auto"><span class="eyebrow justify-content-center"><span></span> ઓફિસ માહિતી</span><h2>કાર્ય સમય અને સંપર્ક</h2><p>મુલાકાત પહેલાં કાર્ય સમય તપાસો અથવા Email અને WhatsApp દ્વારા સંપર્ક કરો.</p></div>
        <div class="row g-4 mt-3">
            <div class="col-lg-5"><div class="premium-card office-card h-100"><span class="icon-box"><x-public-icon name="clock" size="28" /></span><h3 class="mt-4">ઓફિસનો કાર્ય સમય</h3>
                @if($homepage['workingHoursLabel'])
                    <p class="working-hours-large">{{ str($homepage['workingHoursLabel'])->after('Working Hours: ') }}</p>
                @else
                    <p class="office-hours-fallback">ઓફિસનો કાર્ય સમય હાલમાં ઉપલબ્ધ નથી. મુલાકાત પહેલાં Email અથવા WhatsApp દ્વારા ખાતરી કરો.</p>
                @endif
                @if($homepage['holidayNotice'])<div class="holiday-notice"><strong>આગામી રજા</strong><span>{{ $homepage['holidayNotice']['title'] }} · {{ $homepage['holidayNotice']['date'] }}</span>@if($homepage['holidayNotice']['description'])<small>{{ $homepage['holidayNotice']['description'] }}</small>@endif</div>@endif
                @if($homepage['address'])<div class="contact-line mt-4"><x-public-icon name="location" size="21" /><span>{{ $homepage['address'] }}</span></div>@endif
                @if($homepage['email'])<div class="contact-line"><x-public-icon name="mail" size="21" /><a href="mailto:{{ $homepage['email'] }}">{{ $homepage['email'] }}</a></div>@endif
                @if($homepage['whatsappUrl'])<a href="{{ $homepage['whatsappUrl'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp rounded-pill px-4 mt-3"><x-public-icon name="message" size="18" /> WhatsApp</a>@endif
            </div></div>
            <div class="col-lg-7"><div class="premium-card office-card h-100"><h3>અઠવાડિયાનો કાર્ય સમય</h3><div class="hours-list mt-3">
                @php($days = ['રવિવાર', 'સોમવાર', 'મંગળવાર', 'બુધવાર', 'ગુરુવાર', 'શુક્રવાર', 'શનિવાર'])
                @foreach($homepage['timings'] as $timing)<div class="hours-row"><span>{{ $days[$timing->day_of_week] }}</span><strong>@if($timing->day_of_week === 0 || $timing->is_closed || !$timing->opens_at || !$timing->closes_at)બંધ @else{{ \Illuminate\Support\Carbon::parse($timing->opens_at)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($timing->closes_at)->format('g:i A') }}@endif</strong></div>@endforeach
                @if($homepage['timings']->isEmpty())<div class="office-hours-empty"><x-public-icon name="clock" size="22" /><p class="mb-0">અઠવાડિયાનો કાર્ય સમય ટૂંક સમયમાં અહીં ઉપલબ્ધ થશે.</p></div>@endif
            </div>
            @if($homepage['timings']->isNotEmpty())<div class="schedule-note"><x-public-icon name="clock" size="20" /><span>બીજો અને ચોથો શનિવાર તથા રવિવાર બંધ</span></div>@endif
            </div></div>
        </div>
    </div>
</section>
