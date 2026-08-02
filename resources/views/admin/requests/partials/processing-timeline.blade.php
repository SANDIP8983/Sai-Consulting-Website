@if($customerRequest->processing)
@php($paymentBlocked = $customerRequest->processing->requires_payment_before_processing && $customerRequest->payment_status !== 'received')
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">પ્રક્રિયા સમયરેખા · Processing Timeline</div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-4">
            @foreach(\App\Services\FileDocumentProcessingService::STAGES as $stage)
                <span class="badge {{ $stage === $customerRequest->processing->processing_stage ? 'text-bg-primary' : ($customerRequest->processingHistory->contains('to_stage', $stage) ? 'text-bg-success' : 'text-bg-light') }}">{{ str($stage)->replace('_', ' ')->title() }}</span>
            @endforeach
        </div>

        @if(!$customerRequest->usesChecklistWorkflow() && $processingTransitions)
            @if($paymentBlocked)
                <div class="alert alert-warning"><strong>Payment Pending.</strong> Payment must be confirmed before processing can continue.</div>
            @endif
            <form method="POST" action="{{ route('admin.requests.processing.stage.update', $customerRequest) }}" class="border-top pt-3">
                @csrf @method('PATCH')
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Next Stage</label>
                        <select name="processing_stage" class="form-select @error('processing_stage') is-invalid @enderror" @disabled($paymentBlocked)>
                            @foreach($processingTransitions as $stage)
                                <option value="{{ $stage }}" @selected(old('processing_stage') === $stage)>{{ str($stage)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                        @error('processing_stage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Stage Remark</label>
                        <input name="remarks" value="{{ old('remarks') }}" class="form-control @error('remarks') is-invalid @enderror" maxlength="2000" @disabled($paymentBlocked)>
                        @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if($customerRequest->processing->processing_stage === 'customer_verification_pending')
                        <div class="col-md-4">
                            <label class="form-label">Customer Verification Date</label>
                            <input type="date" name="customer_verification_at" value="{{ old('customer_verification_at', $customerRequest->processing->customer_verification_at?->toDateString()) }}" class="form-control @error('customer_verification_at') is-invalid @enderror" @disabled($paymentBlocked)>
                            @error('customer_verification_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif
                    <div class="col-12">
                        <div class="form-check">
                            <input id="processing_visible" name="is_visible_to_customer" value="1" type="checkbox" class="form-check-input" @disabled($paymentBlocked)>
                            <label for="processing_visible" class="form-check-label">Customer-visible remark</label>
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary mt-3" @disabled($paymentBlocked)>Advance Processing Stage</button>
            </form>
        @endif

        <div class="vstack gap-3 mt-4">
            @foreach($customerRequest->processingHistory as $history)
                <article class="card border-0 border-start border-4 border-primary shadow-sm"><div class="card-body py-3"><div class="d-flex flex-column flex-md-row justify-content-between gap-2"><div><strong>{{ str($history->to_stage)->replace('_', ' ')->title() }}</strong><span class="badge text-bg-light border ms-1">{{ str($history->from_stage)->headline() }} → {{ str($history->to_stage)->headline() }}</span></div><time class="small text-muted">{{ $history->created_at->format('d M Y') }} · {{ $history->created_at->format('g:i A') }}</time></div><div class="small text-muted mt-1">Admin: {{ $history->changedBy?->name ?? 'System' }} · Action: Processing stage changed</div>@if($history->remarks)<p class="mb-0 mt-2">{{ $history->remarks }}</p>@endif</div></article>
            @endforeach
        </div>
    </div>
</div>
@endif
