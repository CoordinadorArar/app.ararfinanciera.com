@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/deterioro.css') }}">
@section('content')
<meta name="csrf-token-deterioro" content="{{ csrf_token() }}" />
<div class="det-contenedor" id="divCortes">

    <div class="det-encabezado">
        <div>
            <h4 class="det-titulo">Deterioro de cartera</h4>
            <p class="det-subtitulo">Cortes mensuales, cálculo contable y controles de cuadre</p>
        </div>
        <button class="btn btn-primary btn-sm" id="btnNuevoCorte" onclick="abrirNuevoCorte()">
            <i class="fas fa-plus"></i>&nbsp; Nuevo corte
        </button>
    </div>

    <div class="det-aviso info" id="avisoOrigen" style="display:none">
        <i class="fas fa-database mt-1"></i>
        <div id="textoOrigen"></div>
    </div>

    <div class="det-panel">
        <h6>Cortes registrados</h6>
        <div class="det-scroll">
            <table class="det-tabla" id="tablaCortes">
                <thead>
                    <tr>
                        <th>Corte</th>
                        <th>Estado</th>
                        <th class="num">Cuotas</th>
                        <th class="num">Operaciones</th>
                        <th class="num">Capital</th>
                        <th class="num">Deterioro contable</th>
                        <th>Cuadres</th>
                        <th>Ejecutado</th>
                        <th class="num">Duración</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbodyCortes"></tbody>
            </table>
        </div>
    </div>

    <div class="det-panel" id="panelPasos" style="display:none">
        <h6>Última corrida</h6>
        <ul class="det-pasos" id="listaPasos"></ul>
    </div>

</div>

<!-- Nuevo corte -->
<div class="modal fade" id="modalNuevoCorte" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Nuevo corte</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="det-aviso info">
                    <i class="fas fa-circle-info mt-1"></i>
                    <div>
                        El módulo toma la cartera tal como esté cargada en el origen. Antes de
                        crear el corte, confirma que el periodo cargado sea el que corresponde.
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="fechaCorte">Fecha de corte</label>
                    <input type="date" class="form-control form-control-sm" id="fechaCorte" name="fechaCorte">
                    <span class="invalid-feedback d-block" id="error-fechaCorte"></span>
                </div>
                <div class="mb-1">
                    <label class="form-label" for="fechaComparacion">Fecha de comparación <span class="text-muted">(opcional)</span></label>
                    <input type="date" class="form-control form-control-sm" id="fechaComparacion" name="fechaComparacion">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="crearCorte()">Crear</button>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('js/deterioro-comun.js') }}"></script>
<script src="{{ asset('js/deterioro-cortes.js') }}"></script>
