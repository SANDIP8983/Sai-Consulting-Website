<!DOCTYPE html>
<html lang="gu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Sai Consulting | Trusted Documentation Partner')</title>

    <meta name="description" content="@yield('description', 'વિશ્વાસપાત્ર દસ્તાવેજ ડ્રાફ્ટિંગ, મિલકત દસ્તાવેજ માર્ગદર્શન અને કાનૂની કન્સલ્ટિંગ માટે Sai Consulting.')">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#0b3b82">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
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
