<section id="contact" class="section-space bg-soft-blue">
    <div class="container"><div class="section-heading text-center mx-auto"><span class="eyebrow justify-content-center"><span></span> ઓફિસ માહિતી</span><h2>કાર્ય સમય અને સંપર્ક</h2><p>Plan your visit or connect securely through email and WhatsApp.</p></div>
        <div class="row g-4 mt-3">
            <div class="col-lg-5"><div class="premium-card office-card h-100"><span class="icon-box"><x-public-icon name="clock" size="28" /></span><h3 class="mt-4">Working Hours</h3>@if($homepage['workingHoursLabel'])<p class="working-hours-large">{{ str($homepage['workingHoursLabel'])->after('Working Hours: ') }}</p>@endif
                @if($homepage['holidayNotice'])<div class="holiday-notice"><strong>Upcoming holiday</strong><span>{{ $homepage['holidayNotice']['title'] }} · {{ $homepage['holidayNotice']['date'] }}</span>@if($homepage['holidayNotice']['description'])<small>{{ $homepage['holidayNotice']['description'] }}</small>@endif</div>@endif
                @if($homepage['address'])<div class="contact-line mt-4"><x-public-icon name="location" size="21" /><span>{{ $homepage['address'] }}</span></div>@endif
                @if($homepage['email'])<div class="contact-line"><x-public-icon name="mail" size="21" /><a href="mailto:{{ $homepage['email'] }}">{{ $homepage['email'] }}</a></div>@endif
                @if($homepage['whatsappUrl'])<a href="{{ $homepage['whatsappUrl'] }}" target="_blank" rel="noopener" class="btn btn-whatsapp rounded-pill px-4 mt-3"><x-public-icon name="message" size="18" /> WhatsApp</a>@endif
            </div></div>
            <div class="col-lg-7"><div class="premium-card office-card h-100"><h3>Weekly Working Hours</h3><div class="hours-list mt-3">
                @php($days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'])
                @foreach($homepage['timings'] as $timing)<div class="hours-row"><span>{{ $days[$timing->day_of_week] }}</span><strong>@if($timing->day_of_week === 0 || $timing->is_closed || !$timing->opens_at || !$timing->closes_at)Closed @else{{ \Illuminate\Support\Carbon::parse($timing->opens_at)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($timing->closes_at)->format('g:i A') }}@endif</strong></div>@endforeach
                @if($homepage['timings']->isEmpty())<p class="text-secondary mb-0">Working hours will be updated shortly.</p>@endif
            </div><div class="schedule-note"><x-public-icon name="clock" size="20" /><span>Second and fourth Saturday closed · Sunday closed</span></div></div></div>
        </div>
    </div>
</section>
