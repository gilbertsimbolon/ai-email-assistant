<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo d-flex align-items-center px-4 py-3">
        <a href="{{ route('dashboard') }}" class="app-brand-link gap-2 d-flex align-items-center text-decoration-none">
            <img src="{{ asset('img/logo.jpeg') }}" class="rounded" style="width:32px;height:32px;object-fit:cover;flex-shrink:0;">
            <span class="app-brand-text demo text-heading fw-bold fs-5 text-truncate">
                AI Email Assistant
            </span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto p-0">
            <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
        </a>
    </div>

    <div class="menu-divider mt-0"></div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-2">

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

        <li class="menu-item {{ request()->routeIs('inbox.index') ? 'active' : '' }}">
            <a href="{{ route('inbox.index') }}" class="menu-link d-flex align-items-center justify-content-between">
                <div><i class="menu-icon tf-icons bx bx-envelope"></i> Email</div>
                @if (($emailUnreadCount ?? 0) > 0)
                    <span class="badge bg-primary rounded-pill">{{ $emailUnreadCount }}</span>
                @endif
            </a>
        </li>

        <li class="menu-item {{ request()->routeIs('inbox.whatsapp') ? 'active' : '' }}">
            <a href="{{ route('inbox.whatsapp') }}" class="menu-link d-flex align-items-center justify-content-between">
                <div><i class="menu-icon tf-icons bx bxl-whatsapp"></i> WhatsApp</div>
                <span class="badge bg-label-secondary rounded-pill">Soon</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link d-flex align-items-center justify-content-between disabled" aria-disabled="true">
                <div><i class="menu-icon tf-icons bx bxl-instagram"></i> Instagram</div>
                <span class="badge bg-label-secondary rounded-pill">Soon</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link d-flex align-items-center justify-content-between disabled" aria-disabled="true">
                <div><i class="menu-icon tf-icons bx bxl-messenger"></i> Messenger</div>
                <span class="badge bg-label-secondary rounded-pill">Soon</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link d-flex align-items-center justify-content-between disabled" aria-disabled="true">
                <div><i class="menu-icon tf-icons bx bx-chat"></i> Live Chat</div>
                <span class="badge bg-label-secondary rounded-pill">Soon</span>
            </a>
        </li>

        {{-- AI Center --}}
        @canany(['manage ai center', 'manage models', 'manage prompt', 'manage workflow'])
            <li class="menu-header small text-uppercase">
                <span class="menu-header-text">AI Center</span>
            </li>

            <li class="menu-item {{ request()->routeIs('ai-center.*') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons bx bx-brain"></i>
                    <div>AI Center</div>
                </a>

                <ul class="menu-sub">
                    @can('manage ai center')
                        <li class="menu-item {{ request()->routeIs('ai-center.dashboard') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.dashboard') }}" class="menu-link">
                                <div>Dashboard</div>
                            </a>
                        </li>
                        <li class="menu-item {{ request()->routeIs('ai-center.intents.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.intents.index') }}" class="menu-link">
                                <div>Intent Builder</div>
                            </a>
                        </li>
                        <li class="menu-item {{ request()->routeIs('ai-center.sops.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.sops.index') }}" class="menu-link">
                                <div>SOP Builder</div>
                            </a>
                        </li>
                    @endcan
                    @can('manage workflow')
                        <li class="menu-item {{ request()->routeIs('ai-center.workflows.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.workflows.index') }}" class="menu-link">
                                <div>Workflow Builder</div>
                            </a>
                        </li>
                    @endcan
                    @can('manage ai center')
                        <li class="menu-item {{ request()->routeIs('ai-center.reply-templates.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.reply-templates.index') }}" class="menu-link">
                                <div>Reply Templates</div>
                            </a>
                        </li>
                        <li class="menu-item {{ request()->routeIs('ai-center.knowledge-bases.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.knowledge-bases.index') }}" class="menu-link">
                                <div>Knowledge Base</div>
                            </a>
                        </li>
                        <li class="menu-item {{ request()->routeIs('ai-center.forbidden-actions.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.forbidden-actions.index') }}" class="menu-link">
                                <div>Forbidden Actions</div>
                            </a>
                        </li>
                    @endcan
                    @can('manage models')
                        <li class="menu-item {{ request()->routeIs('ai-center.ai-models.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.ai-models.index') }}" class="menu-link">
                                <div>AI Models</div>
                            </a>
                        </li>
                        <li class="menu-item {{ request()->routeIs('ai-center.ai-parameters.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.ai-parameters.edit') }}" class="menu-link">
                                <div>AI Parameters</div>
                            </a>
                        </li>
                    @endcan
                    @can('manage prompt')
                        <li class="menu-item {{ request()->routeIs('ai-center.prompt-preview.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.prompt-preview.index') }}" class="menu-link">
                                <div>Prompt Preview</div>
                            </a>
                        </li>
                        <li class="menu-item {{ request()->routeIs('ai-center.playground.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.playground.index') }}" class="menu-link">
                                <div>Playground</div>
                            </a>
                        </li>
                    @endcan
                    @can('manage ai center')
                        <li class="menu-item {{ request()->routeIs('ai-center.ai-logs.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.ai-logs.index') }}" class="menu-link">
                                <div>AI Logs</div>
                            </a>
                        </li>
                        <li class="menu-item {{ request()->routeIs('ai-center.settings.*') ? 'active' : '' }}">
                            <a href="{{ route('ai-center.settings.edit') }}" class="menu-link">
                                <div>Settings</div>
                            </a>
                        </li>
                    @endcan
                </ul>
            </li>
        @endcanany

        {{-- Reports --}}
        @canany(['manage reports', 'view reports'])
            <li class="menu-header small text-uppercase">
                <span class="menu-header-text">Reports</span>
            </li>

            <li class="menu-item {{ request()->routeIs('reports.*') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                    <div>Reports</div>
                </a>

                <ul class="menu-sub">
                    <li class="menu-item {{ request()->routeIs('reports.index') ? 'active' : '' }}">
                        <a href="{{ route('reports.index') }}" class="menu-link">
                            <div>Overview</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('reports.ai-usage') ? 'active' : '' }}">
                        <a href="{{ route('reports.ai-usage') }}" class="menu-link">
                            <div>AI Usage &amp; Models</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('reports.content') ? 'active' : '' }}">
                        <a href="{{ route('reports.content') }}" class="menu-link">
                            <div>Content Analytics</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('reports.customers') ? 'active' : '' }}">
                        <a href="{{ route('reports.customers') }}" class="menu-link">
                            <div>Customer Analytics</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('reports.gmail-accounts') ? 'active' : '' }}">
                        <a href="{{ route('reports.gmail-accounts') }}" class="menu-link">
                            <div>Gmail Analytics</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->routeIs('reports.timeline') ? 'active' : '' }}">
                        <a href="{{ route('reports.timeline') }}" class="menu-link">
                            <div>Activity Timeline</div>
                        </a>
                    </li>
                </ul>
            </li>
        @endcanany

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

                @can('manage gmail')
                    <li class="menu-item {{ request()->routeIs('settings.gmail-config.*') ? 'active' : '' }}">
                        <a href="{{ route('settings.gmail-config.index') }}" class="menu-link">
                            <div>Gmail API Configuration</div>
                        </a>
                    </li>
                @endcan

                @can('manage models')
                    <li class="menu-item {{ request()->routeIs('settings.ai-config.*') ? 'active' : '' }}">
                        <a href="{{ route('settings.ai-config.index') }}" class="menu-link">
                            <div>AI Configuration</div>
                        </a>
                    </li>
                @endcan

                @can('manage users')
                    <li class="menu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <a href="{{ route('users.index') }}" class="menu-link">
                            <div>Users</div>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
    </ul>

    <div class="menu-inner-bottom border-top p-3 mt-auto">

        <a href="{{ route('profil.index') }}"
            class="d-flex align-items-center text-decoration-none text-reset rounded p-2 mb-2 sidebar-profile-link">

            <div class="avatar avatar-sm me-2">
                <span class="avatar-initial rounded-circle bg-label-primary">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </span>
            </div>

            <div class="flex-grow-1 overflow-hidden">
                <h6 class="mb-0 text-truncate">
                    {{ auth()->user()->name }}
                </h6>

                <small class="text-muted text-truncate d-block">
                    {{ auth()->user()->email }}
                </small>
            </div>

            <i class="bx bx-chevron-right fs-4 text-muted"></i>
        </a>

        <form action="{{ route('logout') }}" method="POST">
            @csrf

            <button type="submit" class="btn btn-outline-danger w-100">
                <i class="bx bx-log-out me-1"></i>
                Keluar
            </button>
        </form>

    </div>
</aside>
