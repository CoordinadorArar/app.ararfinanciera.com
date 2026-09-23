@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="{{ asset('css/deterioro.css') }}">
<meta name="csrf-token-deterioro" content="{{ csrf_token() }}" />
<div class="det-contenedor" id="divConciliacion">

    <div class="det-encabezado">
        <div class="det-titular">
            <nav class="det-migas">
                <a href="{{ url('/deterioro-cortes') }}">Cortes</a>
                <span>&rsaquo;</span>
                <a id="migaResumen" href="#">Resumen <span id="migaFecha"></span></a>
                <span>&rsaquo;</span>
                <span class="actual">Conciliación con SIESA</span>
            </nav>
            <h4 class="det-titulo">Conciliación con SIESA <span id="badgeEstado"></span></h4>
            <p class="det-subtitulo">Cruce por operación del saldo de SIESA contra el del sistema de factoring, con explicación de cada partida</p>
            <div class="det-anclas" id="anclasPaneles"></div>
        </div>
        <div>
            <a class="btn btn-secondary btn-sm" href="{{ url('/deterioro-cortes') }}"><i class="fas fa-arrow-left"></i>&nbsp; Cortes</a>
            <a class="btn btn-light btn-sm" id="btnResumenCo" href="#"><i class="fas fa-table"></i>&nbsp; Resumen</a>
            <a class="btn btn-light btn-sm" id="btnDetalleCo" href="#"><i class="fas fa-list"></i>&nbsp; Detalle</a>
            <a class="btn btn-light btn-sm" id="btnComparativoCo" href="#"><i class="fas fa-scale-balanced"></i>&nbsp; Contable contra fiscal</a>
            <a class="btn btn-light btn-sm" id="btnEvolucionCo" href="#"><i class="fas fa-chart-line"></i>&nbsp; Evolución</a>
            <a class="btn btn-light btn-sm" id="btnSuspensionesCo" href="#"><i class="fas fa-circle-pause"></i>&nbsp; Intereses suspendidos</a>
            <a class="btn btn-success btn-sm" id="btnControlesCo" href="#"><i class="fas fa-lock"></i>&nbsp; Controles y cierre</a>
        </div>
        <div class="det-acciones">
            <a class="btn btn-sm det-btn-ayuda" href="{{ url('/deterioro-ayuda') }}?volver={{ urlencode(request()->getRequestUri()) }}"
               data-bs-toggle="tooltip" title="Guía de uso del módulo" aria-label="Guía de uso del módulo"><i class="fas fa-question"></i><span class="rot">&nbsp; Guía de uso</span></a>
            <span id="accionesExportar"></span>
        </div>
    </div>

    <div id="avisoConciliacion"></div>

    <div class="det-tarjetas" id="tarjetasConciliacion"></div>

    <div class="det-filtros">
        <div class="campo">
            <label for="filtroEstadoConc">Estado</label>
            <select class="form-select form-select-sm" id="filtroEstadoConc" onchange="cargarConciliacion()">
                <option value="PENDIENTE" selected>Pendientes</option>
                <option value="EN_GESTION">En gestión</option>
                <option value="EXPLICADA">Explicadas</option>
                <option value="">Todas</option>
            </select>
        </div>
        <div class="campo">
            <label for="filtroBusquedaConc">Cliente, NIT u operación</label>
            <input type="text" class="form-control form-control-sm" id="filtroBusquedaConc"
                   placeholder="Nombre, NIT o número" onkeypress="if(event.key==='Enter')cargarConciliacion()">
        </div>
        <button class="btn btn-primary btn-sm" onclick="cargarConciliacion()"><i class="fas fa-filter"></i>&nbsp; Filtrar</button>
    </div>

    <div class="det-panel" id="panelSoloFactoring">
        <div class="det-panel-cab">
            <div>
                <h6>Operaciones sin saldo en SIESA <span id="chipSoloFactoring"></span></h6>
                <p class="det-subtitulo">El módulo deteriora estas operaciones y SIESA no reporta saldo de ellas. Sin saldo de SIESA para comparar.</p>
            </div>
        </div>
        <div id="vacioSoloFactoring"></div>
        <div class="det-scroll" id="scrollSoloFactoring">
            <table class="det-tabla" id="tablaSoloFactoring">
                <thead>
                    <tr>
                        <th>Operación</th>
                        <th>Cliente</th>
                        <th class="num">Saldo factoring</th>
                        <th class="num" data-bs-toggle="tooltip"
                            title="Negativa: SIESA reporta menos saldo que el sistema de factoring. Positiva: SIESA reporta más.">Diferencia (SIESA &minus; Factoring)</th>
                        <th>Estado</th>
                        <th>Explicación</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbodySoloFactoring"></tbody>
                <tfoot id="tfootSoloFactoring"></tfoot>
            </table>
        </div>
    </div>

    <div class="det-panel" id="panelSoloSiesa">
        <div class="det-panel-cab">
            <div>
                <h6>Saldos de SIESA sin operación en el corte <span id="chipSoloSiesa"></span></h6>
                <p class="det-subtitulo">SIESA reporta saldo de cartera que este corte no deteriora. Sin saldo de factoring para comparar.</p>
            </div>
        </div>
        <div id="vacioSoloSiesa"></div>
        <div class="det-scroll" id="scrollSoloSiesa">
            <table class="det-tabla" id="tablaSoloSiesa">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Cliente</th>
                        <th class="num">Saldo SIESA</th>
                        <th class="num" data-bs-toggle="tooltip"
                            title="Negativa: SIESA reporta menos saldo que el sistema de factoring. Positiva: SIESA reporta más.">Diferencia (SIESA &minus; Factoring)</th>
                        <th>Estado</th>
                        <th>Explicación</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbodySoloSiesa"></tbody>
                <tfoot id="tfootSoloSiesa"></tfoot>
            </table>
        </div>
    </div>

    <div class="det-panel" id="panelDiferencia">
        <div class="det-panel-cab">
            <div>
                <h6>Diferencias de saldo <span id="chipDiferencia"></span></h6>
                <p class="det-subtitulo">La operación existe en las dos fuentes con saldos distintos.</p>
            </div>
        </div>
        <div id="vacioDiferencia"></div>
        <div class="det-scroll" id="scrollDiferencia">
            <table class="det-tabla" id="tablaDiferencia">
                <thead>
                    <tr>
                        <th>Operación</th>
                        <th>Cliente</th>
                        <th class="num">Saldo SIESA</th>
                        <th class="num">Saldo factoring</th>
                        <th class="num" data-bs-toggle="tooltip"
                            title="Negativa: SIESA reporta menos saldo que el sistema de factoring. Positiva: SIESA reporta más.">Diferencia (SIESA &minus; Factoring)</th>
                        <th>Estado</th>
                        <th>Explicación</th>
                        <th></th>
                        <th class="num">Magnitud</th>
                    </tr>
                </thead>
                <tbody id="tbodyDiferencia"></tbody>
                <tfoot id="tfootDiferencia"></tfoot>
            </table>
        </div>
    </div>

    <div class="det-panel">
        <h6>Controles de cuadre</h6>
        <div id="listaCuadresConc"></div>
    </div>

</div>

<div class="modal fade" id="modalExplicarPartida" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Explicar partida</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3" id="explicarCabecera"></div>
                <div class="mb-3">
                    <label class="form-label" for="explicarEstado">Estado</label>
                    <select class="form-select form-select-sm" id="explicarEstado" name="estado"></select>
                    <span class="invalid-feedback d-block" id="error-estado"></span>
                </div>
                <div class="mb-1">
                    <label class="form-label" for="explicarTexto">Explicación</label>
                    <textarea class="form-control form-control-sm" id="explicarTexto" name="explicacion" rows="4"
                              maxlength="500" oninput="contarExplicacion()"
                              placeholder="Motivo por el que las dos fuentes no coinciden"></textarea>
                    <span class="det-subtitulo" id="contadorExplicacion">0 de 500</span>
                    <span class="invalid-feedback d-block" id="error-explicacion"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="confirmarExplicar()">Guardar</button>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('js/deterioro-comun.js') }}"></script>
<script src="{{ asset('js/deterioro-exportar.js') }}"></script>
<script src="{{ asset('js/deterioro-conciliacion.js') }}"></script>
@endsection
