<nav class="nav nav-pills flex-column flex-sm-row gap-2 mb-4" aria-label="Settings navigation">
    <a class="nav-link {{ request()->routeIs('admin.settings.company-branding*') ? 'active' : 'border' }}" href="{{ route('admin.settings.company-branding') }}">Company &amp; Branding</a>
    <a class="nav-link {{ request()->routeIs('admin.settings.website*') ? 'active' : 'border' }}" href="{{ route('admin.settings.website') }}">Website</a>
    <a class="nav-link {{ request()->routeIs('admin.settings.office*') ? 'active' : 'border' }}" href="{{ route('admin.settings.office') }}">Office</a>
    <a class="nav-link {{ request()->routeIs('admin.settings.contact*') ? 'active' : 'border' }}" href="{{ route('admin.settings.contact') }}">Contact</a>
    <a class="nav-link {{ request()->routeIs('admin.settings.office-timings*') ? 'active' : 'border' }}" href="{{ route('admin.settings.office-timings') }}">Office Timings</a>
    <a class="nav-link {{ request()->routeIs('admin.settings.holidays*') ? 'active' : 'border' }}" href="{{ route('admin.settings.holidays') }}">Holidays</a>
    @can('notifications.manage')<a class="nav-link {{ request()->routeIs('admin.settings.customer-notifications*') ? 'active' : 'border' }}" href="{{ route('admin.settings.customer-notifications') }}">Customer Notifications</a>@endcan
</nav>
