<section id="faq" class="section-space faq-section" aria-labelledby="faq-title">
    <div class="container"><div class="row g-5 align-items-start">
        <div class="col-lg-4 faq-intro"><span class="eyebrow"><span></span> સામાન્ય પ્રશ્નો</span><h2 id="faq-title" class="mt-3">વારંવાર પૂછાતા પ્રશ્નો</h2><p class="text-secondary">Online requests, property documents and secure tracking વિશે જરૂરી જવાબો.</p><div class="faq-help premium-card"><span class="icon-box"><x-public-icon name="message" size="25" /></span><div><strong>વધુ મદદ જોઈએ?</strong><small>For additional help, contact us through configured email or WhatsApp.</small></div></div></div>
        <div class="col-lg-8"><div class="accordion premium-accordion" id="faqAccordion">
            @foreach([
                ['ઓનલાઇન વિનંતી કેવી રીતે મોકલવી?','How do I submit an online request?','Apply Online પસંદ કરો, સેવા અને જરૂરી માહિતી ભરો, માન્ય મિલકત દસ્તાવેજો અપલોડ કરો અને વિનંતી સબમિટ કરો.'],
                ['કયા દસ્તાવેજો અપલોડ કરી શકાય?','Which documents may be uploaded?','7/12, 8-A, Hak Patrak અને Property Card જેવા મિલકત દસ્તાવેજો અપલોડ કરી શકાય. Aadhaar, PAN અથવા અન્ય ઓળખના પુરાવા અપલોડ કરશો નહીં.'],
                ['વિનંતી કેવી રીતે ટ્રેક કરી શકાય?','How can I track my request?','સબમિશન પછી મળેલા reference number અને નોંધાયેલા mobile number વડે Track Request પેજ પર જાહેર સ્થિતિ જુઓ.'],
                ['સેવા પૂર્ણ થવામાં કેટલો સમય લાગે?','How long does a service take?','સમય પસંદ કરેલી સેવા અને દસ્તાવેજોની પૂર્ણતા પર આધારિત છે. ઉપલબ્ધ અંદાજ વિનંતી સબમિટ થયા પછી બતાવવામાં આવે છે.'],
                ['ઓફલાઇન સહાય ઉપલબ્ધ છે?','Is offline assistance available?','હા. Sai Consulting ઓનલાઇન અને ઓફલાઇન બંને રીતે દસ્તાવેજ સંબંધિત સહાય પૂરી પાડે છે.'],
                ['Sai Consulting સાથે સંપર્ક કેવી રીતે કરવો?','How can I contact Sai Consulting?','જાહેર સંપર્ક માટે admin settings માં ગોઠવેલ Email અથવા WhatsApp નો ઉપયોગ કરો. કોઈ phone-call service જાહેર કરવામાં આવતી નથી.'],
            ] as $index => $faq)
                <div class="accordion-item">
                    <h3 class="accordion-header" id="faqHeading{{ $index }}"><button class="accordion-button {{ $index ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faqAnswer{{ $index }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="faqAnswer{{ $index }}"><span class="faq-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><span>{{ $faq[0] }}<small>{{ $faq[1] }}</small></span></button></h3>
                    <div id="faqAnswer{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="faqHeading{{ $index }}" data-bs-parent="#faqAccordion"><div class="accordion-body">{{ $faq[2] }}</div></div>
                </div>
            @endforeach
        </div></div>
    </div></div>
</section>
