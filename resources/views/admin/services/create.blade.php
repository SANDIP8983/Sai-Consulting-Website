@extends('layouts.admin')

@section('title', 'Add Service')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.services.index') }}">Services</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add Service</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><h1 class="h2 mb-0">Add Service</h1><a class="btn btn-outline-secondary" href="{{ route('admin.services.index') }}">Back to Services</a></div>
<div class="card border-0 shadow-sm"><div class="card-body p-4"><form method="POST" action="{{ route('admin.services.store') }}">@csrf @include('admin.services._form', ['service' => null, 'submitLabel' => 'Create Service'])</form></div></div>
@endsection
