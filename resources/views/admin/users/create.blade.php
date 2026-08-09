@extends('layouts.admin')
@section('title', 'Add User')
@section('content')
<h1 class="h2 mb-4">Add User</h1><form method="POST" action="{{ route('admin.users.store') }}" class="card card-body shadow-sm">@csrf @include('admin.users._form')<div class="mt-4"><button class="btn btn-primary">Create User</button><a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Cancel</a></div></form>
@endsection
