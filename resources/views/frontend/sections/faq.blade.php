<section id="faq" class="section-space faq-section" aria-labelledby="faq-title">
    <div class="container"><div class="row g-5 align-items-start">
        <div class="col-lg-4 faq-intro">
            <span class="eyebrow"><span></span> સામાન્ય પ્રશ્નો</span>
            <h2 id="faq-title" class="mt-3">વારંવાર પૂછાતા પ્રશ્નો</h2>
            <p class="text-secondary">ઓનલાઇન અરજી અને દસ્તાવેજો વિશે ઉપયોગી માહિતી.</p>
            <a href="{{ route('faq') }}" class="btn btn-outline-primary rounded-pill px-4">બધા પ્રશ્નો જુઓ</a>
        </div>
        <div class="col-lg-8"><div class="accordion premium-accordion" id="faqAccordion">
            @foreach([
                ['ઓનલાઇન અરજી કેવી રીતે કરવી?', 'સેવા પસંદ કરો, જરૂરી માહિતી અને માન્ય Property Documents આપો અને અરજી સબમિટ કરો.'],
                ['કયા દસ્તાવેજો અપલોડ કરી શકાય?', 'સેવા માટે દર્શાવેલ મિલકત સંબંધિત દસ્તાવેજો જ અપલોડ કરો. Aadhaar, PAN અથવા અન્ય ઓળખના પુરાવા અપલોડ કરશો નહીં.'],
                ['અરજી કેવી રીતે ટ્રેક કરી શકાય?', 'Reference Number અને અરજીમાં નોંધાવેલા mobile number વડે Track Request પેજ પર સ્થિતિ જુઓ.'],
            ] as $index => $faq)
                <div class="accordion-item">
                    <h3 class="accordion-header" id="faqHeading{{ $index }}"><button class="accordion-button {{ $index ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faqAnswer{{ $index }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="faqAnswer{{ $index }}"><span class="faq-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><span>{{ $faq[0] }}</span></button></h3>
                    <div id="faqAnswer{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="faqHeading{{ $index }}" data-bs-parent="#faqAccordion"><div class="accordion-body">{{ $faq[1] }}</div></div>
                </div>
            @endforeach
        </div></div>
    </div></div>
</section>
