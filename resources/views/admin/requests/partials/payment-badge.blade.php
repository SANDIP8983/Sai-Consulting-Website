@php($colors = ['not_required'=>'secondary','pending'=>'warning','partial'=>'info','received'=>'success'])
<span class="badge text-bg-{{ $colors[$status] ?? 'secondary' }}">{{ str($status)->replace('_', ' ')->title() }}</span>
