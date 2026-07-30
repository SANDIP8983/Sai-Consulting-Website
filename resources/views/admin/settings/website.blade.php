@extends('layouts.admin')

@section('title', 'Website Settings')

@section('breadcrumbs')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Website</li>
@endsection

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="h2 mb-4">Website Settings</h1>
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            <div class="card shadow-sm"><div class="card-body p-4">
                <form method="POST" action="{{ route('admin.settings.website.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3"><label for="website_name" class="form-label">Website Name</label><input id="website_name" name="website_name" value="{{ old('website_name', $settings['website_name']) }}" class="form-control @error('website_name') is-invalid @enderror" required>@error('website_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="mb-3"><label for="website_status" class="form-label">Website Status</label><select id="website_status" name="website_status" class="form-select @error('website_status') is-invalid @enderror" required><option value="active" @selected(old('website_status', $settings['website_status']) === 'active')>Active</option><option value="maintenance" @selected(old('website_status', $settings['website_status']) === 'maintenance')>Maintenance</option></select>@error('website_status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="mb-4"><label for="maintenance_message" class="form-label">Maintenance Message</label><textarea id="maintenance_message" name="maintenance_message" rows="4" class="form-control @error('maintenance_message') is-invalid @enderror">{{ old('maintenance_message', $settings['maintenance_message']) }}</textarea><div class="form-text">Shown when the website is in maintenance mode.</div>@error('maintenance_message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <button class="btn btn-primary" type="submit">Save Website Settings</button>
                </form>
            </div></div>
        </div>
    </div>
</div>
@endsection
