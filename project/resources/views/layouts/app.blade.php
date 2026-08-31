<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" oncontextmenu="return true">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/font-awesome/all.min.css')}}" rel="stylesheet">
    <link href="{{ asset('css/navbar-sidebar.css') }}" rel="stylesheet">
    {{-- <link href="{{ asset('css/bootstrap-datetimepicker.min.css') }}" rel="stylesheet"> --}}
    <link href="{{ asset('css/jquery.datetimepicker.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/preloader.css') }}" rel="stylesheet">
    <link href="{{ asset('css/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
</head>
<body>
    <div class="wrapper d-flex align-items-stretch">
        @auth
            @include('layouts.sidebar.sidebar')
        @endauth
        <!-- Page Content  -->
        <div class="container-fluid container-init">
            @auth
                @include('layouts.navbar.navbar')
            @endauth
            <script src="{{ asset('js/app.js') }}"></script>
            <script src="{{ asset('js/font-awesome/all.min.js') }}"></script>
            <script src="{{ asset('js/font-awesome/brands.min.js') }}"></script>
            <script src="{{ asset('js/font-awesome/regular.min.js') }}"></script>
            <script src="{{ asset('js/font-awesome/solid.min.js') }}"></script>
            <script src="{{ asset('js/funciones-globales.js') }}"></script>
            <script src="{{ asset('js/sweetalert2@11.js') }}"></script>
            <script src="{{ asset('js/moment.js') }}"></script>
            {{-- <script src="{{ asset('js/bootstrap-datetimepicker.min.js') }}"></script> --}}
            <script src="{{ asset('js/jquery.datetimepicker.full.min.js') }}"></script>
            <script src="{{ asset('js/jquery.preloader.min.js') }}"></script>
            <script src="{{ asset('js/jquery.dataTables.min.js') }}"></script>
            <script src="{{ asset('js/dataTables.bootstrap5.min.js') }}"></script>
            @auth
                <script src="{{ asset('js/main.js') }}" ></script>
            @endauth
            <div class="row">
                <div class="col pt-1">
                    <!-- toggler -->
                    <main class="pt-2 pb-3">
                        @yield('content')
                    </main>
                </div>
            </div>
        </div>
    </div>
    @auth
        @include('layouts.footer.footer')
    @endauth
</body>
</html>
