@php($colors = ['not_required'=>'secondary','pending'=>'warning','received'=>'success','failed'=>'danger','refunded'=>'info'])
<span class="badge text-bg-{{ $colors[$status] ?? 'secondary' }}">{{ str($status)->replace('_', ' ')->title() }}</span>
