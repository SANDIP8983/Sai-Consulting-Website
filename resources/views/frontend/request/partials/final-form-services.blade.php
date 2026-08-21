<section class="request-panel premium-card p-4 d-none" data-step="2">
    <h2>Select One or Multiple Services</h2>
    <div class="row g-3">
        @foreach($services as $service)
            <div class="col-md-6 col-xl-4">
                <label class="service-choice-card h-100">
                    <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="service-choice-input" @checked(in_array((int) $service->id, $selectedIds ?? [], true))>
                    <strong>{{ $service->name_gu }}</strong><small>{{ $service->name_en }}</small>
                    <dl>
                        @if(! is_null($service->service_fee))<div><dt>Professional Fee</dt><dd>₹{{ number_format((float) $service->service_fee, 2) }} થી શરૂ — શરતો લાગુ</dd></div>@endif
                        <div><dt>GST</dt><dd>{{ $service->gst_rate }}% Extra</dd></div>
                        <div><dt>Estimated Days</dt><dd>{{ $service->estimated_days ?: 'After review' }}</dd></div>
                        <div><dt>Government Charges</dt><dd>INR {{ number_format((float) ($service->activeGovernmentChargeItems->sum('amount') ?: $service->government_charges), 2) }}</dd></div>
                        <div><dt>Required Documents</dt><dd>{{ $service->activeRequiredDocuments->count() }}</dd></div>
                    </dl>
                </label>
            </div>
        @endforeach
    </div>
    @if($services->contains(fn ($service) => ! is_null($service->service_fee)))<p class="small text-muted mt-3 mb-0">દર્શાવેલ ફી મૂળભૂત ફી છે. મિલકત, સર્વે નંબર તથા કામના વ્યાપ મુજબ ફીમાં ફેરફાર થઈ શકે છે.</p>@endif
    <p class="small text-muted mt-2 mb-0">દર્શાવેલ સમય જરૂરી માહિતી અને દસ્તાવેજો ઉપલબ્ધ થયા પછીનો અંદાજિત કાર્ય સમય છે. કેસની પરિસ્થિતિ મુજબ સમયમાં ફેરફાર થઈ શકે છે.</p>
    <div class="fee-summary-card mt-4"><h3>Estimated Fee Summary</h3><div><span>Professional Charges</span><strong id="professional-total">INR 0</strong></div><div><span>GST (Extra)</span><strong id="gst-total">INR 0</strong></div><div><span>Government Charges</span><strong id="government-total">INR 0</strong></div><div class="grand-total"><span>Estimated Payable Amount</span><strong id="grand-total">INR 0</strong></div><p>Government charges are payable as applicable and may vary according to Government rules.</p></div>
</section>
