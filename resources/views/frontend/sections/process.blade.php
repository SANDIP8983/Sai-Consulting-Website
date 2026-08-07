<section id="process" class="section-space" aria-labelledby="process-title">
    <div class="container">
        <div class="section-heading text-center mx-auto">
            <span class="eyebrow justify-content-center"><span></span> પ્રક્રિયા</span>
            <h2 id="process-title">ઓનલાઇન અરજીની સરળ પ્રક્રિયા</h2>
        </div>
        @php($steps = [
            ['search', 'સેવા પસંદ કરો'],
            ['online', 'અરજીની માહિતી આપો'],
            ['upload', 'જરૂરી Property Documents અપલોડ કરો'],
            ['document', 'Reference Number મેળવો'],
            ['search', 'અરજી ટ્રેક કરો'],
            ['check', 'સેવા પૂર્ણ થયા પછી માહિતી મેળવો'],
        ])
        <ol class="process-grid mt-5 list-unstyled">
            @foreach($steps as $index => $step)
                <li class="process-step"><span class="step-number">{{ $index + 1 }}</span><span class="icon-box"><x-public-icon :name="$step[0]" size="26" /></span><h3>{{ $step[1] }}</h3></li>
            @endforeach
        </ol>
        <div class="process-actions d-flex flex-wrap justify-content-center gap-3 mt-5">
            <a href="{{ route('request.create') }}" class="btn btn-primary rounded-pill px-4">ઓનલાઇન અરજી કરો</a>
            <a href="{{ route('request.track') }}" class="btn btn-outline-primary rounded-pill px-4">અરજી ટ્રેક કરો</a>
        </div>
    </div>
</section>
