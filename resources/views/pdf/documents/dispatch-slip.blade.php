@extends('pdf.layouts.document')
@section('pdf-content')
@include('pdf.components.customer-summary')
@include('pdf.components.request-summary')
@include('pdf.components.dispatches')
@endsection
