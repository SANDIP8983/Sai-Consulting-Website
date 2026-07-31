<header>
    @include('frontend.sections.top-information-bar')
    <nav class="navbar navbar-expand-xl navbar-light site-navbar sticky-top" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}" aria-label="Sai Consulting home">
                <span class="brand-mark">SC</span>
                <span><strong>{{ $site['businessName'] }}</strong><small>Documentation & Consulting</small></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainMenu" aria-controls="mainMenu" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainMenu">
                <ul class="navbar-nav ms-auto align-items-xl-center gap-xl-1">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('services.*') ? 'active' : '' }}" href="{{ route('services.index') }}">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#process">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('request.track*') ? 'active' : '' }}" href="{{ route('request.track') }}">Track Request</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#about">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#faq">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#contact">Contact</a></li>
                </ul>
                <a href="{{ route('request.create') }}" class="btn btn-primary rounded-pill px-4 ms-xl-3 mt-3 mt-xl-0">Apply Online</a>
            </div>
        </div>
    </nav>
</header>
