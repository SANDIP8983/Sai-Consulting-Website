@php
    $latestDispatch = $customerRequest->dispatches->first();
    $dispatchStatusLabels = [
        'not_dispatched' => ['મોકલવાનું બાકી', 'Not Dispatched'],
        'dispatched' => ['મોકલી આપેલ', 'Dispatched'],
        'delivered' => ['પહોંચાડેલ', 'Delivered'],
    ];
    $dispatchMethodLabels = [
        'office_collection' => 'Office Collection',
        'hand_delivery' => 'Hand Delivery',
        'india_post_registered' => 'India Post Registered',
        'india_post_speed_post' => 'India Post Speed Post',
        'courier' => 'Courier',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'other' => 'Other',
    ];
@endphp

@if($latestDispatch)
    <div class="tracking-side-card premium-card mb-4">
        <div class="tracking-card-title">
            <span class="icon-box"><i class="bi bi-send-check"></i></span>
            <div><h3>મોકલવાની માહિતી</h3><p>Dispatch Information</p></div>
        </div>
        <dl class="row g-2 small mb-0">
            <dt class="col-5 text-muted">Status</dt>
            <dd class="col-7 fw-semibold">{{ $dispatchStatusLabels[$latestDispatch->dispatch_status][0] ?? str($latestDispatch->dispatch_status)->headline() }} <span class="d-block fw-normal text-muted">{{ $dispatchStatusLabels[$latestDispatch->dispatch_status][1] ?? '' }}</span></dd>
            <dt class="col-5 text-muted">Method</dt>
            <dd class="col-7 fw-semibold">{{ $dispatchMethodLabels[$latestDispatch->dispatch_method] ?? str($latestDispatch->dispatch_method)->headline() }}</dd>
            <dt class="col-5 text-muted">Date</dt>
            <dd class="col-7 fw-semibold">{{ $latestDispatch->dispatch_date->format('d M Y') }}</dd>
            @if($latestDispatch->tracking_number)
                <dt class="col-5 text-muted">Tracking No.</dt>
                <dd class="col-7 fw-semibold text-break">{{ $latestDispatch->tracking_number }}</dd>
            @endif
            @if($latestDispatch->carrier_name)
                <dt class="col-5 text-muted">Carrier</dt>
                <dd class="col-7 fw-semibold">{{ $latestDispatch->carrier_name }}</dd>
            @endif
        </dl>
        @if($latestDispatch->customer_remark)
            <div class="tracking-document-note mt-3"><i class="bi bi-chat-left-text"></i> {{ $latestDispatch->customer_remark }}</div>
        @endif
    </div>
@elseif($dispatchUpdate || in_array($customerRequest->status, ['dispatched', 'completed', 'archived'], true))
    <div class="dispatch-card premium-card mb-4">
        <i class="bi bi-send-check"></i>
        <span><small>Dispatch Information</small><strong>તમારી સેવા મોકલી આપવામાં આવી છે.</strong>@if($dispatchUpdate)<em>{{ $dispatchUpdate->created_at->format('d M Y, g:i A') }}</em>@endif @if($dispatchUpdate?->remarks)<p>{{ $dispatchUpdate->remarks }}</p>@endif</span>
    </div>
@endif
