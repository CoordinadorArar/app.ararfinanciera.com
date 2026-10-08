@extends('layouts.app')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/home.css') }}">
@endpush
@section('content')
    <div class="ui-contenedor">
        <div class="ui-encabezado">
            <div>
                <h1 class="ui-titulo">Hola, {{ $shellUsuario['primerNombre'] }}</h1>
                <p class="ui-descripcion">@if($shellUsuario['rol']){{ $shellUsuario['rol'] }} · @endif<span id="inicioFecha"></span></p>
            </div>
        </div>
        <div id="inicioResumen" aria-busy="true">
            <p class="visually-hidden" role="status">Cargando tu resumen…</p>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/home.js') }}"></script>
@endpush
