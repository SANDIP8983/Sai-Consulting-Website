@extends('layouts.app')
@section('title', 'વિનંતીની સ્થિતિ તપાસો | Track Request | Sai Consulting')
@section('description', 'તમારા રેફરન્સ નંબર અને નોંધાયેલા મોબાઇલ નંબરથી Sai Consulting વિનંતીની સ્થિતિ સુરક્ષિત રીતે તપાસો.')

@section('content')
@php
    $statusLabels = [
        'received' => ['વિનંતી મળી', 'Received'], 'under_review' => ['ચકાસણી હેઠળ', 'Under Review'],
        'need_documents' => ['વધુ દસ્તાવેજો જરૂરી', 'Need Documents'], 'approved' => ['મંજૂર', 'Approved'],
        'rejected' => ['નામંજૂર', 'Rejected'], 'payment_pending' => ['ચુકવણી બાકી', 'Payment Pending'],
        'payment_received' => ['ચુકવણી મળી', 'Payment Received'], 'in_progress' => ['પ્રક્રિયામાં', 'In Progress'],
        'draft_in_progress' => ['પ્રક્રિયામાં', 'In Progress'], 'ready_for_verification' => ['પ્રક્રિયામાં', 'In Progress'],
        'customer_approved' => ['પ્રક્રિયામાં', 'In Progress'], 'ready_for_registration' => ['પ્રક્રિયામાં', 'In Progress'],
        'completed' => ['પૂર્ણ', 'Completed'], 'dispatched' => ['મોકલી આપેલ', 'Dispatched'],
        'delivered' => ['પહોંચાડેલ', 'Delivered'], 'closed' => ['બંધ', 'Closed'], 'archived' => ['આર્કાઇવ', 'Archived'],
    ];
    $statusColors = ['received'=>'primary','under_review'=>'info','need_documents'=>'warning','approved'=>'success','rejected'=>'danger','payment_pending'=>'warning','payment_received'=>'success','in_progress'=>'info','draft_in_progress'=>'info','ready_for_verification'=>'info','customer_approved'=>'info','ready_for_registration'=>'info','completed'=>'success','dispatched'=>'secondary','delivered'=>'success','closed'=>'dark','archived'=>'dark'];
    $paymentLabels = ['billing_pending'=>['બિલિંગ બાકી','Billing Pending Approval'],'not_required'=>['જરૂરી નથી','Not Required'],'pending'=>['બાકી','Payment Pending'],'paid'=>['ચૂકવેલ','Paid'],'received'=>['ચૂકવેલ','Paid'],'partial'=>['આંશિક ચુકવણી','Partially Paid'],'failed'=>['નિષ્ફળ','Failed'],'refunded'=>['પરત','Refunded']];
@endphp

<section class="tracking-page-hero py-5">
    <div class="container py-lg-4"><div class="row align-items-center g-4">
        <div class="col-lg-7"><span class="eyebrow"><span></span>Secure Customer Tracking</span><h1>તમારી વિનંતીની સ્થિતિ તપાસો</h1><p>રેફરન્સ નંબર અને અરજી વખતે આપેલો નોંધાયેલ મોબાઇલ નંબર દાખલ કરો.</p></div>
        <div class="col-lg-5"><div class="tracking-privacy-card"><i class="bi bi-shield-lock"></i><div><strong>તમારી માહિતી સુરક્ષિત છે</strong><span>Only customer-safe information for an exact reference and mobile match is displayed.</span></div></div></div>
    </div></div>
</section>

