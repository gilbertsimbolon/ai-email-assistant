<!DOCTYPE html>
<html lang="id" class="layout-menu-fixed layout-navbar-fixed layout-compact">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <!-- Icon Web -->
    <link rel="icon" href="{{ asset('img/logo.jpeg') }}" type="image/x-icon">
    <title>@yield('title', 'AI Email Assistant')</title>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Restore desktop sidebar collapsed state before first paint, so a
         refresh doesn't flash the expanded sidebar before JS collapses it. --}}
    <script>
        (function() {
            try {
                if (window.innerWidth >= 1200 && localStorage.getItem('sidebarCollapsed') === 'true') {
                    document.documentElement.classList.add('layout-menu-collapsed');
                }
            } catch (e) {}
        })();
    </script>

    <!-- Bootstrap & Animate CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/fonts/iconify-icons.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('sneat/assets/css/demo.css') }}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/libs/apex-charts/apex-charts.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/app.css') }}" />

    <!-- Helpers & Config -->
    <script src="{{ asset('sneat/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('sneat/assets/js/config.js') }}"></script>
</head>

<body class="overflow-hidden">
    <div class="layout-wrapper layout-content-navbar vh-100 overflow-hidden">

        <div class="layout-container h-100 overflow-hidden">

            @if (!isset($hideLayout) || !$hideLayout)
                @include('partials.sidebar')
            @endif

            <div class="layout-page h-100 overflow-hidden d-flex flex-column">

                @if (!isset($hideLayout) || !$hideLayout)
                    @include('partials.navbar')
                @endif

                <div class="content-wrapper flex-grow-1 overflow-hidden d-flex flex-column">

                    <div class="container-fluid flex-grow-1 overflow-hidden d-flex flex-column @yield('content-padding', 'p-4')">
                        @yield('content')
                    </div>

                    @include('components.alert')

                    @if (!isset($hideLayout) || !$hideLayout)
                        @include('partials.footer')
                    @endif

                </div>

            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="{{ asset('sneat/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('sneat/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('sneat/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('sneat/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('sneat/assets/vendor/js/menu.js') }}"></script>

    <!-- Vendors JS -->
    <script src="{{ asset('sneat/assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('sneat/assets/js/main.js') }}"></script>
    <script src="{{ asset('sneat/assets/js/dashboards-analytics.js') }}"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>

    <!-- Third Party JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @stack('scripts')
</body>

</html>
