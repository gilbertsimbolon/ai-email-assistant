@php
    $breadcrumbSection = match (true) {
        request()->routeIs('dashboard') => 'Dashboard',
        request()->routeIs('inbox.*') => 'Inbox',
        request()->routeIs('ai-center.*') => 'AI Center',
        request()->routeIs('reports.*') => 'Reports',
        request()->routeIs('settings.*') => 'Administration',
        request()->routeIs('profil.*') => 'Administration',
        default => 'AI Email Assistant',
    };

    $breadcrumbPage = match (true) {
        request()->routeIs('dashboard') => null,
        request()->routeIs('inbox.whatsapp') => 'WhatsApp',
        request()->routeIs('inbox.show') => 'Conversation',
        request()->routeIs('inbox.index') => 'Email',
        request()->routeIs('ai-center.dashboard') => 'Dashboard',
        request()->routeIs('ai-center.intents.*') => 'Intent Builder',
        request()->routeIs('ai-center.sops.*') => 'SOP Builder',
        request()->routeIs('ai-center.workflows.*') => 'Workflow Builder',
        request()->routeIs('ai-center.reply-templates.*') => 'Reply Templates',
        request()->routeIs('ai-center.knowledge-bases.*') => 'Knowledge Base',
        request()->routeIs('ai-center.forbidden-actions.*') => 'Forbidden Actions',
        request()->routeIs('ai-center.ai-models.*') => 'AI Models',
        request()->routeIs('ai-center.ai-parameters.*') => 'AI Parameters',
        request()->routeIs('ai-center.prompt-preview.*') => 'Prompt Preview',
        request()->routeIs('ai-center.playground.*') => 'Playground',
        request()->routeIs('ai-center.ai-logs.*') => 'AI Logs',
        request()->routeIs('ai-center.settings.*') => 'Settings',
        request()->routeIs('reports.index') => 'Overview',
        request()->routeIs('reports.ai-usage') => 'AI Usage & Models',
        request()->routeIs('reports.content') => 'Content Analytics',
        request()->routeIs('reports.customers') => 'Customer Analytics',
        request()->routeIs('reports.gmail-accounts') => 'Gmail Analytics',
        request()->routeIs('reports.timeline') => 'Activity Timeline',
        request()->routeIs('settings.gmail-config.*') => 'Gmail API Configuration',
        request()->routeIs('settings.ai-config.*') => 'AI Configuration',
        request()->routeIs('settings.*') => 'Gmail Account',
        request()->routeIs('profil.*') => 'My Profile',
        default => null,
    };

    $breadcrumbHome = match (true) {
        request()->routeIs('inbox.*') => route('inbox.index'),
        request()->routeIs('ai-center.*') => route('ai-center.dashboard'),
        request()->routeIs('reports.*') => route('reports.index'),
        request()->routeIs('settings.*'), request()->routeIs('profil.*') => route('settings.index'),
        default => route('dashboard'),
    };
@endphp

<nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme px-4 py-2" id="layout-navbar">

    <div class="navbar-nav align-items-center d-xl-none me-3">
        <a class="nav-item nav-link layout-menu-toggle px-0" href="javascript:void(0);">
            <i class="bx bx-menu icon-md"></i>
        </a>
    </div>

    <a href="{{ route('dashboard') }}" class="app-brand-link d-flex d-xl-none align-items-center gap-2 text-decoration-none me-3">
        <img src="{{ asset('img/logo.jpeg') }}" style="width:32px;height:32px;object-fit:cover;border-radius:.5rem;">
    </a>

    <div class="navbar-nav-right d-flex align-items-center w-100" id="navbar-collapse">

        {{-- Left: title / breadcrumb --}}
        <div class="d-none d-lg-block me-auto">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ $breadcrumbHome }}" class="text-muted">{{ $breadcrumbSection }}</a>
                    </li>
                    @if ($breadcrumbPage)
                        <li class="breadcrumb-item active fw-semibold" aria-current="page">{{ $breadcrumbPage }}</li>
                    @endif
                </ol>
            </nav>
        </div>

        {{-- Center: global search --}}
        <div class="navbar-nav align-items-center mx-auto d-none d-lg-flex" style="width: 320px;">
            <div class="nav-item d-flex align-items-center w-100 border rounded px-2">
                <i class="bx bx-search icon-md text-muted"></i>
                <input type="text" class="form-control border-0 shadow-none ps-2" placeholder="Cari percakapan, pelanggan..." aria-label="Search">
            </div>
        </div>

        {{-- Right: notifications, connected gmail, user --}}
        <ul class="navbar-nav flex-row align-items-center ms-auto">

            <li class="nav-item d-none d-md-block me-2">
                <a href="{{ route('settings.index') }}" class="d-flex align-items-center text-decoration-none px-2 py-1 rounded {{ ($navbarGmailAccount ?? null) ? 'bg-label-success' : 'bg-label-warning' }}">
                    <i class="bx bx-envelope me-2"></i>
                    <span class="small fw-medium text-truncate" style="max-width: 180px;">
                        {{ ($navbarGmailAccount ?? null) ? $navbarGmailAccount->email : 'Hubungkan Gmail' }}
                    </span>
                </a>
            </li>

            <li class="nav-item navbar-dropdown dropdown-notifications dropdown me-2">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bx bx-bell icon-md"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end py-0">
                    <li class="dropdown-menu-header border-bottom">
                        <div class="dropdown-header d-flex align-items-center py-3">
                            <h6 class="mb-0 me-auto">Notifikasi</h6>
                        </div>
                    </li>
                    <li class="dropdown-notifications-list">
                        <div class="text-center text-muted py-4 px-3">
                            <i class="bx bx-bell-off bx-md mb-2 d-block mx-auto"></i>
                            <small>Belum ada notifikasi baru</small>
                        </div>
                    </li>
                </ul>
            </li>

            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                        <span class="avatar-initial rounded-circle bg-label-primary">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="{{ route('profil.index') }}">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar avatar-online">
                                        <span class="avatar-initial rounded-circle bg-label-primary">
                                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">{{ auth()->user()->name }}</h6>
                                    <small class="text-muted">{{ auth()->user()->email }}</small>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <div class="dropdown-divider"></div>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profil.index') }}">
                            <i class="bx bx-user me-2"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('settings.index') }}">
                            <i class="bx bx-cog me-2"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li>
                        <div class="dropdown-divider"></div>
                    </li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="bx bx-power-off me-2"></i>
                                <span>Keluar</span>
                            </button>
                        </form>
                    </li>
                </ul>
            </li>

        </ul>
    </div>
</nav>
