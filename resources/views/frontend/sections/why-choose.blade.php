<section class="section-space why-section text-white position-relative overflow-hidden" aria-labelledby="why-title">
    <div class="container position-relative"><div class="row align-items-center g-5">
        <div class="col-lg-5">
            <span class="eyebrow eyebrow-light"><span></span> શા માટે Sai Consulting?</span>
            <h2 id="why-title" class="display-6 fw-bold mt-3">વિશ્વાસપાત્ર દસ્તાવેજીકરણ સહાય</h2>
            <p class="why-subtitle-gu">સરળ માહિતી, યોગ્ય માર્ગદર્શન અને ગ્રાહક-કેન્દ્રિત સેવા.</p>
        </div>
        <div class="col-lg-7"><div class="row g-3">
            @foreach([
                ['clock', '20+ વર્ષનો અનુભવ'],
                ['document', 'દસ્તાવેજીકરણ પર કેન્દ્રિત સેવા'],
                ['online', 'ઓનલાઇન અરજી અને ટ્રેકિંગ'],
                ['message', 'જરૂરી માર્ગદર્શન અને સહાય'],
            ] as $item)
                <div class="col-sm-6"><div class="why-card"><span class="icon-box"><x-public-icon :name="$item[0]" size="27" /></span><h3>{{ $item[1] }}</h3></div></div>
            @endforeach
        </div></div>
    </div></div>
</section>
