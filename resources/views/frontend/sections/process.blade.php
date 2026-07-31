<section id="process" class="section-space" aria-labelledby="process-title">
    <div class="container">
        <div class="section-heading text-center mx-auto"><span class="eyebrow justify-content-center"><span></span> પ્રક્રિયા</span><h2 id="process-title">વિનંતીથી પૂર્ણ સેવા સુધીની સરળ પ્રક્રિયા</h2><p>The public request workflow stays clear, secure and trackable at every step.</p></div>
        @php($steps = [
            ['search','સેવા પસંદ કરો','Select service','સક્રિય સેવાઓમાંથી જરૂરી સેવા પસંદ કરો.'],
            ['online','માહિતી મોકલો','Submit information','તમારી સંપર્ક અને મિલકતની વિગતો સુરક્ષિત રીતે ભરો.'],
            ['upload','મિલકત દસ્તાવેજ અપલોડ કરો','Upload property documents','માત્ર માન્ય મિલકત દસ્તાવેજો ખાનગી રીતે અપલોડ કરો.'],
            ['document','સંદર્ભ નંબર મેળવો','Receive reference number','સબમિશન પછી અનન્ય reference number મેળવો.'],
            ['search','વિનંતી ટ્રેક કરો','Track request','Reference number અને mobile number વડે સ્થિતિ જુઓ.'],
            ['check','પૂર્ણ સેવા મેળવો','Receive completed service','પ્રક્રિયા પૂર્ણ થયા પછી તૈયાર સેવા મેળવો.'],
        ])
        <ol class="process-grid mt-5 list-unstyled">
            @foreach($steps as $index => $step)
                <li class="process-step"><span class="step-number">{{ $index + 1 }}</span><span class="icon-box"><x-public-icon :name="$step[0]" size="26" /></span><h3>{{ $step[1] }}</h3><strong>{{ $step[2] }}</strong><p>{{ $step[3] }}</p></li>
            @endforeach
        </ol>
        <div class="process-actions text-center mt-5"><a href="{{ route('request.create') }}" class="btn btn-primary rounded-pill px-4">Start Online Request</a><a href="{{ route('request.track') }}" class="btn btn-link text-decoration-none ms-2">Track an existing request →</a></div>
    </div>
</section>
