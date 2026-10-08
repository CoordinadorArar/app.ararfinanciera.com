@extends('layouts.app')
@push('styles')
    <link href="{{ asset('css/forms-datos.css') }}" rel="stylesheet">
@endpush
@section('content')
    <div class="ui-contenedor registro-tercero" data-url-procesos="{{ route('lista-procesos') }}" data-url-registro="{{ route('form-crear-tercero') }}">
        <header class="ui-encabezado">
            <div>
                <h1 class="ui-titulo">Registro de cliente</h1>
                <p class="ui-descripcion">Datos del cliente, cálculo de cupo y autorización de datos</p>
                <p class="ui-descripcion fw-semibold d-none" id="resumenCliente"></p>
            </div>
        </header>
        <nav aria-label="Progreso del registro">
            <ol class="ui-stepper" id="listaPasos"></ol>
            <p class="ui-stepper-resumen d-sm-none" id="stepperResumen"></p>
        </nav>
        <p class="visually-hidden" aria-live="polite" id="anuncioPaso"></p>
        <div class="ui-card ui-vacio d-none" id="cargaRegistro" aria-busy="true">
            <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
            <p class="ui-vacio-titulo mt-2">Cargando registro…</p>
        </div>
        <div class="d-none" id="errorRegistro">
            <div class="ui-alerta ui-alerta-error" role="alert">
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                <span class="ui-alerta-texto">No encontramos el registro solicitado.</span>
                <div class="ui-alerta-accion">
                    <a href="{{ route('form-crear-tercero') }}" class="btn btn-sm ui-btn ui-btn-sec"><i class="fas fa-plus" aria-hidden="true"></i><span>Iniciar un registro nuevo</span></a>
                </div>
            </div>
        </div>
        @include('perfilamiento.registro-datos.form-datos-personales')
        @include('perfilamiento.registro-datos.form-datos-financieros')
        @include('perfilamiento.registro-datos.form-tratamiento-datos')
        <section class="ui-card d-none" id="data-finished" aria-labelledby="titulo-final"></section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/form-datos.js') }}"></script>
@endpush
