@extends('layouts.app')

@section('title', 'Services | Sai Consulting')
@section('description', 'Sai Consulting ની સક્રિય દસ્તાવેજ ડ્રાફ્ટિંગ, મિલકત દસ્તાવેજ અને કન્સલ્ટિંગ સેવાઓ શોધો.')

@section('content')
<section class="service-page-hero"><div class="container py-5"><nav aria-label="breadcrumb"><ol class="breadcrumb service-breadcrumb"><li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li><li class="breadcrumb-item active" aria-current="page">Services</li></ol></nav><div class="row align-items-end g-4"><div class="col-lg-7"><span class="eyebrow"><span></span> અમારી સેવાઓ</span><h1>દસ્તાવેજ અને કન્સલ્ટિંગ સેવાઓ</h1><p>Browse every active service, review requirements, and apply through the secure public request workflow.</p></div><div class="col-lg-5"><form method="GET" action="{{ route('services.index') }}" class="service-search" role="search"><label for="service-search" class="visually-hidden">Search services</label><i class="bi bi-search" aria-hidden="true"></i><input id="service-search" name="q" value="{{ $search }}" class="form-control" placeholder="Search Gujarati or English services"><button class="btn btn-primary" type="submit">Search</button></form></div></div></div></section>
<section class="section-space service-catalog"><div class="container">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4"><div><h2 class="h4 mb-1">{{ $search ? 'Search Results' : 'All Active Services' }}</h2><p class="text-secondary mb-0">{{ $services->total() }} service(s) available</p></div>@if($search)<a href="{{ route('services.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Clear Search</a>@endif</div>
    <div class="row g-4">@forelse($services as $index => $service)<div class="col-md-6 col-xl-4 d-flex"><x-public-service-card :service="$service" :number="$services->firstItem() + $index" /></div>@empty<div class="col-12"><div class="empty-services premium-card text-center"><i class="bi bi-search" aria-hidden="true"></i><h2>No matching services found</h2><p>Try another Gujarati or English search term.</p></div></div>@endforelse</div>
    @if($services->hasPages())<div class="mt-5">{{ $services->links('pagination::bootstrap-5') }}</div>@endif
</div></section>
@endsection
