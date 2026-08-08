<!DOCTYPE html>
<html lang="gu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @php
        $seoTitle = trim($__env->yieldContent('title', 'Sai Consulting | દસ્તાવેજીકરણ અને મિલકત માર્ગદર્શન'));
        $seoDescription = trim($__env->yieldContent('description', 'Sai Consulting દ્વારા દસ્તાવેજીકરણ અને મિલકત સંબંધિત માર્ગદર્શન મેળવો.'));
        $seoRobots = trim($__env->yieldContent('robots', 'index, follow'));
        $seoCanonical = trim($__env->yieldContent('canonical'));
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <meta name="theme-color" content="#0b3b82">
    @if($seoCanonical)
        <link rel="canonical" href="{{ $seoCanonical }}">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $seoCanonical }}">
        <meta property="og:site_name" content="Sai Consulting">
        @if($site['primaryLogoUrl'])<meta property="og:image" content="{{ \App\Support\Seo::route('branding.asset', 'primary-logo') }}">@endif
    @endif
    <link rel="icon" href="{{ $site['faviconUrl'] ?: asset('favicon.ico') }}">
    @stack('structured-data')

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/site.css') }}" rel="stylesheet">

    @stack('styles')
</head>

<body>

    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')

</body>
</html>
