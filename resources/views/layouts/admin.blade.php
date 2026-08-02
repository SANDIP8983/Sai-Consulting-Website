<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') | {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .admin-sidebar { width: 17rem; }
    .admin-sidebar .nav-link { color: rgba(255, 255, 255, .72); }
    .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link:focus { color: #fff; background-color: rgba(255, 255, 255, .1); }
    .admin-sidebar .nav-link.active { color: #fff; background-color: var(--bs-primary); }
    .admin-sidebar .settings-toggle { color: rgba(255, 255, 255, .72); }
    .admin-sidebar .settings-toggle:hover, .admin-sidebar .settings-toggle:focus { color: #fff; }

    @media (min-width: 992px) {
        .admin-sidebar { min-height: 100vh; position: sticky; top: 0; }
    }
    </style>
    @stack('styles')
</head>
<body class="bg-light">
    @auth
        <div class="d-lg-flex min-vh-100">
            <aside id="adminSidebar" class="admin-sidebar collapse d-lg-flex flex-column flex-shrink-0 bg-dark text-white">
                <div class="px-3 py-4 border-bottom border-secondary">
                    <a class="text-white text-decoration-none fw-semibold fs-5" href="{{ route('admin.dashboard') }}">
                        {{ config('app.name') }}
                    </a>
                    <div class="small text-white-50 mt-1">Administration Panel</div>
                </div>

                <div class="p-3 flex-grow-1">
                    <nav class="nav nav-pills flex-column gap-1" aria-label="Admin navigation">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>

                        <div class="pt-3 pb-1 text-uppercase small fw-semibold text-white-50">Management</div>
                        <a class="nav-link {{ request()->routeIs('admin.requests.*') ? 'active' : '' }}" href="{{ route('admin.requests.index') }}">Requests</a>
                        <a class="nav-link {{ request()->routeIs('admin.services.*') ? 'active' : '' }}" href="{{ route('admin.services.index') }}">Services</a>
                        <a class="nav-link {{ request()->routeIs('admin.required-documents.*') ? 'active' : '' }}" href="{{ route('admin.required-documents.index') }}">Required Documents</a>

                        <div class="pt-3 pb-1 text-uppercase small fw-semibold text-white-50">Configuration</div>
                        <button class="settings-toggle nav-link border-0 bg-transparent text-start w-100 d-flex justify-content-between align-items-center {{ request()->routeIs('admin.settings.*') ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#settingsMenu" aria-expanded="{{ request()->routeIs('admin.settings.*') ? 'true' : 'false' }}" aria-controls="settingsMenu">
                            <span>Settings</span>
                            <span aria-hidden="true">⌄</span>
                        </button>
                        <div id="settingsMenu" class="collapse {{ request()->routeIs('admin.settings.*') ? 'show' : '' }}">
                            <div class="nav flex-column ms-3 mt-1 gap-1">
                                <a class="nav-link {{ request()->routeIs('admin.settings.company-branding*') ? 'active' : '' }}" href="{{ route('admin.settings.company-branding') }}">Company &amp; Branding</a>
                                <a class="nav-link {{ request()->routeIs('admin.settings.website*') ? 'active' : '' }}" href="{{ route('admin.settings.website') }}">Website</a>
                                <a class="nav-link {{ request()->routeIs('admin.settings.office*') ? 'active' : '' }}" href="{{ route('admin.settings.office') }}">Office</a>
                                <a class="nav-link {{ request()->routeIs('admin.settings.contact*') ? 'active' : '' }}" href="{{ route('admin.settings.contact') }}">Contact</a>
                                <a class="nav-link {{ request()->routeIs('admin.settings.office-timings*') ? 'active' : '' }}" href="{{ route('admin.settings.office-timings') }}">Office Timings</a>
                                <a class="nav-link {{ request()->routeIs('admin.settings.holidays*') ? 'active' : '' }}" href="{{ route('admin.settings.holidays') }}">Holidays</a>
                            </div>
                        </div>
                    </nav>
                </div>

                <div class="p-3 border-top border-secondary small text-white-50">
                    Signed in as<br>
                    <span class="text-white">{{ auth()->user()->name }}</span>
                </div>
            </aside>

            <div class="d-flex flex-column flex-grow-1 min-vh-100">
                <header class="navbar bg-white border-bottom shadow-sm sticky-top">
                    <div class="container-fluid">
                        <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-expanded="false">
                            Menu
                        </button>
                        <span class="navbar-text fw-semibold ms-2">@yield('title', 'Admin')</span>
                        <div class="dropdown ms-auto">
                            <button class="btn btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ auth()->user()->name }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><span class="dropdown-item-text small text-muted">{{ auth()->user()->email }}</span></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('admin.logout') }}">
                                        @csrf
                                        <button class="dropdown-item" type="submit">Log out</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </header>

                <main class="flex-grow-1 p-3 p-lg-4">
                    <nav aria-label="breadcrumb" class="mb-4">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                            @hasSection('breadcrumbs')
                                @yield('breadcrumbs')
                            @else
                                <li class="breadcrumb-item active" aria-current="page">@yield('title', 'Dashboard')</li>
                            @endif
                        </ol>
                    </nav>

                    @yield('content')
                </main>

                <footer class="bg-white border-top py-3 px-3 px-lg-4 text-muted small">
                    &copy; {{ now()->year }} {{ config('app.name') }}. Admin Panel.
                </footer>
            </div>
        </div>
    @else
        <main>@yield('content')</main>
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
