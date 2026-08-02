<div class="vstack gap-3" aria-label="Admin activity history">
@forelse($activityItems as $activity)
@php($border=['approval'=>'success','payment'=>'primary','completion'=>'success','dispatch'=>'info','archive'=>'dark','billing'=>'warning','processing'=>'primary'][$activity['highlight']]??'secondary')
<article class="card border-0 border-start border-4 border-{{ $border }} shadow-sm"><div class="card-body py-3">
<div class="d-flex flex-column flex-md-row justify-content-between gap-2"><div><strong>{{ $activity['action'] }}</strong>@if($activity['status']) <span class="badge text-bg-light border ms-1">{{ str($activity['status'])->headline() }}</span>@endif</div><time class="small text-muted" datetime="{{ $activity['date']?->toIso8601String() }}">{{ $activity['date']?->format('d M Y') }} · {{ $activity['date']?->format('g:i A') }}</time></div>
<div class="small text-muted mt-1">Admin: {{ $activity['admin'] ?: 'System' }}</div>
@if($activity['remark'])<p class="mb-0 mt-2">{{ $activity['remark'] }}</p>@endif
</div></article>
@empty<div class="text-muted">No activity has been recorded.</div>@endforelse
</div>
