<!doctype html>
<html lang="en">
<body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.6">
<div style="max-width:620px;margin:auto;padding:24px">
    <h1 style="font-size:20px;color:#0b3b82">Sai Consulting</h1>
    <h2 style="font-size:18px">Your finalized documents are available</h2>
    <p>Hello {{ $customerRequest->name }},</p>
    <p>Final documents for request <strong>{{ $customerRequest->reference_no }}</strong> are ready for secure download.</p>
    <ul>@foreach($documentLinks as $document)<li><a href="{{ $document['url'] }}">Download {{ $document['name'] }}</a></li>@endforeach</ul>
    <p>These secure links expire in {{ config('final-documents.signed_link_expiration_days') }} days. You can also access released documents after verifying your reference number and registered mobile number on the <a href="{{ route('request.track') }}">Sai Consulting tracking page</a>.</p>
    <p style="font-size:12px;color:#6b7280">Do not forward these private download links. Sai Consulting will never ask for passwords through this email.</p>
</div>
</body>
</html>
