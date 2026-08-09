@extends('layouts.admin')
@section('title', 'Notification Log')
@section('content')
<h1 class="h2">Customer Notification Log</h1>
<form class="card card-body border-0 shadow-sm mb-4" method="GET" aria-label="Filter customer notification log">
    <div class="row g-3">
        <div class="col-sm-6 col-xl-3"><label for="notification-reference" class="form-label">Reference Number</label><input id="notification-reference" name="q" value="{{ request('q') }}" class="form-control"></div>
        <div class="col-sm-6 col-xl-2"><label for="notification-milestone" class="form-label">Milestone</label><select id="notification-milestone" name="milestone" class="form-select"><option value="">All milestones</option>@foreach($milestones as $m)<option value="{{ $m->value }}" @selected(request('milestone') === $m->value)>{{ $m->label() }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-xl-2"><label for="notification-channel" class="form-label">Channel</label><select id="notification-channel" name="channel" class="form-select"><option value="">All channels</option><option value="email" @selected(request('channel') === 'email')>Email</option><option value="whatsapp" @selected(request('channel') === 'whatsapp')>WhatsApp</option></select></div>
        <div class="col-sm-6 col-xl-2"><label for="notification-status" class="form-label">Status</label><select id="notification-status" name="status" class="form-select"><option value="">All statuses</option>@foreach(['pending','sent','failed','skipped'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ str($s)->title() }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-xl"><label for="notification-date-from" class="form-label">From</label><input id="notification-date-from" type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
        <div class="col-sm-6 col-xl"><label for="notification-date-to" class="form-label">To</label><input id="notification-date-to" type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
        <div class="col-12 col-xl-auto d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Filter</button></div>
    </div>
</form>
<div class="card border-0 shadow-sm">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Customer notification deliveries">
        <table class="table align-middle mb-0">
            <thead><tr><th scope="col">Reference</th><th scope="col">Milestone</th><th scope="col">Channel</th><th scope="col">Recipient</th><th scope="col">Status</th><th scope="col">Time</th><th scope="col">Failure</th></tr></thead>
            <tbody>@forelse($deliveries as $d)<tr><td>{{ $d->event->customerRequest->reference_no }}</td><td>{{ $d->event->milestone->label() }}</td><td>{{ str($d->channel)->title() }}</td><td>{{ $d->recipient_masked ?: '—' }}</td><td><span class="badge text-bg-{{ ['sent'=>'success','failed'=>'danger','skipped'=>'secondary'][$d->status] ?? 'warning' }}">{{ str($d->status)->title() }}</span></td><td>{{ ($d->sent_at ?: $d->created_at)->format('d M Y, g:i A') }}</td><td>{{ $d->failure_category ? str($d->failure_category)->replace('_',' ')->title() : '—' }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">No notification deliveries found.</td></tr>@endforelse</tbody>
        </table>
    </div>
    @if($deliveries->hasPages())<div class="card-body">{{ $deliveries->links() }}</div>@endif
</div>
@endsection
