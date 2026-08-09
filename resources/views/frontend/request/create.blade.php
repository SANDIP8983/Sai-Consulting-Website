@extends('layouts.app')
@section('title', 'Online Service Request | Sai Consulting')
@section('robots', 'noindex, follow, noarchive')
@section('content')
@php($selectedIds = array_map('intval', old('service_ids', array_filter([old('service_id'), request('service')]))))
<section class="py-5 text-white" style="background:linear-gradient(135deg,#082f6b,#0b5ed7)"><div class="container"><span class="eyebrow"><span></span>Secure Production Request</span><h1 class="fw-bold">સેવા માટે ઓનલાઇન અરજી</h1><p>Select services, review charges, and send available property documents.</p></div></section>
<section class="py-5 bg-light"><div class="container">
@if($errors->any())<div class="alert alert-danger" role="alert" aria-labelledby="request-errors-title"><strong id="request-errors-title">Please correct the highlighted information.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="request-stepper mb-4" role="list" aria-label="Request form progress">@foreach(['Customer','Services & Fees','Documents','Review'] as $label)<div class="request-step {{ $loop->first ? 'active' : '' }}" role="listitem"><span aria-hidden="true">{{ $loop->iteration }}</span><small>{{ $label }}</small></div>@endforeach</div>
<form id="final-request-form" method="POST" action="{{ route('request.store') }}" enctype="multipart/form-data">@csrf
<section class="request-panel premium-card p-4 p-lg-5" data-step="1"><span class="detail-kicker">Step 1</span><h2>ગ્રાહકની માહિતી <small>Customer Information</small></h2><div class="row g-3 mt-2">@foreach([['name','Full Name','text',true],['mobile','Mobile Number','text',true],['whatsapp','WhatsApp (Optional)','text',false],['email','Email (Optional)','email',false]] as [$field,$label,$type,$required])<div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }} @if($required)<b class="text-danger">*</b>@endif</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" maxlength="{{ in_array($field,['mobile','whatsapp']) ? 10 : 150 }}" class="form-control @error($field) is-invalid @enderror" @required($required)>@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endforeach<div class="col-12"><label for="address" class="form-label">Full Address (Optional)</label><textarea id="address" name="address" rows="2" class="form-control">{{ old('address') }}</textarea></div><div class="col-12"><label for="details" class="form-label">Additional Details (Optional)</label><textarea id="details" name="details" rows="3" class="form-control">{{ old('details') }}</textarea></div></div><hr class="my-4"><div id="property-details-section"><h2 class="h4">મિલકતની વિગતો <small>Property Details</small></h2><p class="small text-muted">Required when a selected service is property-related.</p><div class="row g-3 mt-2">@foreach([['property_village','Property Village / મિલકતનું ગામ'],['property_taluka','Property Taluka / મિલકતનો તાલુકો'],['property_district','Property District / મિલકતનો જિલ્લો'],['survey_numbers','Survey / Block No.'],['khata_number','Khata No.'],['tp_number','TP No. (Optional)'],['final_plot_number','Final Plot No. (Optional)'],['revenue_village','Revenue Village (where applicable)']] as [$field,$label])<div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" class="form-control property-field @error($field) is-invalid @enderror">@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endforeach<div class="col-12"><label for="property_address_remarks" class="form-label">Property Address / Remarks</label><textarea id="property_address_remarks" name="property_address_remarks" rows="3" class="form-control">{{ old('property_address_remarks') }}</textarea></div></div></div></section>
@include('frontend.request.partials.final-form-services')
@include('frontend.request.partials.final-form-documents')
@include('frontend.request.partials.final-form-review')
</form></div></section>
@endsection
@push('styles')
<style>#property-details-section{display:block!important}</style>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const review = document.getElementById('request-review');
    const value = id => document.getElementById(id)?.value?.trim() ?? '';
    const escape = text => { const node = document.createElement('span'); node.textContent = text; return node.innerHTML; };
    new MutationObserver(() => {
        const heading = [...review.querySelectorAll('h3')].find(node => node.textContent.trim() === 'Property Details');
        if (! heading) return;
        const card = heading.closest('.review-card');
        if (card.querySelector('[data-property-review]')) return;
        const location = [value('property_village'), value('property_taluka'), value('property_district')].filter(Boolean).join(', ');
        heading.insertAdjacentHTML('afterend', `<div data-property-review><strong>${escape(location || 'Not provided')}</strong><div>Survey / Block: ${escape(value('survey_numbers') || 'Not provided')}</div>${value('khata_number') ? `<div>Khata Number: ${escape(value('khata_number'))}</div>` : ''}</div>`);
        heading.nextElementSibling?.nextElementSibling?.remove();
    }).observe(review, { childList: true });
});
</script>
@endpush
