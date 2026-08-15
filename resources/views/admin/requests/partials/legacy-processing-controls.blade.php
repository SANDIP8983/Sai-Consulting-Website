@if($customerRequest->processing && $processingTransitions)
@php($paymentBlocked = $customerRequest->processing->requires_payment_before_processing && $billingState->paymentStatus !== 'paid')
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold">Legacy Processing Controls</div>
    <div class="card-body">
        @if($paymentBlocked)
            <div class="alert alert-warning"><strong>Payment Pending.</strong> Payment must be confirmed before processing can continue.</div>
        @endif
        <form method="POST" action="{{ route('admin.requests.processing.stage.update', $customerRequest) }}">
            @csrf @method('PATCH')
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Next Stage</label><select name="processing_stage" class="form-select @error('processing_stage') is-invalid @enderror" @disabled($paymentBlocked)>@foreach($processingTransitions as $stage)<option value="{{ $stage }}" @selected(old('processing_stage') === $stage)>{{ str($stage)->replace('_', ' ')->title() }}</option>@endforeach</select>@error('processing_stage')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-8"><label class="form-label">Stage Remark</label><input name="remarks" value="{{ old('remarks') }}" class="form-control @error('remarks') is-invalid @enderror" maxlength="2000" @disabled($paymentBlocked)>@error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @if($customerRequest->processing->processing_stage === 'customer_verification_pending')<div class="col-md-4"><label class="form-label">Customer Verification Date</label><input type="date" name="customer_verification_at" value="{{ old('customer_verification_at', $customerRequest->processing->customer_verification_at?->toDateString()) }}" class="form-control @error('customer_verification_at') is-invalid @enderror" @disabled($paymentBlocked)>@error('customer_verification_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endif
                <div class="col-12"><div class="form-check"><input id="processing_visible" name="is_visible_to_customer" value="1" type="checkbox" class="form-check-input" @disabled($paymentBlocked)><label for="processing_visible" class="form-check-label">Customer-visible remark</label></div></div>
            </div>
            <button class="btn btn-primary mt-3" @disabled($paymentBlocked)>Advance Processing Stage</button>
        </form>
    </div>
</div>
@endif
