@extends('layouts.admin')

@section('title', 'Contact Settings')

@section('breadcrumbs')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Contact</li>
@endsection

@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-9">
    <h1 class="h2 mb-4">Contact Settings</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card shadow-sm"><div class="card-body p-4"><form method="POST" action="{{ route('admin.settings.contact.update') }}">
        @csrf @method('PUT')
        <div class="mb-3"><label for="public_email" class="form-label">Public Email</label><input type="email" id="public_email" name="public_email" value="{{ old('public_email', $settings['public_email']) }}" class="form-control @error('public_email') is-invalid @enderror">@error('public_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label for="public_phone" class="form-label">Public Phone</label><input type="tel" id="public_phone" name="public_phone" value="{{ old('public_phone', $settings['public_phone']) }}" class="form-control @error('public_phone') is-invalid @enderror">@error('public_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-4"><label for="whatsapp_number" class="form-label">WhatsApp Number</label><input type="tel" id="whatsapp_number" name="whatsapp_number" value="{{ old('whatsapp_number', $settings['whatsapp_number']) }}" placeholder="919913793876" class="form-control @error('whatsapp_number') is-invalid @enderror"><div class="form-text">Use country code and digits only; do not include a plus sign.</div>@error('whatsapp_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <button class="btn btn-primary" type="submit">Save Contact Settings</button>
    </form></div></div>
</div></div></div>
@endsection
