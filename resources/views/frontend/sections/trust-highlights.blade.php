<section class="trust-strip section-overlap position-relative">
    <div class="container"><div class="row g-3">
        @foreach([['clock','20+ વર્ષનો અનુભવ','20+ Years Experience'],['shield','સુરક્ષિત દસ્તાવેજો','Secure Documentation'],['online','ઓનલાઇન વિનંતી','Online Request'],['search','વિનંતી ટ્રેકિંગ','Request Tracking'],['check','પારદર્શક પ્રક્રિયા','Transparent Process'],['document','ઝડપી સેવા','Fast Delivery']] as $item)
            <div class="col-sm-6 col-lg-4 col-xl-2"><div class="trust-highlight premium-card"><span class="icon-box"><x-public-icon :name="$item[0]" size="25" /></span><div><strong>{{ $item[1] }}</strong><small>{{ $item[2] }}</small></div></div></div>
        @endforeach
    </div></div>
</section>
