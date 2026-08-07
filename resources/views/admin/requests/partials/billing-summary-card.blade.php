@unless($billingSummary['frozen'])<div class="alert alert-warning"><strong>Billing not frozen.</strong> The request estimate is not a final payable amount. Accept or reject every selected service, then approve and freeze billing.</div>@endunless
<div class="row g-3 mb-4">
@foreach([
    ['Professional Fee',$billingSummary['professional_fee']],['Discount',$billingSummary['discount']],['GST',$billingSummary['gst']],
    ['Government Charges',$billingSummary['government_charges']],['Grand Total',$billingSummary['grand_total']],
    ['Paid Amount',$billingSummary['paid']],['Balance',$billingSummary['balance']],
] as [$label,$amount])
<div class="col-6 col-lg-3"><small class="text-muted d-block">{{ $label }}</small><strong>{{ $amount === null ? '—' : '₹'.number_format($amount,2) }}</strong></div>
@endforeach
<div class="col-6 col-lg-3"><small class="text-muted d-block">Pricing Locked</small><span class="badge text-bg-{{ $billingSummary['locked']?'success':'warning' }}">{{ $billingSummary['locked']?'Yes':'No' }}</span></div>
<div class="col-6 col-lg-3"><small class="text-muted d-block">Payment Status</small>@include('admin.requests.partials.payment-badge',['status'=>$billingSummary['payment_status']])</div>
</div>
