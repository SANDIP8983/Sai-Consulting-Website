@extends('layouts.app')
@section('title', 'Request Submitted Successfully | Sai Consulting')
@section('content')
@php($submission = session('submitted_request'))
<section class="py-5 bg-light"><div class="container"><div class="row justify-content-center"><div class="col-lg-9"><div class="premium-card overflow-hidden">
<div class="text-center text-white p-5 bg-success"><i class="bi bi-check-circle-fill display-3"></i><h1 class="mt-3">તમારી અરજી સફળતાપૂર્વક નોંધાઈ ગઈ છે.</h1><p>Request Submitted Successfully</p></div>
<div class="p-4 p-lg-5"><div class="alert alert-success text-center"><small>Your Reference Number</small><div class="h2 text-primary fw-bold">{{ $submission['reference_no'] }}</div></div>
<div class="row g-4"><div class="col-md-6"><div class="border rounded p-3 h-100"><h2 class="h5">Selected Services</h2><ul>@foreach($submission['services'] ?? [] as $service)<li><strong>{{ $service['name_gu'] }}</strong><small class="d-block">{{ $service['name_en'] }}</small></li>@endforeach</ul></div></div><div class="col-md-6"><div class="border rounded p-3 h-100"><h2 class="h5">Current Status</h2><span class="badge text-bg-primary">{{ str($submission['status'] ?? 'received')->headline() }}</span>@if($submission['estimated_days'])<p class="mt-3">Approximately {{ $submission['estimated_days'] }} day(s)</p>@endif</div></div></div>
<div class="alert alert-info mt-4">અમારી ટીમ અરજીની ચકાસણી કર્યા બાદ જરૂરી હોય તો તમારો સંપર્ક કરશે.<br><strong>Reference Number સાચવી રાખો.</strong><hr>Our team will contact you through WhatsApp or your registered mobile number if anything else is needed.</div>
<div class="d-flex justify-content-center gap-2"><a href="{{ route('request.track') }}" class="btn btn-primary">Track Request</a><a href="{{ route('home') }}" class="btn btn-outline-secondary">Back to Home</a></div>
</div></div></div></div></div></section>
@endsection