<div class="tracking-page-body py-5"><div class="container"><div class="row justify-content-center"><div class="col-xl-10">
    @if($errors->any())<div class="alert alert-danger d-flex gap-2" role="alert" tabindex="-1"><i class="bi bi-exclamation-circle-fill"></i><span>{{ $errors->first() }}</span></div>@endif

    <form method="POST" action="{{ route('request.track.lookup') }}" class="tracking-lookup-card premium-card mb-5">@csrf
        <div class="tracking-form-heading"><span class="icon-box"><i class="bi bi-search"></i></span><div><h2>વિનંતી શોધો <small>Find Your Request</small></h2><p>Both fields must match the submitted request.</p></div></div>
        <div class="row g-3 align-items-end">
            <div class="col-lg-5"><label for="reference_no" class="form-label">રેફરન્સ નંબર <span>Reference Number</span></label><input id="reference_no" name="reference_no" value="{{ old('reference_no') }}" placeholder="SC/2026/000001" class="form-control form-control-lg @error('reference_no') is-invalid @enderror" autocomplete="off" required>@error('reference_no')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-lg-4"><label for="mobile" class="form-label">મોબાઇલ નંબર <span>Registered Mobile Number</span></label><input id="mobile" name="mobile" value="{{ old('mobile') }}" inputmode="numeric" autocomplete="tel" maxlength="10" class="form-control form-control-lg @error('mobile') is-invalid @enderror" required>@error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-lg-3"><button class="btn btn-primary btn-lg rounded-pill w-100" type="submit"><i class="bi bi-search me-1"></i> સ્થિતિ તપાસો</button></div>
        </div>
    </form>

    @isset($customerRequest)
        @php
            $publicStatus = $customerRequest->public_status;
            $isRejected = $publicStatus === 'rejected';
            $progress = (int) $customerRequest->public_progress_percentage;
            $pendingDocuments = collect($customerRequest->public_pending_documents);
            $publicDispatchStatuses = ['completed', 'dispatched', 'delivered', 'closed', 'archived'];
            $pdfTypes = [
                \App\Enums\PdfDocumentType::RequestAcknowledgement,
                \App\Enums\PdfDocumentType::CaseSummary,
            ];
            if (! $isRejected) $pdfTypes[] = \App\Enums\PdfDocumentType::PaymentSummary;
            if ($customerRequest->dispatches->isNotEmpty()) $pdfTypes[] = \App\Enums\PdfDocumentType::DispatchSlip;
            $publicStatusHistory = $isRejected
                ? $customerRequest->statusHistory->where('to_status', 'rejected')
                : $customerRequest->statusHistory;
        @endphp
        <section class="tracking-result" aria-labelledby="tracking-result-title">
            <div class="tracking-result-header">
                <div><span>Verified Request</span><h2 id="tracking-result-title">{{ $customerRequest->name }}</h2><p>{{ $customerRequest->reference_no }}@if($customerRequest->file_number)<small>File: {{ $customerRequest->file_number }}</small>@endif</p></div>
                <span class="tracking-status-badge text-bg-{{ $statusColors[$publicStatus] ?? 'secondary' }}"><i class="bi bi-circle-fill"></i><strong>{{ $statusLabels[$publicStatus][0] ?? str($publicStatus)->headline() }}</strong><small>{{ $statusLabels[$publicStatus][1] ?? '' }}</small></span>
            </div>

            @if($isRejected)<div class="alert alert-danger mt-4" role="status"><strong>This request was not approved.</strong> Please review the customer-visible remarks below or contact Sai Consulting for guidance.</div>@endif
            @if($customerRequest->status === 'archived')<div class="alert alert-secondary mt-4" role="status"><strong>This request has been archived.</strong> Its customer-visible history remains available below.</div>@endif

            <div class="tracking-summary premium-card mt-4"><div class="row g-0">
                <div class="col-sm-6 tracking-summary-item"><i class="bi bi-hash"></i><span><small>Reference Number</small><strong>{{ $customerRequest->reference_no }}</strong></span></div>
                @if($customerRequest->file_number)<div class="col-sm-6 tracking-summary-item"><i class="bi bi-folder-check"></i><span><small>File Number</small><strong>{{ $customerRequest->file_number }}</strong></span></div>@endif
                <div class="col-sm-6 tracking-summary-item"><i class="bi bi-calendar-plus"></i><span><small>Submitted Date</small><strong>{{ $customerRequest->created_at->format('d M Y') }}</strong></span></div>
                <div class="col-sm-6 tracking-summary-item"><i class="bi bi-credit-card"></i><span><small>ચુકવણી · Payment Status</small><strong>{{ $paymentLabels[$customerRequest->public_payment_status][0] ?? str($customerRequest->public_payment_status)->headline() }} <em>{{ $paymentLabels[$customerRequest->public_payment_status][1] ?? '' }}</em></strong></span></div>
                @if($customerRequest->estimated_completion_date)<div class="col-sm-6 tracking-summary-item"><i class="bi bi-calendar-check"></i><span><small>Estimated Completion</small><strong>{{ $customerRequest->estimated_completion_date->format('d M Y') }}</strong></span></div>@endif
                <div class="col-sm-6 tracking-summary-item"><i class="bi bi-clock-history"></i><span><small>Last Updated</small><strong>{{ ($customerRequest->last_status_changed_at ?? $customerRequest->updated_at)->format('d M Y') }}</strong></span></div>
            </div></div>

            @unless($isRejected)<div class="premium-card p-4 mt-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h3 class="h5 mb-1">Processing Progress</h3><p class="text-muted mb-0">Calculated from current processing records.</p></div><strong class="fs-4">{{ $progress }}%</strong></div>
                <div class="progress mt-3" role="progressbar" aria-label="Processing progress" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" style="height:10px"><div class="progress-bar" style="width:{{ $progress }}%"></div></div>
                @foreach($customerRequest->public_work_remarks as $remark)<div class="tracking-document-note mt-3"><i class="bi bi-chat-left-text"></i> {{ $remark }}</div>@endforeach
            </div>@endunless

            <div class="premium-card p-4 mt-4"><h3 class="h5 mb-3">Selected Services</h3><div class="row g-3">
                @forelse($customerRequest->requestServices as $selectedService)
                    <div class="col-md-6"><div class="border rounded-3 p-3 h-100"><strong>{{ $selectedService->service_name_gu_snapshot ?: $selectedService->service?->name_gu }}</strong><div class="text-muted">{{ $selectedService->service_name_en_snapshot ?: $selectedService->service?->name_en }}</div>@if($selectedService->status === 'approved')<div class="mt-2"><small class="text-muted">Professional Fee</small><div class="fw-semibold">₹{{ number_format($selectedService->billingProfessionalFee(), 2) }}</div></div>@elseif($selectedService->customer_decision_message)<p class="small mt-2 mb-0">{{ $selectedService->customer_decision_message }}</p>@endif</div></div>
                @empty<div class="col-12">{{ $customerRequest->service?->name_en }}</div>@endforelse
            </div></div>

            @if($customerRequest->property_village || $customerRequest->property_taluka || $customerRequest->property_district || $customerRequest->survey_numbers || $customerRequest->khata_number)
                <div class="premium-card p-4 mt-4"><h3 class="h5 mb-3">Property Details</h3><div class="row g-3"><div class="col-md-6"><small class="text-muted">Village / Taluka / District</small><div class="fw-semibold">{{ collect([$customerRequest->property_village, $customerRequest->property_taluka, $customerRequest->property_district])->filter()->implode(', ') }}</div></div>@if($customerRequest->survey_numbers)<div class="col-md-3"><small class="text-muted">Survey / Block Number(s)</small><div>{{ $customerRequest->survey_numbers }}</div></div>@endif @if($customerRequest->khata_number)<div class="col-md-3"><small class="text-muted">Khata Number</small><div>{{ $customerRequest->khata_number }}</div></div>@endif</div></div>
            @endif

            @unless($isRejected)@include('frontend.request.partials.finalized-payment-summary')@endunless

            <div class="row g-4 mt-1"><div class="col-lg-8">
                <div class="tracking-timeline-card premium-card mb-4"><div class="tracking-card-title"><span class="icon-box"><i class="bi bi-signpost-split"></i></span><div><h3>ગ્રાહક અપડેટ્સ</h3><p>Customer-visible Remarks</p></div></div><div class="public-status-timeline">
                    @forelse($publicStatusHistory->sortBy('created_at') as $history)<article class="public-timeline-item"><span class="timeline-dot text-bg-{{ $statusColors[$history->to_status] ?? 'secondary' }}"><i class="bi bi-check2"></i></span><div><div class="d-flex flex-wrap justify-content-between gap-2"><h4>{{ $statusLabels[$history->to_status][0] ?? str($history->to_status)->headline() }} <small>{{ $statusLabels[$history->to_status][1] ?? '' }}</small></h4><time>{{ $history->created_at->format('d M Y, g:i A') }}</time></div>@if($history->remarks)<p>{{ $history->remarks }}</p>@endif</div></article>
                    @empty<article class="public-timeline-item"><span class="timeline-dot text-bg-primary"><i class="bi bi-check2"></i></span><div><h4>{{ $statusLabels[$publicStatus][1] ?? str($publicStatus)->headline() }}</h4><time>{{ $customerRequest->created_at->format('d M Y, g:i A') }}</time></div></article>@endforelse
                </div>
                @unless($isRejected)@foreach($customerRequest->processingHistory as $history)@if($history->remarks)<div class="tracking-document-note mt-2"><i class="bi bi-chat-left-text"></i> {{ $history->remarks }}</div>@endif @endforeach @endunless
                @if($customerRequest->completion_customer_remark)<div class="alert alert-success mt-3 mb-0">{{ $customerRequest->completion_customer_remark }}</div>@endif
                @if($customerRequest->closure_customer_remark)<div class="alert alert-secondary mt-3 mb-0">{{ $customerRequest->closure_customer_remark }}</div>@endif
                </div>

                <div class="premium-card p-4"><h3 class="h5">Customer-safe PDFs</h3><p class="small text-muted">Links are available only in this verified tracking session.</p><div class="d-grid d-sm-flex flex-wrap gap-2">@foreach($pdfTypes as $pdfType)<a class="btn btn-outline-primary" href="{{ route('request.track.pdf', [$customerRequest, $pdfType->value]) }}"><i class="bi bi-file-earmark-pdf me-1"></i>{{ $pdfType->title() }}</a>@endforeach</div></div>
            </div><aside class="col-lg-4">
                @unless($isRejected)@include('frontend.request.partials.payment-details')
                @unless($customerRequest->usesChecklistWorkflow())@include('frontend.request.partials.processing-details')@endunless @endunless

                <div class="tracking-side-card premium-card mb-4"><div class="tracking-card-title"><span class="icon-box"><i class="bi bi-files"></i></span><div><h3>બાકી જરૂરી દસ્તાવેજો</h3><p>Required Pending Documents</p></div></div>
                    @if($pendingDocuments->isNotEmpty())<ul class="tracking-document-list">@foreach($pendingDocuments as $document)<li><i class="bi bi-file-earmark-excel"></i><span><strong>{{ $document['name_gu'] ?? $document['name_en'] ?? 'Required document' }}</strong>@if(!empty($document['name_en']))<small>{{ $document['name_en'] }}</small>@endif</span></li>@endforeach</ul><div class="tracking-document-note"><i class="bi bi-info-circle"></i> Submit documents only through an approved Sai Consulting channel.</div>
                    @else<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i>No required documents are currently pending.</div>@endif
                </div>

                @if(in_array($publicStatus, $publicDispatchStatuses, true))@include('frontend.request.partials.dispatch-details')@endif
                @if($whatsappUrl)<div class="tracking-help-card premium-card"><span class="icon-box"><i class="bi bi-whatsapp"></i></span><h3>મદદની જરૂર છે?</h3><p>Need help understanding your request status?</p><a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-whatsapp-outline rounded-pill w-100 justify-content-center">WhatsApp Help</a></div>@endif
            </aside></div>
        </section>
    @endisset
</div></div></div></div>
@endsection
