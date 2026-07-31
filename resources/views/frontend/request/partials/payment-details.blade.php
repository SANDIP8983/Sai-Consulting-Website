@php
    $receivedPayment = $customerRequest->payments->firstWhere('payment_status', 'received');
    $publicPaymentRemarks = $customerRequest->payments->whereNotNull('customer_remark');
@endphp
<div class="tracking-side-card premium-card mb-4">
    <div class="tracking-card-title"><span class="icon-box"><i class="bi bi-credit-card"></i></span><div><h3>ચુકવણીની વિગતો</h3><p>Payment Details</p></div></div>
    <dl class="mb-0">
        <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><dt>Final Fee</dt><dd class="mb-0 fw-semibold">₹{{ number_format((float) $customerRequest->amount_due, 2) }}</dd></div>
        <div class="d-flex justify-content-between gap-3 py-2"><dt>Status</dt><dd class="mb-0 fw-semibold">{{ str($customerRequest->payment_status)->replace('_', ' ')->title() }}</dd></div>
        @if($receivedPayment)
            <div class="d-flex justify-content-between gap-3 py-2 border-top"><dt>Method</dt><dd class="mb-0 fw-semibold">{{ str($receivedPayment->payment_method)->replace('_', ' ')->title() }}</dd></div>
            <div class="d-flex justify-content-between gap-3 py-2"><dt>Payment Date</dt><dd class="mb-0 fw-semibold">{{ $receivedPayment->received_at->format('d M Y') }}</dd></div>
        @endif
    </dl>
    @foreach($publicPaymentRemarks as $payment)
        <div class="tracking-document-note mt-2"><i class="bi bi-chat-left-text"></i> {{ $payment->customer_remark }}</div>
    @endforeach
</div>
