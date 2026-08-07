<section class="section-space tracking-section">
    <div class="container"><div class="tracking-panel"><div class="row align-items-center g-4">
        <div class="col-lg-5"><span class="eyebrow eyebrow-light"><span></span> અરજીની સ્થિતિ</span><h2 class="text-white mt-3">તમારી અરજી સરળતાથી ટ્રેક કરો</h2><p class="text-white-50 mb-0">Reference Number અને અરજીમાં નોંધાવેલા mobile number વડે માત્ર ગ્રાહક માટેની સ્થિતિ જુઓ.</p></div>
        <div class="col-lg-7"><form method="POST" action="{{ route('request.track.lookup') }}" class="tracking-form">@csrf<div class="row g-3">
            <div class="col-md-7"><label for="home_reference_no" class="form-label">Reference Number</label><input id="home_reference_no" name="reference_no" class="form-control form-control-lg" placeholder="SC/2026/000001" required></div>
            <div class="col-md-5"><label for="home_mobile" class="form-label">Mobile Number</label><input id="home_mobile" name="mobile" class="form-control form-control-lg" inputmode="numeric" maxlength="10" placeholder="10-digit number" required></div>
            <div class="col-12 d-flex flex-wrap align-items-center gap-3"><button type="submit" class="btn btn-light btn-lg rounded-pill px-4">અરજી ટ્રેક કરો</button><a href="{{ route('request.track') }}" class="text-white">ટ્રેકિંગ પેજ ખોલો →</a></div>
        </div></form></div>
    </div></div></div>
</section>
