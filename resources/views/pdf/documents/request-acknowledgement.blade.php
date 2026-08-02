@extends('pdf.layouts.document')
@section('pdf-content')
<div class="note success">&#xAA4;&#xAAE;&#xABE;&#xAB0;&#xAC0; &#xAB5;&#xABF;&#xAA8;&#xA82;&#xAA4;&#xAC0; &#xAB8;&#xAAB;&#xAB3;&#xAA4;&#xABE;&#xAAA;&#xAC2;&#xAB0;&#xACD;&#xAB5;&#xA95; &#xAA8;&#xACB;&#xA82;&#xAA7;&#xABE;&#xA88; &#xA9B;&#xAC7;. / {{ $document->content['message'] }}</div>
@include('pdf.components.customer-summary')
@include('pdf.components.request-summary')
@include('pdf.components.services')
@endsection
