<div class="row g-3 mb-4">
@foreach([
    ['Professional Fee',$billingSummary['professional_fee']],['Discount',$billingSummary['discount']],['GST',$billingSummary['gst']],
    ['Government Charges',$billingSummary['government_charges']],['Grand Total',$billingSummary['grand_total']],
    ['Paid Amount',$billingSummary['paid']],['Balance',$billingSummary['balance']],
] as [$label,$amount])
<div class="col-6 col-lg-3"><small class="text-muted d-block">{{ $label }}</small><strong>₹{{ number_format($amount,2) }}</strong></div>
@endforeach
<div class="col-6 col-lg-3"><small class="text-muted d-block">Pricing Locked</small><span class="badge text-bg-{{ $billingSummary['locked']?'success':'warning' }}">{{ $billingSummary['locked']?'Yes':'No' }}</span></div>
<div class="col-6 col-lg-3"><small class="text-muted d-block">Payment Status</small>@include('admin.requests.partials.payment-badge',['status'=>$customerRequest->payment_status])</div>
</div>
