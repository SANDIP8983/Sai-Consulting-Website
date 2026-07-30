@extends('layouts.app')

@section('title', 'Office Timings')

@section('content')
<div class="container py-5"><div class="row justify-content-center"><div class="col-xl-10">
    <h1 class="h2 mb-4">Office Timings</h1>
    @include('admin.settings._navigation')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @php($days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
    <div class="card shadow-sm"><div class="card-body p-4"><form method="POST" action="{{ route('admin.settings.office-timings.update') }}">
        @csrf @method('PUT')
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Day</th><th>Open</th><th>Close</th><th>Closed</th><th>Notes</th></tr></thead><tbody>
            @foreach($timings as $index => $timing)
                @php($closed = (bool) old("timings.{$index}.is_closed", $timing['is_closed']))
                <tr>
                    <td><strong>{{ $days[$timing['day_of_week']] }}</strong><input type="hidden" name="timings[{{ $index }}][day_of_week]" value="{{ $timing['day_of_week'] }}"></td>
                    <td><input type="time" name="timings[{{ $index }}][opens_at]" value="{{ old("timings.{$index}.opens_at", $timing['opens_at'] ? substr($timing['opens_at'], 0, 5) : '') }}" class="form-control @error("timings.{$index}.opens_at") is-invalid @enderror">@error("timings.{$index}.opens_at")<div class="invalid-feedback">{{ $message }}</div>@enderror</td>
                    <td><input type="time" name="timings[{{ $index }}][closes_at]" value="{{ old("timings.{$index}.closes_at", $timing['closes_at'] ? substr($timing['closes_at'], 0, 5) : '') }}" class="form-control @error("timings.{$index}.closes_at") is-invalid @enderror">@error("timings.{$index}.closes_at")<div class="invalid-feedback">{{ $message }}</div>@enderror</td>
                    <td><input type="hidden" name="timings[{{ $index }}][is_closed]" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="timings[{{ $index }}][is_closed]" value="1" @checked($closed) aria-label="{{ $days[$timing['day_of_week']] }} is closed"></div></td>
                    <td><input name="timings[{{ $index }}][notes]" value="{{ old("timings.{$index}.notes", $timing['notes']) }}" class="form-control"></td>
                </tr>
            @endforeach
        </tbody></table></div>
        <button class="btn btn-primary" type="submit">Save Office Timings</button>
    </form></div></div>
</div></div></div>
@endsection
