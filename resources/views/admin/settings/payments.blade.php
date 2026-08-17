@extends('layouts.admin')

@section('title', 'Payment Settings')

@section('breadcrumbs')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Payments</li>
@endsection

@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-9">
    <h1 class="h2 mb-4">Payment Settings</h1>
    @include('admin.settings._navigation')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card shadow-sm"><div class="card-body p-4"><form method="POST" action="{{ route('admin.settings.payments.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="form-check form-switch mb-4"><input type="hidden" name="enabled" value="0"><input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))><label class="form-check-label fw-semibold" for="enabled">Enable customer UPI payments</label></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="upi_id">UPI ID</label><input class="form-control" id="upi_id" name="upi_id" value="{{ old('upi_id', $settings['upi_id']) }}" maxlength="150" autocomplete="off"><div class="form-text">Stored in Admin settings; never hard-coded.</div></div>
            <div class="col-md-6"><label class="form-label" for="payee_name">Payee / Business Name</label><input class="form-control" id="payee_name" name="payee_name" value="{{ old('payee_name', $settings['payee_name']) }}" maxlength="150"></div>
            <div class="col-12"><label class="form-label" for="qr_code">Static UPI QR Code</label><input class="form-control" type="file" id="qr_code" name="qr_code" accept="image/png,image/jpeg,image/webp"><div class="form-text">PNG, JPG, JPEG or WebP; maximum 2 MB.</div>@if($settings['qr_path'])<div class="mt-2"><img src="{{ route('payments.upi-qr') }}" alt="Current UPI QR code" class="img-thumbnail" style="max-width:180px"><div class="form-check mt-2"><input type="hidden" name="remove_qr_code" value="0"><input class="form-check-input" type="checkbox" name="remove_qr_code" value="1" id="remove_qr_code"><label class="form-check-label" for="remove_qr_code">Remove current QR code</label></div></div>@endif</div>
            <div class="col-12"><label class="form-label" for="instructions">Customer Payment Instructions</label><textarea class="form-control" id="instructions" name="instructions" rows="4" maxlength="2000">{{ old('instructions', $settings['instructions']) }}</textarea></div>
            <div class="col-12"><div class="form-check form-switch"><input type="hidden" name="proof_upload_allowed" value="0"><input class="form-check-input" type="checkbox" id="proof_upload_allowed" name="proof_upload_allowed" value="1" @checked(old('proof_upload_allowed', $settings['proof_upload_allowed']))><label class="form-check-label" for="proof_upload_allowed">Allow optional customer payment-proof upload</label></div><div class="form-text">Accepted proof: JPG, JPEG, PNG or PDF; maximum 5 MB; stored privately.</div></div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save Payment Settings</button>
    </form></div></div>
</div></div></div>
@endsection
