@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="{{ asset('css/deterioro.css') }}">
<meta name="csrf-token-deterioro" content="{{ csrf_token() }}" />
<div class="det-contenedor" id="divControles">

    <div class="det-encabezado">
        <div class="det-titular">
            <nav class="det-migas">
                <a href="{{ url('/deterioro-cortes') }}">Cortes</a>
                <span>&rsaquo;</span>
                <a id="migaResumen" href="#">Resumen <span id="migaFecha"></span></a>
                <span>&rsaquo;</span>
                <span class="actual">Controles</span>
            </nav>
            <h4 class="det-titulo">Controles del corte <span id="badgeEstado"></span></h4>
            <p class="det-subtitulo">Los cinco controles del corte, con la revisión de las prórrogas que reducen la mora y la clasificación de las bajas del período</p>
            <div class="det-anclas" id="anclasControles"></div>
        </div>
        <div>
            <a class="btn btn-secondary btn-sm" href="{{ url('/deterioro-cortes') }}"><i class="fas fa-arrow-left"></i>&nbsp; Cortes</a>
            <a class="btn btn-light btn-sm" id="btnResumenCt" href="#"><i class="fas fa-table"></i>&nbsp; Resumen</a>
            <a class="btn btn-light btn-sm" id="btnDetalleCt" href="#"><i class="fas fa-list"></i>&nbsp; Detalle</a>
            <a class="btn btn-light btn-sm" id="btnComparativoCt" href="#"><i class="fas fa-scale-balanced"></i>&nbsp; Contable contra fiscal</a>
            <a class="btn btn-light btn-sm" id="btnEvolucionCt" href="#"><i class="fas fa-chart-line"></i>&nbsp; Evolución</a>
            <a class="btn btn-light btn-sm" id="btnSuspensionesCt" href="#"><i class="fas fa-circle-pause"></i>&nbsp; Intereses suspendidos</a>
            <a class="btn btn-light btn-sm" id="btnConciliacionCt" href="#"><i class="fas fa-scale-unbalanced"></i>&nbsp; Conciliación</a>
        </div>
        <div class="det-acciones">
            <a class="btn btn-sm det-btn-ayuda" href="{{ url('/deterioro-ayuda') }}?volver={{ urlencode(request()->getRequestUri()) }}"
               data-bs-toggle="tooltip" title="Guía de uso del módulo" aria-label="Guía de uso del módulo"><i class="fas fa-question"></i><span class="rot">&nbsp; Guía de uso</span></a>
            <span id="accionesExportar"></span>
        </div>
    </div>

    <div class="det-tarjetas" id="tarjetasControles"></div>

    <div class="det-panel" id="panelCierre">
        <div class="det-panel-cab">
            <div>
                <h6>Cierre del corte</h6>
                <p class="det-subtitulo">Requisitos que el corte tiene que cumplir para cerrarse, y la acción de cierre</p>
            </div>
        </div>
        <div id="avisoCierre"></div>
        <div id="listaRequisitos"></div>
        <div id="accionesCierre" class="d-flex justify-content-end gap-2 mt-3"></div>
        <div id="accionForzar" class="text-end mt-2"></div>
    </div>

    <div class="det-panel det-foto" id="panelFotoSalvedad" style="display:none">
        <h6>Cierre con salvedades</h6>
        <div id="contenidoFotoSalvedad"></div>
    </div>

    <div class="det-panel" id="panelTablero">
        <div class="det-panel-cab">
            <div>
                <h6>Tablero de controles</h6>
                <p class="det-subtitulo">C-1 y C-2 se gestionan en esta pantalla. C-3, C-4 y C-5 tienen pantalla propia y aquí sólo muestran su estado.</p>
            </div>
        </div>
        <div id="listaTablero"></div>
    </div>

    <div class="det-panel" id="panelProrrogas">
        <div class="det-panel-cab">
            <div>
                <h6>C-1 &middot; Prórrogas que reducen la antigüedad de la mora</h6>
                <p class="det-subtitulo" id="subtituloProrrogas">Operaciones cuya antigüedad de mora bajó respecto al corte anterior, con el deterioro que eso liberó</p>
            </div>
            <div class="d-flex gap-4" id="cifrasProrrogas"></div>
        </div>
        <div class="det-leyenda" id="leyendaProrrogas">
            <span><i class="aumenta"></i> El deterioro aumenta</span>
            <span><i class="libera"></i> El deterioro se libera</span>
        </div>
        <div id="vacioProrrogas"></div>
        <div class="det-scroll" id="scrollProrrogas">
            <table class="det-tabla" id="tablaProrrogas">
                <thead>
                    <tr>
                        <th>Operación</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th class="num">Días de mora anteriores</th>
                        <th class="num">Días de mora</th>
                        <th class="num">Días reducidos</th>
                        <th>Rango anterior</th>
                        <th>Rango</th>
                        <th class="num">Base de deterioro</th>
                        <th class="num">Deterioro liberado</th>
                        <th>Efecto</th>
                    </tr>
                </thead>
                <tbody id="tbodyProrrogas"></tbody>
            </table>
        </div>
        <div id="cuadreProrrogas"></div>
    </div>

    <div class="det-panel" id="panelBajas">
        <div class="det-panel-cab">
            <div>
                <h6>C-2 &middot; Bajas del período</h6>
                <p class="det-subtitulo" id="subtituloBajasCtrl">Operaciones presentes en el corte anterior que ya no están en este corte, con la causa de su salida</p>
            </div>
            <div class="d-flex gap-4" id="cifrasBajasCtrl"></div>
        </div>
        <div id="vacioBajasCtrl"></div>
        <div id="contenidoBajasCtrl">
            <div class="det-scroll mb-3">
                <table class="det-tabla" id="tablaDescomposicionBajas">
                    <thead>
                        <tr>
                            <th>Causa de la salida</th>
                            <th class="num">Operaciones</th>
                            <th class="num">Base que salió</th>
                            <th class="num">Deterioro que liberó</th>
                            <th class="num">Deducción fiscal cerrada</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyDescomposicionBajas"></tbody>
                </table>
            </div>
            <div class="det-scroll">
                <table class="det-tabla" id="tablaBajasCtrl">
                    <thead id="theadBajasCtrl"></thead>
                    <tbody id="tbodyBajasCtrl"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="det-panel" id="panelCuadresCtrl">
        <h6>Controles de cuadre</h6>
        <div id="listaCuadresCtrl"></div>
    </div>

    <details class="det-panel det-referencia" id="panelBitacora" style="display:none" ontoggle="cargarBitacora()">
        <summary>
            <i class="fas fa-chevron-right det-caret"></i>
            Bitácora del corte <span id="badgeBitacora"></span>
            <span class="det-subtitulo">Cada acto del módulo sobre este corte, con su autor, su fecha y su dirección IP</span>
        </summary>
        <div class="det-filtros">
            <div class="campo">
                <label for="filtroAccionBit">Acción</label>
                <select class="form-select form-select-sm" id="filtroAccionBit" onchange="cargarBitacora(true)"></select>
            </div>
            <div class="campo">
                <label for="filtroUsuarioBit">Usuario</label>
                <select class="form-select form-select-sm" id="filtroUsuarioBit" onchange="cargarBitacora(true)"></select>
            </div>
            <div class="campo">
                <label for="filtroDesdeBit">Desde</label>
                <input type="date" class="form-control form-control-sm" id="filtroDesdeBit">
            </div>
            <div class="campo">
                <label for="filtroHastaBit">Hasta</label>
                <input type="date" class="form-control form-control-sm" id="filtroHastaBit">
            </div>
            <div class="campo">
                <label for="filtroBusquedaBit">Operación o valor</label>
                <input type="text" class="form-control form-control-sm" id="filtroBusquedaBit"
                       placeholder="Número o texto" onkeypress="if(event.key==='Enter')cargarBitacora(true)">
            </div>
            <button class="btn btn-primary btn-sm" onclick="cargarBitacora(true)"><i class="fas fa-filter"></i>&nbsp; Filtrar</button>
        </div>
        <div id="avisoBitacora"></div>
        <div id="vacioBitacora"></div>
        <div class="det-scroll" id="scrollBitacora">
            <table class="det-tabla" id="tablaBitacora">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Acción</th>
                        <th>Operación</th>
                        <th>Valor anterior</th>
                        <th>Valor nuevo</th>
                        <th>Usuario</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody id="tbodyBitacora"></tbody>
            </table>
        </div>
    </details>

