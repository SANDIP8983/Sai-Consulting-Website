<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') | {{ config('app.name') }}</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .admin-sidebar { width: 17rem; }
    .admin-sidebar .nav-link { color: rgba(255, 255, 255, .72); }
    .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link:focus { color: #fff; background-color: rgba(255, 255, 255, .1); }
    .admin-sidebar .nav-link.active { color: #fff; background-color: var(--bs-primary); }
    .admin-sidebar .settings-toggle { color: rgba(255, 255, 255, .72); }
    .admin-sidebar .settings-toggle:hover, .admin-sidebar .settings-toggle:focus { color: #fff; }
    .admin-shell-content, .admin-main { min-width: 0; }
    .admin-main .card, .admin-main .row > * { min-width: 0; }
    .admin-main .table-responsive { border-radius: inherit; overscroll-behavior-inline: contain; }
    .admin-main .table-responsive:focus-visible { outline: 3px solid rgba(13, 110, 253, .45); outline-offset: 2px; }
    .admin-main :where(a, button, input, select, textarea, summary):focus-visible { outline: 3px solid rgba(13, 110, 253, .45); outline-offset: 2px; box-shadow: none; }
    .admin-skip-link { z-index: 2000; }

    @media (min-width: 992px) {
        .admin-sidebar { min-height: 100vh; position: sticky; top: 0; }
    }
    @media (max-width: 991.98px) {
        .admin-sidebar { width: 100%; max-height: calc(100vh - 3.5rem); overflow-y: auto; }
        .admin-toolbar { gap: .5rem; flex-wrap: nowrap; }
        .admin-toolbar .navbar-text { min-width: 0; overflow-wrap: anywhere; }
        .admin-user-menu { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .admin-main { padding: 1rem !important; }
        .admin-main .btn { min-height: 44px; }
        .admin-main .btn-sm { min-height: 40px; }
        .admin-main .form-check-input { width: 1.25rem; height: 1.25rem; }
        .admin-main .form-check-label { padding-block: .15rem; }
    }
    </style>
    @stack('styles')
</head>
<body class="bg-light">
    <a class="visually-hidden-focusable position-fixed top-0 start-0 m-2 p-2 bg-white rounded admin-skip-link" href="#admin-main-content">Skip to main content</a>
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
                        @can('requests.view')<a class="nav-link {{ request()->routeIs('admin.requests.*') ? 'active' : '' }}" href="{{ route('admin.requests.index') }}">Requests</a>@endcan
                        @can('appointments.manage')<a class="nav-link {{ request()->routeIs('admin.appointments.*') ? 'active' : '' }}" href="{{ route('admin.appointments.index') }}">Appointments</a><a class="nav-link {{ request()->routeIs('admin.appointment-blocks.*') ? 'active' : '' }}" href="{{ route('admin.appointment-blocks.index') }}">Appointment Availability</a>@endcan
                        @can('services.manage')<a class="nav-link {{ request()->routeIs('admin.services.*') ? 'active' : '' }}" href="{{ route('admin.services.index') }}">Services</a>@endcan
                        @can('documents.manage')<a class="nav-link {{ request()->routeIs('admin.required-documents.*') ? 'active' : '' }}" href="{{ route('admin.required-documents.index') }}">Required Documents</a>@endcan
                        @can('users.manage')<a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users / Staff</a>@endcan
                        @can('notifications.view')<a class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}">Notification Log</a>@endcan

                        @can('settings.manage')<div class="pt-3 pb-1 text-uppercase small fw-semibold text-white-50">Configuration</div>
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
                                @can('notifications.manage')<a class="nav-link {{ request()->routeIs('admin.settings.customer-notifications*') ? 'active' : '' }}" href="{{ route('admin.settings.customer-notifications') }}">Customer Notifications</a>@endcan
                            </div>
                        </div>
                        @endcan
                    </nav>
                </div>

                <div class="p-3 border-top border-secondary small text-white-50">
                    Signed in as<br>
                    <span class="text-white">{{ auth()->user()->name }}</span>
                </div>
            </aside>

            <div class="admin-shell-content d-flex flex-column flex-grow-1 min-vh-100">
                <header class="navbar bg-white border-bottom shadow-sm sticky-top">
                    <div class="container-fluid admin-toolbar">
                        <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-expanded="false" aria-label="Open admin navigation">
                            Menu
                        </button>
                        <span class="navbar-text fw-semibold ms-2">@yield('title', 'Admin')</span>
                        <div class="dropdown ms-auto">
                            <button class="btn btn-light border dropdown-toggle admin-user-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open account menu for {{ auth()->user()->name }}">
                                {{ auth()->user()->name }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><span class="dropdown-item-text small text-muted">{{ auth()->user()->username }}@if(auth()->user()->email)<br>{{ auth()->user()->email }}@endif</span></li>
                                <li><a class="dropdown-item" href="{{ route('admin.profile.edit') }}">My Profile</a></li>
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

                <main id="admin-main-content" class="admin-main flex-grow-1 p-3 p-lg-4" tabindex="-1">
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
        <main id="admin-main-content" tabindex="-1">@yield('content')</main>
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
