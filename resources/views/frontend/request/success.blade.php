@extends('layouts.app')

@section('title', 'Request Submitted Successfully')

@section('content')

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-8">

            <div class="card shadow border-0">

                <div class="card-body text-center p-5">

                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success"
                           style="font-size:70px;"></i>
                    </div>

                    <h2 class="text-success fw-bold">
                        Request Submitted Successfully
                    </h2>

                    <p class="text-muted mt-3">
                        Thank you for choosing
                        <strong>Sai Consulting</strong>.
                    </p>

                    <div class="alert alert-success mt-4">

                        <h5 class="mb-3">
                            Your Reference Number
                        </h5>

                        <h3 class="fw-bold text-primary">
                            {{ session('reference_no') }}
                        </h3>

                    </div>

                    <div class="alert alert-warning text-start">

                        <h5>Next Process</h5>

                        <ul class="mb-0">
                            <li>Your request has been received successfully.</li>
                            <li>Our team will review your request.</li>
                            <li>If additional documents are required, we will contact you on WhatsApp.</li>
                            <li>Please save your Reference Number for future tracking.</li>
                        </ul>

                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-center mt-4">

                        <a href="{{ route('request.create') }}"
                           class="btn btn-primary">

                            New Request

                        </a>

                        <a href="/"
                           class="btn btn-outline-secondary">

                            Back to Home

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection