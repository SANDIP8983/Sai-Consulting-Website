@extends('layouts.admin')
@section('title', 'Edit User')
@section('content')
<h1 class="h2 mb-4">Edit User</h1>@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<form method="POST" action="{{ route('admin.users.update', $user) }}" class="card card-body shadow-sm mb-4">@csrf @method('PUT') @include('admin.users._form')<div class="mt-4"><button class="btn btn-primary">Save User</button><a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Cancel</a></div></form>
<form method="POST" action="{{ route('admin.users.password', $user) }}" class="card card-body shadow-sm">@csrf @method('PUT')<h2 class="h5">Administrative Password Reset</h2><div class="row g-3"><div class="col-md-6"><label class="form-label">New Password</label><input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label class="form-label">Confirm New Password</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required></div></div><div class="mt-3"><button class="btn btn-outline-danger">Reset Password</button></div></form>
@endsection
