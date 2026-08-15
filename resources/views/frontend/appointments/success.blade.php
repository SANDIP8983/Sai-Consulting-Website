@extends('layouts.app')
@section('title','Appointment Request Received | Sai Consulting')
@section('robots','noindex, nofollow, noarchive')
@section('content')
@php($appointment = session('submitted_appointment'))
<section class="py-5"><div class="container" style="max-width:720px"><div class="card border-0 shadow-sm text-center"><div class="card-body p-5"><h1 class="h2 text-success">Appointment request received</h1><p class="lead">Reference: <strong>{{ $appointment['reference_no'] }}</strong></p><dl class="row text-start"><dt class="col-5">Service</dt><dd class="col-7">{{ $appointment['service_name'] }}</dd><dt class="col-5">Requested time</dt><dd class="col-7">{{ $appointment['scheduled_at'] }}</dd><dt class="col-5">Status</dt><dd class="col-7"><span class="badge text-bg-warning">{{ str($appointment['status'])->headline() }}</span></dd></dl><p>Confirmation will be sent by email or WhatsApp when available. This is not confirmed until our team contacts you.</p><a class="btn btn-primary" href="{{ route('home') }}">Back Home</a></div></div></div></section>
@endsection
