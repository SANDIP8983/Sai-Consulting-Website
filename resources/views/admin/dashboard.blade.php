@extends('layouts.admin')

@section('title', 'Dashboard')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
@endsection

@section('content')
<div class="container py-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h1 class="h2 mb-1">Dashboard</h1>
            <p class="text-muted mb-0">Overview of your administration workspace.</p>
        </div>
        <a class="btn btn-primary mt-3 mt-md-0" href="{{ route('admin.settings.website') }}">Manage Settings</a>
    </div>

    <div class="row g-4">
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2">Customer Requests</p><p class="display-6 fw-semibold mb-0">{{ $summary['requests'] }}</p></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2">Saved Settings</p><p class="display-6 fw-semibold mb-0">{{ $summary['settings'] }}</p></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2">Office Timings</p><p class="display-6 fw-semibold mb-0">{{ $summary['office_timings'] }}</p></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2">Holidays</p><p class="display-6 fw-semibold mb-0">{{ $summary['holidays'] }}</p></div></div></div>
    </div>
</div>
@endsection
