@extends('layouts.app')
@section('title', 'Online Service Request | Sai Consulting')
@section('content')
@php($selectedIds = array_map('intval', old('service_ids', array_filter([old('service_id'), request('service')]))))
<section class="py-5 text-white" style="background:linear-gradient(135deg,#082f6b,#0b5ed7)"><div class="container"><span class="eyebrow"><span></span>Secure Production Request</span><h1 class="fw-bold">સેવા માટે ઓનલાઇન અરજી</h1><p>Select services, review charges, and send available property documents.</p></div></section>
<section class="py-5 bg-light"><div class="container">
@if($errors->any())<div class="alert alert-danger"><strong>Please correct the highlighted information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="request-stepper mb-4">@foreach(['Customer','Services & Fees','Documents','Review'] as $label)<button type="button" class="request-step {{ $loop->first ? 'active' : '' }}"><span>{{ $loop->iteration }}</span><small>{{ $label }}</small></button>@endforeach</div>
<form id="final-request-form" method="POST" action="{{ route('request.store') }}" enctype="multipart/form-data">@csrf
<section class="request-panel premium-card p-4 p-lg-5" data-step="1"><span class="detail-kicker">Step 1</span><h2>ગ્રાહકની માહિતી <small>Customer Information</small></h2><div class="row g-3 mt-2">@foreach([['name','Full Name','text',true],['mobile','Mobile Number','text',true],['whatsapp','WhatsApp (Optional)','text',false],['email','Email (Optional)','email',false],['village','Village','text',true],['taluka','Taluka','text',true],['district','District','text',true]] as [$field,$label,$type,$required])<div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }} @if($required)<b class="text-danger">*</b>@endif</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" maxlength="{{ in_array($field,['mobile','whatsapp']) ? 10 : 150 }}" class="form-control @error($field) is-invalid @enderror" @required($required)>@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endforeach<div class="col-12"><label for="address" class="form-label">Full Address (Optional)</label><textarea id="address" name="address" rows="2" class="form-control">{{ old('address') }}</textarea></div><div class="col-12"><label for="details" class="form-label">Additional Details (Optional)</label><textarea id="details" name="details" rows="3" class="form-control">{{ old('details') }}</textarea></div></div></section>
@include('frontend.request.partials.final-form-services')
@include('frontend.request.partials.final-form-documents')
@include('frontend.request.partials.final-form-review')
</form></div></section>
@endsection
