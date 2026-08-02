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
    <div class="row g-4 mt-1">
        <div class="col-sm-6"><a href="{{ route('admin.requests.index', ['source' => 'online']) }}" class="card border-0 shadow-sm h-100 text-decoration-none"><div class="card-body"><p class="text-muted mb-2">Total Online Requests</p><p class="display-6 fw-semibold text-primary mb-0">{{ $summary['online_requests'] }}</p></div></a></div>
        <div class="col-sm-6"><a href="{{ route('admin.requests.index', ['source' => 'offline']) }}" class="card border-0 shadow-sm h-100 text-decoration-none"><div class="card-body"><p class="text-muted mb-2">Total Offline Requests</p><p class="display-6 fw-semibold text-secondary mb-0">{{ $summary['offline_requests'] }}</p></div></a></div>
    </div>

    <div class="row g-4">
        <div class="col-sm-6 col-xl-3"><a href="{{ route('admin.services.index', ['active'=>'1']) }}" class="card border-0 shadow-sm h-100 text-decoration-none"><div class="card-body"><p class="text-muted mb-2">Total Active Services</p><p class="display-6 fw-semibold text-success mb-0">{{ $summary['active_services'] }}</p></div></a></div>
        <div class="col-sm-6 col-xl-3"><a href="{{ route('admin.services.index', ['availability'=>'online']) }}" class="card border-0 shadow-sm h-100 text-decoration-none"><div class="card-body"><p class="text-muted mb-2">Total Online Services</p><p class="display-6 fw-semibold text-primary mb-0">{{ $summary['online_services'] }}</p></div></a></div>
        <div class="col-sm-6 col-xl-3"><a href="{{ route('admin.services.index', ['availability'=>'offline']) }}" class="card border-0 shadow-sm h-100 text-decoration-none"><div class="card-body"><p class="text-muted mb-2">Total Offline Services</p><p class="display-6 fw-semibold text-info mb-0">{{ $summary['offline_services'] }}</p></div></a></div>
        <div class="col-sm-6 col-xl-3"><a href="{{ route('admin.services.index', ['active'=>'0']) }}" class="card border-0 shadow-sm h-100 text-decoration-none"><div class="card-body"><p class="text-muted mb-2">Disabled Services</p><p class="display-6 fw-semibold text-secondary mb-0">{{ $summary['disabled_services'] }}</p></div></a></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2">Customer Requests</p><p class="display-6 fw-semibold mb-0">{{ $summary['requests'] }}</p></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2">Saved Settings</p><p class="display-6 fw-semibold mb-0">{{ $summary['settings'] }}</p></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2">Office Timings</p><p class="display-6 fw-semibold mb-0">{{ $summary['office_timings'] }}</p></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="text-muted mb-2">Holidays</p><p class="display-6 fw-semibold mb-0">{{ $summary['holidays'] }}</p></div></div></div>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-5 mb-3"><div><h2 class="h4 mb-1">Request Workflow</h2><p class="text-muted mb-0">Current workload by status.</p></div><a href="{{ route('admin.requests.index') }}" class="btn btn-outline-primary">Manage Requests</a></div>
    <div class="row g-3">
        @foreach(['received' => ['New / Received','primary'], 'under_review' => ['Under Review','info'], 'need_documents' => ['Need Documents','warning'], 'payment_pending' => ['Payment Pending','danger'], 'processing_not_started'=>['Processing Not Started','secondary'], 'in_progress' => ['In Progress','primary'], 'ready_to_complete'=>['Ready to Complete','warning'], 'completed' => ['Completed','success']] as $key => [$label,$color])
            <div class="col-6 col-lg-4 col-xl-2"><a href="{{ route('admin.requests.index', $key === 'in_progress' ? [] : ['status' => $key]) }}" class="card border-0 shadow-sm h-100 text-decoration-none"><div class="card-body"><span class="badge text-bg-{{ $color }} mb-3">{{ $label }}</span><div class="display-6 fw-semibold text-dark">{{ $requestSummary[$key] }}</div></div></a></div>
        @endforeach
    </div>
</div>
@endsection
