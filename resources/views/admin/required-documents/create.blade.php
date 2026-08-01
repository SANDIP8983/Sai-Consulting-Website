@extends('layouts.admin')
@section('title', 'Add Required Document')
@section('content')
<div class="card shadow-sm"><div class="card-body p-4"><form method="POST" action="{{ route('admin.required-documents.store') }}">@csrf
@include('admin.required-documents._form', ['document' => null, 'submitLabel' => 'Create Document'])
</form></div></div>
@endsection
