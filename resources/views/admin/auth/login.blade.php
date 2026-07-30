@extends('layouts.admin')

@section('title', 'Admin Login')

@section('content')
<div class="container min-vh-100 d-flex align-items-center justify-content-center py-5">
    <div class="col-sm-10 col-md-8 col-lg-5 col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-2">Admin Login</h1>
                <p class="text-muted mb-4">Sign in to manage {{ config('app.name') }}.</p>

                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email" required autofocus>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-check mb-4">
                        <input id="remember" name="remember" value="1" type="checkbox" class="form-check-input" @checked(old('remember'))>
                        <label for="remember" class="form-check-label">Remember me</label>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Sign In</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
