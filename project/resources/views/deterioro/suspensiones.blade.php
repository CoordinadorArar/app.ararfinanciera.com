@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="{{ asset('css/deterioro.css') }}">
<meta name="csrf-token-deterioro" content="{{ csrf_token() }}" />
<div class="det-contenedor" id="divSuspensiones">

    <div class="det-encabezado">
        <div class="det-titular">
            <nav class="det-migas">
                <a href="{{ url('/deterioro-cortes') }}">Cortes</a>
                <span>&rsaquo;</span>
                <a id="migaResumen" href="#">Resumen <span id="migaFecha"></span></a>
                <span>&rsaquo;</span>
                <span class="actual">Intereses suspendidos</span>
            </nav>
            <h4 class="det-titulo">Intereses suspendidos <span id="badgeEstado"></span></h4>
            <p class="det-subtitulo">Marcas de suspensión de interés por causal registrada y su efecto sobre la base de deterioro de este corte</p>
        </div>
        <div>
            <a class="btn btn-secondary btn-sm" href="{{ url('/deterioro-cortes') }}"><i class="fas fa-arrow-left"></i>&nbsp; Cortes</a>
            <a class="btn btn-light btn-sm" id="btnResumenSus" href="#"><i class="fas fa-table"></i>&nbsp; Resumen</a>
            <a class="btn btn-light btn-sm" id="btnDetalleSus" href="#"><i class="fas fa-list"></i>&nbsp; Detalle</a>
            <a class="btn btn-light btn-sm" id="btnComparativoSus" href="#"><i class="fas fa-scale-balanced"></i>&nbsp; Contable contra fiscal</a>
            <a class="btn btn-light btn-sm" id="btnEvolucionSus" href="#"><i class="fas fa-chart-line"></i>&nbsp; Evolución</a>
            <a class="btn btn-light btn-sm" id="btnConciliacionSus" href="#"><i class="fas fa-scale-unbalanced"></i>&nbsp; Conciliación</a>
            <a class="btn btn-success btn-sm" id="btnControlesSus" href="#"><i class="fas fa-lock"></i>&nbsp; Controles y cierre</a>
        </div>
        <div class="det-acciones">
            <a class="btn btn-sm det-btn-ayuda" href="{{ url('/deterioro-ayuda') }}?volver={{ urlencode(request()->getRequestUri()) }}"
               data-bs-toggle="tooltip" title="Guía de uso del módulo" aria-label="Guía de uso del módulo"><i class="fas fa-question"></i><span class="rot">&nbsp; Guía de uso</span></a>
            <span id="accionesExportar"></span>
        </div>
    </div>

    <div class="det-panel">
        <div class="det-panel-cab">
            <div>
                <h6>Efecto sobre este corte</h6>
                <p class="det-subtitulo">Marcas cuyo evento afecta la fecha de este corte</p>
            </div>
        </div>
        <div class="det-tarjetas" id="tarjetasEfecto"></div>
    </div>

    <div class="det-panel" id="panelVigentes">
        <div class="det-panel-cab">
            <div>
                <h6>Marcas vigentes <span id="chipSinCongelar"></span></h6>
                <p class="det-subtitulo">Suspensiones activas en el sistema, con su efecto sobre este corte</p>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="abrirMarcar()">
                <i class="fas fa-circle-pause"></i>&nbsp; Marcar operación
            </button>
        </div>
        <div id="vacioVigentes"></div>
        <div class="det-scroll" id="scrollVigentes">
            <table class="det-tabla" id="tablaVigentes">
                <thead>
                    <tr>
                        <th>Operación</th>
                        <th>Cliente</th>
                        <th>Causal</th>
                        <th>Fecha del evento</th>
                        <th class="num">Interés congelado</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbodyVigentes"></tbody>
            </table>
        </div>
    </div>

    <div class="det-panel" id="panelCandidatas">
        <div class="det-panel-cab">
            <div>
                <h6>Candidatas sugeridas</h6>
                <p class="det-subtitulo">Operaciones en mora avanzada de este corte, sin marca vigente</p>
            </div>
        </div>
        <div id="vacioCandidatas"></div>
        <div class="det-scroll" id="scrollCandidatas">
            <table class="det-tabla" id="tablaCandidatas">
                <thead>
                    <tr>
                        <th>Operación</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th>Rango</th>
                        <th class="num">Días de mora</th>
                        <th class="num">Capital vencido</th>
                        <th class="num">Interés vencido</th>
                        <th class="num">Base de deterioro</th>
                        <th class="num">Deterioro contable</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbodyCandidatas"></tbody>
            </table>
        </div>
    </div>

    <div class="det-panel" id="panelHistorico">
        <h6>Histórico de marcas levantadas</h6>
        <div id="vacioHistorico"></div>
        <div class="det-scroll" id="scrollHistorico">
            <table class="det-tabla" id="tablaHistorico">
                <thead>
                    <tr>
                        <th>Operación</th>
                        <th>Cliente</th>
                        <th>Causal</th>
                        <th>Fecha del evento</th>
                        <th class="num">Interés congelado</th>
                        <th>Fecha de reactivación</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody id="tbodyHistorico"></tbody>
            </table>
        </div>
    </div>

    <div class="det-panel">
        <h6>Controles de cuadre</h6>
        <div id="listaCuadresSus"></div>
    </div>

</div>

<div class="modal fade" id="modalMarcarSuspension" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Marcar operación</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="marcarOperacion">Operación</label>
                    <input type="text" class="form-control form-control-sm" id="marcarOperacion" name="idOperacion">
                    <span class="invalid-feedback d-block" id="error-idOperacion"></span>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="marcarCausal">Causal</label>
                    <select class="form-select form-select-sm" id="marcarCausal" name="causal"></select>
                    <span class="invalid-feedback d-block" id="error-causal"></span>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="marcarFecha">Fecha del evento</label>
                    <input type="date" class="form-control form-control-sm" id="marcarFecha" name="fechaEvento">
                    <span class="invalid-feedback d-block" id="error-fechaEvento"></span>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="marcarObservacion">Observación</label>
                    <textarea class="form-control form-control-sm" id="marcarObservacion" name="observacion" rows="2"></textarea>
                    <span class="invalid-feedback d-block" id="error-observacion"></span>
                </div>
                <div class="mb-1">
                    <label class="form-label" for="marcarSoporte">Soporte <span class="det-subtitulo">(opcional)</span></label>
                    <input type="file" class="form-control form-control-sm" id="marcarSoporte" name="soporte" accept="application/pdf,image/jpeg,image/png" onchange="validarSoporte()">
                    <span class="det-subtitulo d-block">PDF o imagen escaneada (JPG o PNG), hasta 20 MB.</span>
                    <div class="det-archivo d-none" id="lineaSoporte"></div>
                    <span class="invalid-feedback d-block" id="error-soporte"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="confirmarMarcar()">Marcar</button>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('js/deterioro-comun.js') }}"></script>
<script src="{{ asset('js/deterioro-exportar.js') }}"></script>
<script src="{{ asset('js/deterioro-suspensiones.js') }}"></script>
@endsection
