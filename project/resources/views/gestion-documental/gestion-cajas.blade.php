@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/gestion-documental-folios.css') }}">
@section('content')
    <div class="container" style="min-height: 75vh;" id="divGestionCajas">
        <h5 class="text-center"><strong>Cajas Pendientes de Ubicación <i class="fas fa-box"></i></strong></h5>
        <meta name="csrf-token-gestion-cajas" content="{{ csrf_token() }}" />

        <div class="container-fluid my-3">
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr class="bg-primary">
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Facturas</th>
                            <th>Piso</th>
                            <th>Pasillo</th>
                            <th>Estante</th>
                            <th>Posición</th>
                            <th>Columna</th>
                            <th>Fila</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyCajasPendienteUbicacion">
                        <tr><td colspan="10" class="text-center">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/gestion-documental-cajas.js') }}"></script>
@endsection
