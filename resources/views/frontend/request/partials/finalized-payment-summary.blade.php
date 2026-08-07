@if($customerRequest->billing)
<div class="premium-card p-4 mt-4"><h3 class="h5">Payment Summary</h3>
    <div class="table-responsive"><table class="table"><tbody>
        <tr><th>Total Professional Fee</th><th class="text-end">₹{{ number_format((float)$customerRequest->billing->total_original_professional_fee,2) }}</th></tr>
        @if((float)$customerRequest->billing->discount_amount > 0)<tr><td>Discount</td><td class="text-end text-success">− ₹{{ number_format((float)$customerRequest->billing->discount_amount,2) }}</td></tr>@endif
        <tr><td>Net Professional Fee</td><td class="text-end">₹{{ number_format((float)$customerRequest->billing->net_professional_fee,2) }}</td></tr>
        <tr><td>GST ({{ number_format((float)$customerRequest->billing->gst_rate,2) }}%)</td><td class="text-end">₹{{ number_format((float)$customerRequest->billing->gst_amount,2) }}</td></tr>
        @foreach($customerRequest->billing->charges as $charge)<tr><td>{{ $charge->name }}</td><td class="text-end">₹{{ number_format((float)$charge->amount,2) }}</td></tr>@endforeach
        <tr><th>Government Charges Total</th><th class="text-end">₹{{ number_format((float)$customerRequest->billing->government_charges_total,2) }}</th></tr>
        <tr class="table-primary"><th>Grand Total</th><th class="text-end">₹{{ number_format((float)$customerRequest->billing->grand_total,2) }}</th></tr>
    </tbody></table></div>
</div>
@elseif($customerRequest->requestServices->contains(fn($item) => $item->pricing_locked_at))
<div class="premium-card p-4 mt-4"><h3 class="h5">Payment Summary</h3>
    @foreach($customerRequest->requestServices->whereNotNull('pricing_locked_at') as $item)<div class="border rounded-3 p-3 mb-3"><strong>{{ $item->service?->name_gu }} <small class="text-muted">{{ $item->service?->name_en }}</small></strong><div class="table-responsive mt-2"><table class="table table-sm mb-0"><tbody><tr><td>Professional Fee</td><td class="text-end">₹{{ number_format((float)$item->net_professional_fee,2) }}</td></tr><tr><td>GST</td><td class="text-end">₹{{ number_format((float)$item->gst_amount,2) }}</td></tr>@foreach($item->government_charges_snapshot ?? [] as $charge)<tr><td>{{ $charge['name'] }}</td><td class="text-end">₹{{ number_format((float)$charge['amount'],2) }}</td></tr>@endforeach<tr class="table-primary"><th>Grand Total</th><th class="text-end">₹{{ number_format((float)$item->final_total,2) }}</th></tr></tbody></table></div></div>@endforeach
</div>
@endif
