@extends('pdf.layouts.document')
@section('pdf-content')
@include('pdf.components.customer-summary')
@include('pdf.components.request-summary')
@include('pdf.components.services')
@if($document->content['completion']['date'])<div class="note success"><strong>Completed:</strong> {{ $document->content['completion']['date'] }}@if($document->content['completion']['customer_remark'])<div>{{ $document->content['completion']['customer_remark'] }}</div>@endif</div>@endif
@include('pdf.components.dispatches')
@if($document->content['closure']['date'])<div class="note success"><strong>Closed:</strong> {{ $document->content['closure']['date'] }}@if($document->content['closure']['customer_remark'])<div>{{ $document->content['closure']['customer_remark'] }}</div>@endif</div>@endif
@endsection
