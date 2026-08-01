@if($customerRequest->requestServices->contains(fn($item) => $item->pricing_locked_at))
<div class="premium-card p-4 mt-4">
    <h3 class="h5">Payment Summary</h3>
    <div class="alert alert-success">તમારી અરજી મંજૂર થઈ છે.<br>કૃપા કરીને દર્શાવેલી UPI અથવા Bank Transfer વિગતો મુજબ Payment પૂર્ણ કરો.<br>જરૂરી હોય તો Email અથવા WhatsApp દ્વારા સંપર્ક કરો.</div>
    @foreach($customerRequest->requestServices->whereNotNull('pricing_locked_at') as $item)
    <div class="border rounded-3 p-3 mb-3"><strong>{{ $item->service->name_gu }} <small class="text-muted">{{ $item->service->name_en }}</small></strong>
        <div class="table-responsive mt-2"><table class="table table-sm mb-0"><tbody>
            <tr><td>Professional Fee</td><td class="text-end">₹{{ number_format((float)$item->net_professional_fee,2) }}</td></tr>
            <tr><td>GST</td><td class="text-end">₹{{ number_format((float)$item->gst_amount,2) }}</td></tr>
            @foreach($item->government_charges_snapshot ?? [] as $charge)<tr><td>{{ $charge['name'] }}</td><td class="text-end">₹{{ number_format((float)$charge['amount'],2) }}</td></tr>@endforeach
            <tr class="table-primary"><th>Grand Total</th><th class="text-end">₹{{ number_format((float)$item->final_total,2) }}</th></tr>
        </tbody></table></div>
    </div>
    @endforeach
</div>
@endif
