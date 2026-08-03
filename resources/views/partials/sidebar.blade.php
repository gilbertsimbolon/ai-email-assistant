<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo d-flex align-items-center px-4 py-3">
        <a href="{{ route('dashboard') }}" class="app-brand-link gap-2 d-flex align-items-center text-decoration-none">
            {{-- app-brand-logo lets Sneat's own collapsed-sidebar CSS hide the
                 sibling app-brand-text and center this image automatically. --}}
            <img src="{{ asset('img/logo.jpeg') }}" class="rounded app-brand-logo"
                style="width:32px;height:32px;object-fit:cover;flex-shrink:0;">
            <span class="app-brand-text demo text-heading fw-bold fs-5 text-truncate">
                AI Email Assistant
            </span>
        </a>

        <a href="javascript:void(0);" id="desktopSidebarToggle" class="menu-link text-large ms-auto p-2"
            data-bs-toggle="tooltip" data-bs-placement="right" title="Toggle sidebar">

            <i class="bx bx-chevron-left align-middle"></i>

        </a>
    </div>

    <div class="menu-divider mt-0"></div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-2">

        {{-- Dashboard --}}
        <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}" class="menu-link" data-bs-toggle="tooltip" data-bs-placement="right"
                title="Dashboard">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div>Dashboard</div>
            </a>
        </li>

        {{-- Conversations --}}
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Conversations</span>
        </li>

        <li class="menu-item {{ request()->routeIs('inbox.*') ? 'active' : '' }}">
            <a href="{{ route('inbox.index') }}" class="menu-link d-flex align-items-center justify-content-between"
                data-bs-toggle="tooltip" data-bs-placement="right" title="Conversations">
                <i class="menu-icon tf-icons bx bx-conversation"></i>
                <div>Conversations</div>
            </a>
        </li>

        <li class="menu-item {{ request()->routeIs('gmail-inbox.*') ? 'active' : '' }}">
            <a href="{{ route('gmail-inbox.index') }}"
                class="menu-link d-flex align-items-center justify-content-between" data-bs-toggle="tooltip"
                data-bs-placement="right" title="Gmail Inbox">
                <i class="menu-icon tf-icons bx bx-envelope"></i>
                <div>Gmail Inbox</div>
                @if (($gmailUnreadCount ?? 0) > 0)
                    <span class="badge bg-primary rounded-pill">{{ $gmailUnreadCount }}</span>
                @endif
            </a>
        </li>

        {{-- AI Center --}}
        @canany(['manage ai center', 'manage models', 'manage prompt', 'manage workflow'])
            <li class="menu-header small text-uppercase">
                <span class="menu-header-text">AI Center</span>
            </li>

            <li class="menu-item {{ request()->routeIs('ai-center.*') ? 'active open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle" data-bs-toggle="tooltip"
                    data-bs-placement="right" title="AI Center">
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
                <a href="javascript:void(0);" class="menu-link menu-toggle" data-bs-toggle="tooltip"
                    data-bs-placement="right" title="Reports">
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
            <a href="javascript:void(0);" class="menu-link menu-toggle" data-bs-toggle="tooltip"
                data-bs-placement="right" title="Settings">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div>Settings</div>
            </a>

            <ul class="menu-sub">
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

        {{-- Integrations: legacy Gmail account/OAuth management, still needed
         to keep replies working on conversations that haven't migrated to
         GHL, but no longer surfaced as a first-class channel. --}}
        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link menu-toggle" data-bs-toggle="tooltip"
                data-bs-placement="right" title="Integrations">
                <i class="menu-icon tf-icons bx bx-plug"></i>
                <div>Integrations</div>
            </a>

            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('settings.index') ? 'active' : '' }}">
                    <a href="{{ route('settings.index') }}" class="menu-link">
                        <div>Email Connection</div>
                    </a>
                </li>

                @can('manage gmail')
                    <li class="menu-item {{ request()->routeIs('settings.gmail-config.*') ? 'active' : '' }}">
                        <a href="{{ route('settings.gmail-config.index') }}" class="menu-link">
                            <div>Email API Configuration</div>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
    </ul>

    <div class="menu-inner-bottom border-top p-3 mt-auto">

        <a href="{{ route('profil.index') }}"
            class="d-flex align-items-center text-decoration-none text-reset rounded p-2 mb-2 sidebar-profile-link"
            data-bs-toggle="tooltip" data-bs-placement="right" title="{{ auth()->user()->name }}">

            <div class="avatar avatar-sm me-2">
                <span class="avatar-initial rounded-circle bg-label-primary">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </span>
            </div>

            <div class="flex-grow-1 overflow-hidden sidebar-profile-info">
                <h6 class="mb-0 text-truncate">
                    {{ auth()->user()->name }}
                </h6>

                <small class="text-muted text-truncate d-block">
                    {{ auth()->user()->email }}
                </small>
            </div>

            <i class="bx bx-chevron-right fs-4 text-muted sidebar-profile-chevron"></i>
        </a>

        <form action="{{ route('logout') }}" method="POST">
            @csrf

            <button type="submit" class="btn btn-outline-danger w-100" data-bs-toggle="tooltip"
                data-bs-placement="right" title="Keluar">
                <i class="bx bx-log-out me-1"></i>
                <span class="sidebar-logout-text">Keluar</span>
            </button>
        </form>

    </div>
</aside>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const html = document.documentElement;
    const toggleButton = document.getElementById('desktopSidebarToggle');

    if (!toggleButton) return;

    const toggleIcon = toggleButton.querySelector('i');
    const STORAGE_KEY = 'sidebarCollapsed';
    // Sneat's own Helpers.toggleCollapsed()/setCollapsed() only manage the
    // mobile offcanvas ("layout-menu-expanded"); on screens >= LAYOUT_BREAKPOINT
    // it never adds "layout-menu-collapsed" itself, so the desktop rail state
    // is toggled here directly instead of duplicating a broken code path.
    const isDesktop = () => window.innerWidth >= (window.Helpers ? window.Helpers.LAYOUT_BREAKPOINT : 1200);

    function syncToggleUI() {
        const collapsed = isDesktop() && html.classList.contains('layout-menu-collapsed');

        toggleIcon.classList.toggle('bx-chevron-right', collapsed);
        toggleIcon.classList.toggle('bx-chevron-left', !collapsed);

        document.querySelectorAll('#layout-menu [data-bs-toggle="tooltip"]').forEach(function (el) {
            if (el === toggleButton) return;

            const tooltip = bootstrap.Tooltip.getOrCreateInstance(el);
            if (collapsed) {
                tooltip.enable();
            } else {
                tooltip.disable();
                tooltip.hide();
            }
        });
    }

    // Restore persisted desktop state (the inline <head> script already does
    // this before first paint; repeated here in case storage was unavailable then).
    if (isDesktop() && localStorage.getItem(STORAGE_KEY) === 'true') {
        html.classList.add('layout-menu-collapsed');
    }

    syncToggleUI();

    toggleButton.addEventListener('click', function (e) {
        e.preventDefault();

        if (!isDesktop()) {
            // Mobile: reuse Sneat's built-in offcanvas close, don't touch desktop state.
            window.Helpers.toggleCollapsed();
            return;
        }

        const collapsed = html.classList.toggle('layout-menu-collapsed');

        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');
        } catch (e) {}

        syncToggleUI();
    });

    window.addEventListener('resize', syncToggleUI);

});
</script>
@endpush
