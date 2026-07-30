@extends('layouts.admin')

@section('title', 'Holidays')

@section('breadcrumbs')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Holidays</li>
@endsection

@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-xl-10">
    <h1 class="h2 mb-4">Holidays</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @php($isEditing = $editingHoliday !== null)
    <div class="card shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-3">{{ $isEditing ? 'Edit Holiday' : 'Add Holiday' }}</h2>
        <form method="POST" action="{{ $isEditing ? route('admin.settings.holidays.update', $editingHoliday) : route('admin.settings.holidays.store') }}">
            @csrf @if($isEditing) @method('PUT') @endif
            <div class="row g-3"><div class="col-md-4"><label for="holiday_date" class="form-label">Date</label><input type="date" id="holiday_date" name="holiday_date" value="{{ old('holiday_date', $editingHoliday?->holiday_date?->format('Y-m-d')) }}" class="form-control @error('holiday_date') is-invalid @enderror" required>@error('holiday_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-8"><label for="title" class="form-label">Title</label><input id="title" name="title" value="{{ old('title', $editingHoliday?->title) }}" class="form-control @error('title') is-invalid @enderror" required>@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12"><label for="description" class="form-label">Description</label><textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $editingHoliday?->description) }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-3"><input type="hidden" name="is_recurring" value="0"><div class="form-check"><input id="is_recurring" class="form-check-input" type="checkbox" name="is_recurring" value="1" @checked(old('is_recurring', $editingHoliday?->is_recurring))><label class="form-check-label" for="is_recurring">Recurs annually</label></div></div><div class="col-md-3"><input type="hidden" name="is_closed" value="0"><div class="form-check"><input id="is_closed" class="form-check-input" type="checkbox" name="is_closed" value="1" @checked(old('is_closed', $editingHoliday?->is_closed ?? true))><label class="form-check-label" for="is_closed">Office closed</label></div></div></div>
            <button class="btn btn-primary mt-4" type="submit">{{ $isEditing ? 'Update Holiday' : 'Add Holiday' }}</button>@if($isEditing) <a class="btn btn-outline-secondary mt-4" href="{{ route('admin.settings.holidays') }}">Cancel</a>@endif
        </form>
    </div></div>
    <div class="card shadow-sm"><div class="card-body p-4"><h2 class="h5 mb-3">Holiday Calendar</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Date</th><th>Title</th><th>Recurring</th><th>Office Status</th><th class="text-end">Actions</th></tr></thead><tbody>@forelse($holidays as $holiday)<tr><td>{{ $holiday->holiday_date->format('d M Y') }}</td><td><strong>{{ $holiday->title }}</strong>@if($holiday->description)<div class="small text-muted">{{ $holiday->description }}</div>@endif</td><td>{{ $holiday->is_recurring ? 'Yes' : 'No' }}</td><td>{{ $holiday->is_closed ? 'Closed' : 'Open' }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.settings.holidays.edit', $holiday) }}">Edit</a><form class="d-inline" method="POST" action="{{ route('admin.settings.holidays.destroy', $holiday) }}" onsubmit="return confirm('Delete this holiday?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form></td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No holidays have been added.</td></tr>@endforelse</tbody></table></div></div></div>
</div></div></div>
@endsection
