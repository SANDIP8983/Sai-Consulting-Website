@extends('layouts.app')

@section('title', 'Request Submitted Successfully')

@section('content')
@php($submission = session('submitted_request'))
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow border-0">
                <div class="card-body text-center p-4 p-lg-5">
                    <div class="display-3 text-success mb-3" aria-hidden="true">✓</div>
                    <h1 class="h2 text-success fw-bold">Request Submitted Successfully</h1>
                    <p class="text-muted">તમારી વિનંતી સફળતાપૂર્વક મળી ગઈ છે.</p>

                    <div class="alert alert-success mt-4">
                        <div class="mb-2">Your Reference Number / તમારો સંદર્ભ નંબર</div>
                        <div class="h3 fw-bold text-primary mb-0">{{ $submission['reference_no'] }}</div>
                    </div>

                    <div class="border rounded p-3 mb-4">
                        <strong>Available service time estimate</strong>
                        @if($submission['estimated_days'])
                            <div class="mt-2">Approximately {{ $submission['estimated_days'] }} day(s)</div>
                            @if($submission['estimated_completion_date'])
                                <div class="text-muted">Estimated completion: {{ \Illuminate\Support\Carbon::parse($submission['estimated_completion_date'])->format('d M Y') }}</div>
                            @endif
                        @else
                            <div class="mt-2 text-muted">The service estimate will be provided after review.</div>
                        @endif
                    </div>

                    <p>Please save the reference number and use it with your mobile number to track the request.</p>
                    <div class="d-grid gap-2 d-sm-flex justify-content-center mt-4">
                        <a href="{{ route('request.track') }}" class="btn btn-primary">Track Request</a>
                        <a href="{{ route('request.create') }}" class="btn btn-outline-primary">New Request</a>
                        <a href="{{ url('/') }}" class="btn btn-outline-secondary">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