</div>

<div class="modal fade" id="modalClasificarBaja" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Clasificar la salida de la operación</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3" id="clasificarCabecera"></div>
                <div class="mb-3">
                    <label class="form-label" for="clasificarCausal">Causa de la salida</label>
                    <select class="form-select form-select-sm" id="clasificarCausal" name="clasificacion"
                            onchange="cambiarCausalBaja()"></select>
                    <span class="invalid-feedback d-block" id="error-clasificacion"></span>
                    <div id="avisoCausal" class="mt-2"></div>
                </div>
                <div class="mb-3" id="campoReferencia" style="display:none">
                    <label class="form-label" for="clasificarReferencia">Operación nueva</label>
                    <input type="text" class="form-control form-control-sm" id="clasificarReferencia"
                           name="referencia" maxlength="30" placeholder="Número de la operación que se abrió">
                    <span class="det-subtitulo">Referencia informativa. La política D-07 no vincula la operación nueva con la cerrada.</span>
                </div>
                <div class="mb-1">
                    <label class="form-label" for="clasificarObservacion">Observación</label>
                    <textarea class="form-control form-control-sm" id="clasificarObservacion" name="observacion"
                              rows="4" maxlength="500" oninput="contarObservacionBaja()"
                              placeholder="Hecho que produjo la salida de la operación"></textarea>
                    <span class="det-subtitulo" id="contadorObservacionBaja">0 de 500</span>
                    <span class="invalid-feedback d-block" id="error-observacion"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="confirmarClasificarBaja()">Guardar</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalForzarCierre" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Forzar el cierre del corte <span id="cerrarFechaCorte" class="det-subtitulo"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="det-subtitulo mb-1">Requisitos sin resolver que se congelan en la foto del cierre</p>
                <div class="mb-3" id="cerrarBloqueos"></div>
                <div class="mb-1">
                    <label class="form-label" for="motivoSalvedad">Motivo de la salvedad</label>
                    <textarea class="form-control form-control-sm" id="motivoSalvedad" name="motivoSalvedad"
                              rows="4" maxlength="500" oninput="contarMotivoSalvedad()"
                              placeholder="Por qué se cierra el corte con requisitos sin resolver"></textarea>
                    <span class="det-subtitulo" id="contadorMotivoSalvedad">0 de 500</span>
                    <span class="invalid-feedback d-block" id="error-motivoSalvedad"></span>
                </div>
                <div class="det-aviso mt-3 mb-0">
                    <i class="fas fa-triangle-exclamation mt-1"></i>
                    <div>El corte queda marcado CON SALVEDADES de forma permanente y los requisitos pendientes se congelan en la foto del cierre.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="confirmarForzarCierre(this)">Forzar el cierre</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalReabrirCorte" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Reabrir el corte <span id="reabrirFechaCorte" class="det-subtitulo"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-1">
                    <label class="form-label" for="motivoReapertura">Motivo de la reapertura</label>
                    <textarea class="form-control form-control-sm" id="motivoReapertura" name="motivo"
                              rows="4" maxlength="500" oninput="contarMotivoReapertura()"
                              placeholder="Por qué hay que reabrir un corte ya cerrado"></textarea>
                    <span class="det-subtitulo" id="contadorMotivoReapertura">0 de 500</span>
                    <span class="invalid-feedback d-block" id="error-motivo"></span>
                </div>
                <div class="det-aviso mt-3 mb-0">
                    <i class="fas fa-triangle-exclamation mt-1"></i>
                    <div>Revierte el acumulado fiscal que escribió este cierre y borra sus marcas de cierre; la historia queda en la bitácora.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="confirmarReabrirCorte(this)">Reabrir el corte</button>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('js/deterioro-comun.js') }}"></script>
<script src="{{ asset('js/deterioro-exportar.js') }}"></script>
<script src="{{ asset('js/deterioro-controles.js') }}"></script>
@endsection
