@extends('layouts.app')

@section('title', 'Document Request')

@section('content')

<div class="container py-5">

    <h2 class="mb-4">
        Document Request Form
    </h2>

    <div class="alert alert-danger">

        <strong>મહત્વપૂર્ણ સૂચના</strong>

        <hr>

        આધાર કાર્ડ, PAN કાર્ડ, ચૂંટણી કાર્ડ,
        પાસપોર્ટ અથવા અન્ય ઓળખના દસ્તાવેજો
        અહીં અપલોડ કરશો નહીં.

        જરૂર પડ્યે અમારી ટીમ
        WhatsApp દ્વારા માંગશે.

    </div>

    <form method="POST"
          action="{{ route('request.store') }}"
          enctype="multipart/form-data">

        @csrf

        <div class="row">

            <div class="col-md-6 mb-3">

                <label class="form-label">
                    Service
                </label>

                <select
                    name="service_id"
                    class="form-select"
                    required>

                    <option value="">
                        Select Service
                    </option>

                    @foreach($services as $service)

                        <option value="{{ $service->id }}">
                            {{ $service->name }}
                        </option>

                    @endforeach

                </select>

            </div>

            <div class="col-md-6 mb-3">

                <label class="form-label">

                    Full Name

                </label>

                <input
                    type="text"
                    name="name"
                    class="form-control"
                    required>

<div class="col-md-6 mb-3">
    <label class="form-label">મોબાઇલ નંબર</label>

    <input type="text"
           name="mobile"
           class="form-control"
           maxlength="10"
           required>
</div>

<div class="col-md-6 mb-3">
    <label class="form-label">ઈમેલ (વૈકલ્પિક)</label>

    <input type="email"
           name="email"
           class="form-control">
</div>

<div class="col-md-4 mb-3">
    <label class="form-label">ગામ</label>

    <input type="text"
           name="village"
           class="form-control">
</div>

<div class="col-md-4 mb-3">
    <label class="form-label">તાલુકો</label>

    <input type="text"
           name="taluka"
           class="form-control">
</div>

<div class="col-md-4 mb-3">
    <label class="form-label">જિલ્લો</label>

    <input type="text"
           name="district"
           class="form-control">
</div>

            </div>

        </div>

    </form>

</div>

@endsection