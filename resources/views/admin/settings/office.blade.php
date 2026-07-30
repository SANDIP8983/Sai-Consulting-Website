@extends('layouts.admin')

@section('title', 'Office Settings')

@section('breadcrumbs')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Office</li>
@endsection

@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-9">
    <h1 class="h2 mb-4">Office Settings</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card shadow-sm"><div class="card-body p-4"><form method="POST" action="{{ route('admin.settings.office.update') }}">
        @csrf @method('PUT')
        <div class="row g-3">
            @foreach(['office_name' => 'Office Name', 'address_line_1' => 'Address Line 1', 'address_line_2' => 'Address Line 2', 'city' => 'City', 'state' => 'State', 'postal_code' => 'Postal Code'] as $field => $label)
                <div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $settings[$field]) }}" class="form-control @error($field) is-invalid @enderror" @if($field === 'office_name') required @endif>@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @endforeach
            <div class="col-md-6"><label for="timezone" class="form-label">Timezone</label><input id="timezone" name="timezone" value="{{ old('timezone', $settings['timezone']) }}" placeholder="Asia/Kolkata" class="form-control @error('timezone') is-invalid @enderror" required>@error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save Office Settings</button>
    </form></div></div>
</div></div></div>
@endsection
