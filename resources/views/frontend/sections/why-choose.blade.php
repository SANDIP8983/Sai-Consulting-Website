<section class="section-space why-section text-white position-relative overflow-hidden">
    <div class="container position-relative"><div class="row align-items-center g-5">
        <div class="col-lg-5"><span class="eyebrow eyebrow-light"><span></span> શા માટે Sai Consulting?</span><h2 class="display-6 fw-bold mt-3">Why Choose Sai Consulting</h2><p class="why-subtitle-gu">અનુભવ, સુરક્ષા અને સ્પષ્ટ પ્રક્રિયા</p><p class="text-white-50 mt-3">Documentation-focused expertise with online and offline support for a service experience you can trust.</p><div class="experience-panel mt-4"><strong>20+</strong><span>Years of documentation<br>service experience</span></div></div>
        <div class="col-lg-7"><div class="row g-3">
            @foreach([['document','દસ્તાવેજ-કેન્દ્રિત નિપુણતા','Documentation-focused expertise'],['shield','દસ્તાવેજોની સુરક્ષિત સંભાળ','Secure document handling'],['online','ઓનલાઇન અને ઓફલાઇન સહાય','Online and offline support'],['message','ઇમેઇલ અને WhatsApp સંપર્ક','Email and WhatsApp communication']] as $item)
                <div class="col-sm-6"><div class="why-card"><span class="icon-box"><x-public-icon :name="$item[0]" size="27" /></span><h3>{{ $item[1] }}</h3><p>{{ $item[2] }}</p></div></div>
            @endforeach
        </div></div>
    </div></div>
</section>
