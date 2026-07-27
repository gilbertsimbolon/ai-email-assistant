<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo d-flex justify-content-center">
        <a href="{{ route('dashboard') }}" class="app-brand-link gap-1">
            <span class="app-brand-text demo text-heading text-center fw-bold fs-5">
                AI Email Assistant
            </span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
        </a>
    </div>

    <div class="menu-divider mt-0"></div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">

        {{-- Dashboard --}}
        <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div>Dashboard</div>
            </a>
        </li>

        {{-- Inbox --}}
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Inbox</span>
        </li>

        <li class="menu-item {{ request()->routeIs('inbox.index') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-envelope"></i>
                <div>Inbox</div>
            </a>

            <ul class="menu-sub">
                <li class="menu-item {{ request()->input('status') === 'pending_review' ? 'active' : '' }}">
                    <a href="{{ route('inbox.index', ['status' => 'pending_review']) }}" class="menu-link">
                        <div>Pending Review</div>
                    </a>
                </li>

                <li class="menu-item {{ request()->input('status') === 'replied' ? 'active' : '' }}">
                    <a href="{{ route('inbox.index', ['status' => 'replied']) }}" class="menu-link">
                        <div>Replied</div>
                    </a>
                </li>
            </ul>
        </li>

        {{-- AI Center --}}
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">AI Center</span>
        </li>

        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-brain"></i>
                <div>AI Center</div>
            </a>

            <ul class="menu-sub">
                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div>AI Logs</div>
                    </a>
                </li>

                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div>Prompt Templates</div>
                    </a>
                </li>

                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div>SOP</div>
                    </a>
                </li>
            </ul>
        </li>

        {{-- Reports --}}
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Reports</span>
        </li>

        <li class="menu-item">
            <a href="#" class="menu-link">
                <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                <div>Reports</div>
            </a>
        </li>

        {{-- Administration --}}
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Administration</span>
        </li>

        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div>Settings</div>
            </a>

            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <a href="{{ route('settings.index') }}" class="menu-link">
                        <div>Gmail Account</div>
                    </a>
                </li>

                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div>Company</div>
                    </a>
                </li>

                @if (auth()->user()?->isAdmin())
                    <li class="menu-item {{ request()->routeIs('settings.gmail-config.*') ? 'active' : '' }}">
                        <a href="{{ route('settings.gmail-config.index') }}" class="menu-link">
                            <div>Gmail API Configuration</div>
                        </a>
                    </li>
                @endif

                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div>Users</div>
                    </a>
                </li>

                <li class="menu-item">
                    <a href="{{ route('profil.index') }}" class="menu-link">
                        <div>Profile</div>
                    </a>
                </li>
            </ul>
        </li>

    </ul>
</aside>
