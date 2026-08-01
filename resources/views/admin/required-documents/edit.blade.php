@extends('layouts.admin')
@section('title', 'Edit Required Document')
@section('content')
<div class="card shadow-sm"><div class="card-body p-4"><form method="POST" action="{{ route('admin.required-documents.update', $document) }}">@csrf @method('PUT')
@include('admin.required-documents._form', ['submitLabel' => 'Save Changes'])
</form></div></div>
@endsection
