@props(['name', 'size' => 24])
<svg {{ $attributes->merge(['class' => 'public-icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('shield') <path d="M12 3 5 6v5c0 4.6 2.8 8.2 7 10 4.2-1.8 7-5.4 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('online') <rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/> @break
        @case('search') <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/> @break
        @case('document') <path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 12h6M9 16h6"/> @break
        @case('building') <path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6"/> @break
        @case('check') <circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/> @break
        @case('upload') <path d="M12 16V4m0 0L8 8m4-4 4 4M5 14v6h14v-6"/> @break
        @case('message') <path d="M4 4h16v12H8l-4 4z"/><path d="M8 9h8M8 12h5"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/> @break
        @case('location') <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/> @break
        @default <circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>
    @endswitch
</svg>
