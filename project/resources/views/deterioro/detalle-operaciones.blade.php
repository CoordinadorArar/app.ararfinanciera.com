@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/deterioro.css') }}">
@section('content')
<meta name="csrf-token-deterioro" content="{{ csrf_token() }}" />
<div class="det-contenedor" id="divDetalle">

    <div class="det-encabezado">
        <div>
            <h4 class="det-titulo">Detalle por operación <span id="tituloFecha" class="text-muted"></span></h4>
            <p class="det-subtitulo">Cada cifra desciende hasta la cuota de origen</p>
        </div>
        <div>
            <a class="btn btn-light btn-sm" href="{{ url('/deterioro-cortes') }}"><i class="fas fa-arrow-left"></i>&nbsp; Cortes</a>
            <a class="btn btn-light btn-sm" id="btnResumen" href="#"><i class="fas fa-table"></i>&nbsp; Resumen</a>
        </div>
    </div>

    <div class="det-filtros">
        <div class="campo">
            <label for="filtroProducto">Producto</label>
            <select class="form-select form-select-sm" id="filtroProducto" onchange="cargarDetalle()">
                <option value="">Todos</option>
                <option value="LIBRANZAS">Libranzas</option>
                <option value="FINANCIACION">Financiación</option>
                <option value="FACTORING">Factoring</option>
            </select>
        </div>
        <div class="campo">
            <label for="filtroRango">Rango</label>
            <select class="form-select form-select-sm" id="filtroRango" onchange="cargarDetalle()">
                <option value="">Todos</option>
                <option value="Corriente">Corriente</option>
                <option value="A">A · 0 a 30</option>
                <option value="B">B · 31 a 90</option>
                <option value="C">C · 91 a 180</option>
                <option value="D">D · 181 a 360</option>
                <option value="E">E · 361 a 720</option>
                <option value="F">F · 721 en adelante</option>
            </select>
        </div>
        <div class="campo">
            <label for="filtroBusqueda">Cliente u operación</label>
            <input type="text" class="form-control form-control-sm" id="filtroBusqueda"
                   placeholder="Nombre, documento o número" onkeypress="if(event.key==='Enter')cargarDetalle()">
        </div>
        <div class="campo">
            <label>&nbsp;</label>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="filtroSoloDeterioro" onchange="cargarDetalle()">
                <label class="form-check-label" for="filtroSoloDeterioro" style="text-transform:none;font-size:.84rem">Solo con deterioro</label>
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="cargarDetalle()"><i class="fas fa-filter"></i>&nbsp; Filtrar</button>
    </div>

    <div class="det-panel">
        <div class="det-scroll">
            <table class="det-tabla" id="tablaDetalle" style="width:100%">
                <thead>
                    <tr>
                        <th>Operación</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th>Rango</th>
                        <th class="num">Días mora</th>
                        <th class="num">Cuotas</th>
                        <th class="num">Capital vencido</th>
                        <th class="num">Interés vencido</th>
                        <th class="num">Base</th>
                        <th class="num">%</th>
                        <th class="num">Deterioro</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbodyDetalle"></tbody>
            </table>
        </div>
    </div>

</div>

<!-- Cuotas de la operación -->
<div class="modal fade" id="modalCuotas" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="tituloCuotas">Cuotas</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="det-scroll">
                    <table class="det-tabla">
                        <thead>
                            <tr>
                                <th class="num">Cuota</th>
                                <th>Inicio</th>
                                <th>Vencimiento</th>
                                <th class="num">Días mora</th>
                                <th>Estado</th>
                                <th class="num">Capital</th>
                                <th class="num">Interés</th>
                                <th class="num">Administración</th>
                                <th class="num">Capital vencido</th>
                                <th class="num">Interés vencido</th>
                                <th class="num">Interés de mora</th>
                                <th class="num">Capital mes anterior</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyCuotas"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('js/deterioro-comun.js') }}"></script>
<script src="{{ asset('js/deterioro-detalle.js') }}"></script>
