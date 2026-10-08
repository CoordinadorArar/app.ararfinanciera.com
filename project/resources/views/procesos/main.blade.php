@extends('layouts.app')
@push('styles')
    <link href="{{ asset('css/procesos.css') }}" rel="stylesheet">
@endpush
@section('content')
    <div class="ui-contenedor proc" id="procesos" data-url-procesos="{{ route('lista-procesos') }}" data-url-registro="{{ route('form-crear-tercero') }}" data-ver-asesor="{{ collect($rol)->pluck('IdRol')->diff(config('procesos.soloPropios'))->isNotEmpty() ? 1 : 0 }}">
        <div id="vistaBandeja">
            <header class="ui-encabezado">
                <div>
                    <h1 class="ui-titulo" id="tituloBandeja" tabindex="-1">Procesos de crédito</h1>
                    <p class="ui-descripcion">Seguimiento de solicitudes por etapa</p>
                </div>
            </header>
            <div class="ui-contadores" role="group" aria-label="Filtrar por estado" id="contadores"></div>
            <div class="proc-barra">
                <div class="ui-buscador" role="search">
                    <label for="busquedaProceso" class="visually-hidden">Buscar por documento o nombre</label>
                    <i class="fas fa-magnifying-glass ui-buscador-icono" aria-hidden="true"></i>
                    <input type="text" id="busquedaProceso" class="form-control" placeholder="Buscar por documento o nombre" maxlength="100" autocomplete="off">
                    <button type="button" class="ui-buscador-limpiar d-none" id="limpiarBusqueda" aria-label="Limpiar búsqueda" onclick="limpiarBusqueda()"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                </div>
                <p class="proc-resumen" id="resumenBandeja" aria-live="polite"></p>
            </div>
            <section class="ui-card" aria-labelledby="tituloBandeja">
                <div id="bandejaContenido"></div>
            </section>
        </div>
        <div class="proc-detalle d-none" id="vistaDetalle"></div>
        @include('procesos.modales')
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/procesos.js') }}"></script>
@endpush
