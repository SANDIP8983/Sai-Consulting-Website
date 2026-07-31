@extends('layouts.app')
@section('title', 'Track Customer Request | વિનંતીની સ્થિતિ')
@section('content')
@php
    $statusLabels = ['received' => 'Received', 'under_review' => 'Under Review', 'need_documents' => 'Additional Documents Needed', 'approved' => 'Approved', 'rejected' => 'Rejected', 'payment_pending' => 'Payment Pending', 'payment_received' => 'Payment Received', 'draft_in_progress' => 'Draft in Progress', 'ready_for_verification' => 'Ready for Verification', 'customer_approved' => 'Customer Approved', 'ready_for_registration' => 'Ready for Registration', 'dispatched' => 'Dispatched', 'completed' => 'Completed', 'archived' => 'Archived'];
    $paymentLabels = ['not_required' => 'Not Required', 'pending' => 'Pending', 'received' => 'Received', 'partial' => 'Partially Paid'];
@endphp
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-8">
    <div class="mb-4"><h1 class="h2 mb-1">Track Request / વિનંતીની સ્થિતિ</h1><p class="text-muted">Enter the same mobile number used while submitting the request.</p></div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('request.track.lookup') }}" class="card border-0 shadow-sm mb-4">@csrf
        <div class="card-body p-4"><div class="row g-3 align-items-end">
            <div class="col-md-7"><label for="reference_no" class="form-label">Reference Number / સંદર્ભ નંબર</label><input id="reference_no" name="reference_no" value="{{ old('reference_no') }}" placeholder="SC/2026/000001" class="form-control @error('reference_no') is-invalid @enderror" required>@error('reference_no')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-5"><label for="mobile" class="form-label">Mobile Number / મોબાઇલ નંબર</label><input id="mobile" name="mobile" value="{{ old('mobile') }}" inputmode="numeric" maxlength="10" class="form-control @error('mobile') is-invalid @enderror" required>@error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Check Status / સ્થિતિ તપાસો</button></div>
        </div></div>
    </form>
    @isset($customerRequest)
        <div class="card border-0 shadow-sm"><div class="card-header bg-primary text-white py-3"><strong>Request Status</strong></div><div class="card-body p-4">
            <dl class="row mb-0">
                <dt class="col-sm-5">Reference Number</dt><dd class="col-sm-7">{{ $customerRequest->reference_no }}</dd>
                @if($customerRequest->file_number)<dt class="col-sm-5">File Number</dt><dd class="col-sm-7">{{ $customerRequest->file_number }}</dd>@endif
                <dt class="col-sm-5">Service</dt><dd class="col-sm-7">{{ $customerRequest->service->name_en }} / {{ $customerRequest->service->name_gu }}</dd>
                <dt class="col-sm-5">Current Status</dt><dd class="col-sm-7"><span class="badge text-bg-primary">{{ $statusLabels[$customerRequest->status] ?? str($customerRequest->status)->headline() }}</span></dd>
                <dt class="col-sm-5">Last Updated</dt><dd class="col-sm-7">{{ ($customerRequest->last_status_changed_at ?? $customerRequest->updated_at)->format('d M Y') }}</dd>
                <dt class="col-sm-5">Payment Status</dt><dd class="col-sm-7">{{ $paymentLabels[$customerRequest->payment_status] ?? str($customerRequest->payment_status)->headline() }}</dd>
                @if($customerRequest->estimated_completion_date)<dt class="col-sm-5">Estimated Completion</dt><dd class="col-sm-7">{{ $customerRequest->estimated_completion_date->format('d M Y') }}</dd>@endif
            </dl>
            @if($customerRequest->statusHistory->isNotEmpty())<hr><h2 class="h5">Updates / અપડેટ્સ</h2><div class="list-group list-group-flush">
                @foreach($customerRequest->statusHistory as $history)<div class="list-group-item px-0"><div class="d-flex justify-content-between gap-3"><strong>{{ $statusLabels[$history->to_status] ?? str($history->to_status)->headline() }}</strong><small class="text-muted">{{ $history->created_at->format('d M Y') }}</small></div><div>{{ $history->remarks }}</div></div>@endforeach
            </div>@endif
        </div></div>
    @endisset
</div></div></div>
@endsection
