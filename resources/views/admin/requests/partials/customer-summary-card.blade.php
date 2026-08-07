<div class="row g-3">
@foreach([
    ['Customer Name',$customerRequest->name],['Mobile',$customerRequest->mobile],['Email',$customerRequest->email ?: 'Not provided'],
    ['Reference Number',$customerRequest->reference_no],['File Number',$customerRequest->file_number ?: 'Not assigned'],
    ['Submission Date',$customerRequest->created_at->format('d M Y, g:i A')],['Request Source',$customerRequest->isOffline()?'Offline':'Online'],
    ['Village',$customerRequest->property_village ?: $customerRequest->village ?: 'Not provided'],['Survey Number',$customerRequest->survey_numbers ?: 'Not provided'],
    ['Current Status',str($customerRequest->status)->headline()],
] as [$label,$value])
<div class="col-sm-6 col-xl-4"><small class="text-muted d-block">{{ $label }}</small><strong>{{ $value }}</strong></div>
@endforeach
</div>
