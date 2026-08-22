@php
    $receivedPayment = $customerRequest->payments->firstWhere('payment_status', 'received');
    $publicPaymentRemarks = $customerRequest->payments->whereNotNull('customer_remark');
@endphp
<div class="tracking-side-card premium-card mb-4">
    <div class="tracking-card-title"><span class="icon-box"><i class="bi bi-credit-card"></i></span><div><h3>ચુકવણીની વિગતો</h3><p>Payment Details</p></div></div>
    <dl class="mb-0">
        <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><dt>Final Fee</dt><dd class="mb-0 fw-semibold">{{ $customerRequest->public_billing_state->grandTotal === null ? 'Billing Pending Approval' : '₹'.number_format($customerRequest->public_billing_state->grandTotal, 2) }}</dd></div>
        <div class="d-flex justify-content-between gap-3 py-2"><dt>Status</dt><dd class="mb-0 fw-semibold">{{ str($customerRequest->public_billing_state->paymentStatus)->replace('_', ' ')->title() }}</dd></div>
        @if($receivedPayment)
            <div class="d-flex justify-content-between gap-3 py-2 border-top"><dt>Method</dt><dd class="mb-0 fw-semibold">{{ str($receivedPayment->payment_method)->replace('_', ' ')->title() }}</dd></div>
            <div class="d-flex justify-content-between gap-3 py-2"><dt>Payment Date</dt><dd class="mb-0 fw-semibold">{{ \App\Support\IndiaDateTime::format($receivedPayment->received_at, 'd M Y') }}</dd></div>
        @endif
    </dl>
    @if($customerRequest->public_billing_state->paymentStatus === 'not_required')
        <div class="alert alert-success mt-3 mb-0"><strong>ચૂકવણી જરૂરી નથી.</strong><br>No payment is required. Your request can proceed to processing.</div>
    @endif
    @foreach($publicPaymentRemarks as $payment)
        <div class="tracking-document-note mt-2"><i class="bi bi-chat-left-text"></i> {{ $payment->customer_remark }}</div>
    @endforeach
</div>
@if(($upiPayment ?? null) && $customerRequest->paymentSubmission?->status === 'pending')
    <div class="tracking-side-card premium-card mb-4 border border-warning"><div class="tracking-card-title"><span class="icon-box"><i class="bi bi-hourglass-split"></i></span><div><h3>ચકાસણી બાકી</h3><p>Awaiting Payment Verification</p></div></div><p class="mb-0">Payment details submitted. Our team will verify the payment. This does not mean payment is confirmed yet.</p></div>
@elseif($upiPayment ?? null)
    <div class="tracking-side-card premium-card mb-4 border border-primary">
        <div class="tracking-card-title"><span class="icon-box"><i class="bi bi-qr-code"></i></span><div><h3>UPI દ્વારા ચૂકવણી</h3><p>Pay via UPI</p></div></div>
        @if($customerRequest->paymentSubmission?->status === 'rejected')<div class="alert alert-warning">The previous payment details could not be verified. Please submit corrected UTR/proof.</div>@endif
        <div class="text-center mb-3">
            <div class="fw-semibold mb-2">Dynamic UPI QR / ચોક્કસ રકમ માટે QR</div>
            <img src="{{ $upiPayment['dynamic_qr_url'] }}" data-static-qr="{{ $upiPayment['static_qr_url'] }}" onerror="this.onerror=null;this.src=this.dataset.staticQr" alt="Request-specific UPI QR code for the exact frozen amount" class="img-fluid border rounded p-2" style="max-width:240px">
            <p class="small text-muted mt-2 mb-0">આ QR સ્કેન કરતાં ચુકવણીની રકમ આપમેળે ભરાઈ આવશે. ચુકવણી કરતાં પહેલાં રકમ અને Payee ચકાસો.</p>
        </div>
        <dl>
            <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><dt>Reference</dt><dd class="mb-0 text-end">{{ $customerRequest->reference_no }}</dd></div>
            @if($customerRequest->file_number)<div class="d-flex justify-content-between gap-3 py-2 border-bottom"><dt>File Number</dt><dd class="mb-0 text-end">{{ $customerRequest->file_number }}</dd></div>@endif
            <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><dt>Frozen Grand Total</dt><dd class="mb-0 fw-semibold">₹{{ number_format($upiPayment['grand_total'], 2) }}</dd></div>
            <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><dt>Exact Amount to Pay</dt><dd class="mb-0 fw-bold text-primary">₹{{ number_format($upiPayment['amount_to_pay'], 2) }}</dd></div>
            <div class="d-flex justify-content-between gap-3 py-2 border-bottom"><dt>UPI ID</dt><dd class="mb-0 text-break text-end">{{ $upiPayment['upi_id'] }}</dd></div>
            <div class="d-flex justify-content-between gap-3 py-2"><dt>Payee</dt><dd class="mb-0 text-end">{{ $upiPayment['payee_name'] }}</dd></div>
        </dl>
        <p class="small">{{ $upiPayment['instructions'] ?: 'Scan the QR code or pay using the UPI ID. Then enter your UTR / Transaction ID below.' }}</p>
        <form method="POST" action="{{ route('request.track.payment-submission', $customerRequest) }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3"><label class="form-label" for="utr_reference">UTR / Transaction ID</label><input class="form-control" id="utr_reference" name="utr_reference" value="{{ old('utr_reference') }}" required minlength="6" maxlength="100" autocomplete="off"></div>
            @if($upiPayment['proof_upload_allowed'])<div class="mb-3"><label class="form-label" for="proof">Payment Screenshot / Proof <span class="text-muted">(optional)</span></label><input class="form-control" type="file" id="proof" name="proof" accept="image/jpeg,image/png,application/pdf"><div class="form-text">JPG, JPEG, PNG or PDF; maximum 5 MB.</div></div>@endif
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="declaration" value="1" id="payment_declaration" required><label class="form-check-label" for="payment_declaration">I confirm that the payment details entered are correct.</label></div>
            <button class="btn btn-primary w-100" type="submit">Submit Payment Details</button>
        </form>
        <div class="form-text mt-2">Payment will be marked Paid only after manual verification by Sai Consulting.</div>
    </div>
@endif
