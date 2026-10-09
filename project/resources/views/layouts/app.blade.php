<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="csrf-token-menus" content="{{ csrf_token() }}">
        <meta name="csrf-token-ambiente" content="{{ csrf_token() }}">
        <script>try{if(localStorage.getItem('shellMenuOculto')==='1')document.documentElement.classList.add('shell-menu-oculto')}catch(e){}</script>
    @endauth

    <title>{{ config('database.ambiente') === 'demo' ? 'DEMO · ' : '' }}{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/font-awesome/all.min.css')}}" rel="stylesheet">
    <link href="{{ asset('css/navbar-sidebar.css') }}" rel="stylesheet">
    <link href="{{ asset('css/ui.css') }}" rel="stylesheet">
    <link href="{{ asset('css/jquery.datetimepicker.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/preloader.css') }}" rel="stylesheet">
    <link href="{{ asset('css/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
    @stack('styles')

    <script src="{{ asset('js/app.js') }}"></script>
    <script src="{{ asset('js/font-awesome/all.min.js') }}"></script>
    <script src="{{ asset('js/font-awesome/brands.min.js') }}"></script>
    <script src="{{ asset('js/font-awesome/regular.min.js') }}"></script>
    <script src="{{ asset('js/font-awesome/solid.min.js') }}"></script>
    <script src="{{ asset('js/funciones-globales.js') }}"></script>
    <script src="{{ asset('js/sweetalert2@11.js') }}"></script>
    <script src="{{ asset('js/moment.js') }}"></script>
    <script src="{{ asset('js/jquery.datetimepicker.full.min.js') }}"></script>
    <script src="{{ asset('js/jquery.preloader.min.js') }}"></script>
    <script src="{{ asset('js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('js/dataTables.bootstrap5.min.js') }}"></script>
    @auth
        <script src="{{ asset('js/main.js') }}"></script>
    @endauth
</head>
<body class="@auth shell @endauth">
    @auth
        <a class="shell-saltar" href="#contenido">Saltar al contenido</a>
        @include('layouts.sidebar.sidebar')
    @endauth
    <div class="shell-cuerpo">
        @auth
            @include('layouts.navbar.navbar')
        @endauth
        <main id="contenido" class="shell-main" tabindex="-1">
            @yield('content')
        </main>
        @auth
            @include('layouts.footer.footer')
        @endauth
    </div>
    @stack('scripts')
</body>
</html>
