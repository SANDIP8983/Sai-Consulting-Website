@extends('layouts.admin')
@section('title', 'My Profile')
@section('content')
<h1 class="h2 mb-4">My Profile / Account Settings</h1>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="row g-4"><div class="col-lg-7"><form method="POST" action="{{ route('admin.profile.update') }}" class="card card-body shadow-sm">@csrf @method('PUT')<h2 class="h5 mb-3">Profile Information</h2><div class="row g-3">
<div class="col-md-6"><label class="form-label">Name</label><input name="name" value="{{ old('name', auth()->user()->name) }}" class="form-control @error('name') is-invalid @enderror" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-md-6"><label class="form-label">Username</label><input name="username" value="{{ old('username', auth()->user()->username) }}" class="form-control @error('username') is-invalid @enderror" required>@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-md-6"><label class="form-label">Email <span class="text-muted">(optional)</span></label><input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" class="form-control @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-md-6"><label class="form-label">Mobile Number</label><input name="mobile" value="{{ old('mobile', auth()->user()->mobile) }}" class="form-control @error('mobile') is-invalid @enderror" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" autocomplete="tel" required><div class="form-text">10-digit Indian mobile number</div>@error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
</div><div class="mt-3"><button class="btn btn-primary">Save Profile</button></div></form></div>
<div class="col-lg-5"><form method="POST" action="{{ route('admin.profile.password') }}" class="card card-body shadow-sm">@csrf @method('PUT')<h2 class="h5 mb-3">Change Password</h2>
<div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="current-password" required>@error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required></div>
<button class="btn btn-outline-primary">Change Password</button></form></div></div>
@endsection
