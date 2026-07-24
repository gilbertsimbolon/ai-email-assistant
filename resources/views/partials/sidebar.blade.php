<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{ route('dashboard') }}" class="app-brand-link gap-1">
            <span class="app-brand-logo demo">
                <img src="{{ asset('img/logo.jpeg') }}" style="width:40px;height:auto;object-fit:contain;">
            </span>

            <span class="app-brand-text demo text-heading fw-bold">
                AI Email
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
        <li class="menu-item">
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
                <li class="menu-item {{ request()->get('status') === 'pending_review' ? 'active' : '' }}">
                    <a href="{{ route('inbox.index', ['status' => 'pending_review']) }}" class="menu-link">
                        <div>Pending Review</div>
                    </a>
                </li>

                <li class="menu-item {{ request()->get('status') === 'replied' ? 'active' : '' }}">
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

                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div>Company</div>
                    </a>
                </li>

                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div>API Configuration</div>
                    </a>
                </li>

                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div>Users</div>
                    </a>
                </li>

                <li class="menu-item">
                    <a href="#" class="menu-link">
                        <div>Profile</div>
                    </a>
                </li>

            </ul>
        </li>

    </ul>
</aside>
