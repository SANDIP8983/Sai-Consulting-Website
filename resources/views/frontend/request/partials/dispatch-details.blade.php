@php
$publicMethods=['whatsapp'=>'Sent through WhatsApp','email'=>'Sent through Email','office_collection'=>'Office Collection','hand_delivery'=>'Hand Delivery','courier'=>'Courier','speed_post'=>'Speed Post','rpad'=>'Registered Post','other'=>'Other','india_post_registered'=>'Registered Post','india_post_speed_post'=>'Speed Post'];
$publicStatuses=['prepared'=>'Prepared','not_dispatched'=>'Prepared','dispatched'=>'Dispatched','in_transit'=>'In Transit','delivered'=>'Delivered','collected'=>'Collected','failed_returned'=>'Delivery unsuccessful / Returned','cancelled'=>'Cancelled'];
@endphp
@if($customerRequest->dispatches->isNotEmpty())
<section class="tracking-card premium-card mb-4"><div class="tracking-card-title"><span class="icon-box"><i class="bi bi-truck"></i></span><div><h3>મોકલવાની વિગતો</h3><p>Dispatch &amp; Delivery</p></div></div>
<div class="vstack gap-3">@foreach($customerRequest->dispatches as $dispatch)<article class="border-bottom pb-3"><dl class="row g-1 small mb-0">
<dt class="col-5 text-muted">Dispatch Method</dt><dd class="col-7 fw-semibold">{{ $publicMethods[$dispatch->dispatch_method]??str($dispatch->dispatch_method)->headline() }}</dd>
<dt class="col-5 text-muted">Status</dt><dd class="col-7">{{ $publicStatuses[$dispatch->dispatch_status]??str($dispatch->dispatch_status)->headline() }}</dd>
@if($dispatch->dispatch_date)<dt class="col-5 text-muted">{{ $dispatch->dispatch_method==='hand_delivery'?'Hand-over Date':'Dispatch Date' }}</dt><dd class="col-7">{{ \App\Support\IndiaDateTime::format($dispatch->dispatch_date) }} IST</dd>@endif
@if(filled($dispatch->carrier_name))<dt class="col-5 text-muted">Courier / Postal / Carrier</dt><dd class="col-7">{{ $dispatch->carrier_name }}</dd>@endif
@if(filled($dispatch->tracking_number))<dt class="col-5 text-muted">Tracking / Consignment No.</dt><dd class="col-7"><code class="user-select-all fs-6 text-break">{{ $dispatch->tracking_number }}</code></dd>@endif
@if($dispatch->delivered_at)<dt class="col-5 text-muted">Delivered Date</dt><dd class="col-7">{{ \App\Support\IndiaDateTime::format($dispatch->delivered_at) }} IST</dd>@endif
@if($dispatch->collected_at)<dt class="col-5 text-muted">Collected Date</dt><dd class="col-7">{{ \App\Support\IndiaDateTime::format($dispatch->collected_at) }} IST</dd>@endif
@if($customerRequest->closed_at)<dt class="col-5 text-muted">Case Closed Date</dt><dd class="col-7">{{ \App\Support\IndiaDateTime::format($customerRequest->closed_at) }} IST</dd>@endif
</dl>@if(filled($dispatch->tracking_url))<a href="{{ $dispatch->tracking_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary mt-2">Track Shipment</a>@endif @if(filled($dispatch->customer_remark))<div class="tracking-document-note mt-2"><i class="bi bi-chat-left-text"></i> {{ $dispatch->customer_remark }}</div>@endif</article>@endforeach</div>
</section>
@elseif(in_array($customerRequest->status,['completed','dispatched','delivered','closed'],true))<div class="dispatch-card premium-card mb-4"><i class="bi bi-send-check"></i><span><small>Dispatch Information</small><strong>Dispatch details will appear here when available.</strong></span></div>@endif
