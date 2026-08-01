@php
    $dispatchStatuses = ['not_dispatched', 'dispatched', 'delivered'];
    $dispatchMethods = [
        'office_collection' => 'Office Collection',
        'hand_delivery' => 'Hand Delivery',
        'india_post_registered' => 'India Post Registered',
        'india_post_speed_post' => 'India Post Speed Post',
        'courier' => 'Courier',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'other' => 'Other',
    ];
    $latestDispatch = $customerRequest->dispatches->first();
    $dispatchEligible = $customerRequest->file_number
        && $customerRequest->payment_status === 'received'
        && in_array($customerRequest->status, ['ready_for_registration', 'dispatched', 'completed', 'archived'], true)
        && (!$customerRequest->processing || in_array($customerRequest->processing->processing_stage, ['ready_for_dispatch','dispatched','completed'], true));
@endphp

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold">Dispatch Management</span>
        @if($latestDispatch)
            <span class="badge text-bg-{{ $latestDispatch->dispatch_status === 'delivered' ? 'success' : ($latestDispatch->dispatch_status === 'dispatched' ? 'primary' : 'secondary') }}">
                {{ str($latestDispatch->dispatch_status)->replace('_', ' ')->title() }}
            </span>
        @endif
    </div>
    <div class="card-body">
        @if($dispatchEligible)
            <form method="POST" action="{{ route('admin.requests.dispatches.store', $customerRequest) }}" data-confirm-form>
                @csrf
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label for="dispatch_status" class="form-label">Dispatch Status</label>
                        <select id="dispatch_status" name="dispatch_status" class="form-select @error('dispatch_status') is-invalid @enderror" required>
                            @foreach($dispatchStatuses as $status)
                                <option value="{{ $status }}" @selected(old('dispatch_status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                        @error('dispatch_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label for="dispatch_method" class="form-label">Dispatch Method</label>
                        <select id="dispatch_method" name="dispatch_method" class="form-select @error('dispatch_method') is-invalid @enderror" required>
                            @foreach($dispatchMethods as $value => $label)
                                <option value="{{ $value }}" @selected(old('dispatch_method') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('dispatch_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="dispatch_date" class="form-label">Dispatch Date & Time</label>
                        <input id="dispatch_date" name="dispatch_date" type="datetime-local" value="{{ old('dispatch_date', now()->format('Y-m-d\TH:i')) }}" class="form-control @error('dispatch_date') is-invalid @enderror" required>
                        @error('dispatch_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label for="tracking_number" class="form-label">Tracking Number</label>
                        <input id="tracking_number" name="tracking_number" value="{{ old('tracking_number') }}" class="form-control @error('tracking_number') is-invalid @enderror" maxlength="150">
                        @error('tracking_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label for="carrier_name" class="form-label">Carrier / Service Name</label>
                        <input id="carrier_name" name="carrier_name" value="{{ old('carrier_name') }}" class="form-control @error('carrier_name') is-invalid @enderror" maxlength="150">
                        @error('carrier_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="form-text mb-2">Tracking is required for India Post and courier. Courier also requires a carrier name.</div>
                        <label for="internal_note" class="form-label">Internal Dispatch Note</label>
                        <textarea id="internal_note" name="internal_note" class="form-control @error('internal_note') is-invalid @enderror" rows="2">{{ old('internal_note') }}</textarea>
                        <div class="form-text">Never visible to the customer.</div>
                        @error('internal_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="customer_remark" class="form-label">Customer-visible Dispatch Remark</label>
                        <textarea id="customer_remark" name="customer_remark" class="form-control @error('customer_remark') is-invalid @enderror" rows="2">{{ old('customer_remark') }}</textarea>
                        @error('customer_remark')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                @error('dispatch')<div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>@enderror
                <button class="btn btn-primary w-100 mt-3"><i class="bi bi-send-check me-1"></i> Save Dispatch Update</button>
            </form>
        @else
            <div class="alert alert-light border mb-0">
                Dispatch becomes available when payment is received and the approved request is ready for registration.
            </div>
        @endif
    </div>
</div>

@if($customerRequest->dispatches->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Dispatch History</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Status</th><th>Method</th><th>Tracking / Carrier</th><th>Recorded By</th></tr></thead>
                <tbody>
                    @foreach($customerRequest->dispatches as $dispatch)
                        <tr>
                            <td>{{ $dispatch->dispatch_date->format('d M Y, g:i A') }}</td>
                            <td>{{ str($dispatch->dispatch_status)->replace('_', ' ')->title() }}</td>
                            <td>{{ $dispatchMethods[$dispatch->dispatch_method] ?? str($dispatch->dispatch_method)->headline() }}</td>
                            <td>{{ $dispatch->tracking_number ?: '—' }}@if($dispatch->carrier_name)<div class="small text-muted">{{ $dispatch->carrier_name }}</div>@endif</td>
                            <td>{{ $dispatch->performedBy?->name ?? 'System' }}</td>
                        </tr>
                        @if($dispatch->internal_note || $dispatch->customer_remark)
                            <tr class="table-light"><td></td><td colspan="4"><small>@if($dispatch->internal_note)<strong>Internal:</strong> {{ $dispatch->internal_note }} @endif @if($dispatch->customer_remark)<strong class="ms-2">Customer visible:</strong> {{ $dispatch->customer_remark }}@endif</small></td></tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
