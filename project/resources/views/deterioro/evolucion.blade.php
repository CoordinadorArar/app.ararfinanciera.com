@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="{{ asset('css/deterioro.css') }}">
<meta name="csrf-token-deterioro" content="{{ csrf_token() }}" />
<div class="det-contenedor" id="divEvolucion">

    <div class="det-encabezado">
        <div class="det-titular">
            <nav class="det-migas">
                <a href="{{ url('/deterioro-cortes') }}">Cortes</a>
                <span>&rsaquo;</span>
                <a id="migaResumen" href="#">Resumen <span id="migaFecha"></span></a>
                <span>&rsaquo;</span>
                <span class="actual">Evolución</span>
            </nav>
            <h4 class="det-titulo">Evolución <span id="badgeEstado"></span></h4>
            <p class="det-subtitulo">Gasto contable del período, descomposición del movimiento del mes y serie histórica del deterioro</p>
        </div>
        <div>
            <a class="btn btn-secondary btn-sm" href="{{ url('/deterioro-cortes') }}"><i class="fas fa-arrow-left"></i>&nbsp; Cortes</a>
            <a class="btn btn-light btn-sm" id="btnResumenEv" href="#"><i class="fas fa-table"></i>&nbsp; Resumen</a>
            <a class="btn btn-light btn-sm" id="btnDetalleEv" href="#"><i class="fas fa-list"></i>&nbsp; Detalle</a>
            <a class="btn btn-light btn-sm" id="btnComparativoEv" href="#"><i class="fas fa-scale-balanced"></i>&nbsp; Contable contra fiscal</a>
            <a class="btn btn-light btn-sm" id="btnSuspensionesEv" href="#"><i class="fas fa-circle-pause"></i>&nbsp; Intereses suspendidos</a>
            <a class="btn btn-light btn-sm" id="btnConciliacionEv" href="#"><i class="fas fa-scale-unbalanced"></i>&nbsp; Conciliación</a>
            <a class="btn btn-success btn-sm" id="btnControlesEv" href="#"><i class="fas fa-lock"></i>&nbsp; Controles y cierre</a>
        </div>
        <div class="det-acciones">
            <a class="btn btn-sm det-btn-ayuda" href="{{ url('/deterioro-ayuda') }}?volver={{ urlencode(request()->getRequestUri()) }}"
               data-bs-toggle="tooltip" title="Guía de uso del módulo" aria-label="Guía de uso del módulo"><i class="fas fa-question"></i><span class="rot">&nbsp; Guía de uso</span></a>
            <span id="accionesExportar"></span>
        </div>
    </div>

    <div class="det-puente tres" id="franjaGasto"></div>
    <div id="sinAnterior"></div>

    <div class="det-panel" id="panelCascada">
        <div class="det-panel-cab">
            <div>
                <h6>Descomposición del movimiento del mes</h6>
                <p class="det-subtitulo">Del deterioro del corte anterior al de este corte, por altas, variación de las que continúan y bajas</p>
            </div>
        </div>
        <div class="det-leyenda" id="leyendaCascada"></div>
        <div class="det-cascada" id="cascada"></div>
        <div id="cierreCascada"></div>
        <div class="det-scroll mt-3">
            <table class="det-tabla" id="tablaDescomposicion">
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th class="num">Operaciones</th>
                        <th class="num">Importe</th>
                    </tr>
                </thead>
                <tbody id="tbodyDescomposicion"></tbody>
            </table>
        </div>
    </div>

    <div class="det-panel" id="panelConciliacion">
        <div class="det-panel-cab">
            <div>
                <h6>Conciliación con el libro</h6>
                <p class="det-subtitulo">Deterioro del mes anterior, deterioro de este corte y gasto del período, comparados con el libro</p>
            </div>
        </div>
        <div id="conciliacion"></div>
    </div>

    <div class="det-panel" id="panelComparativo">
        <div class="det-panel-cab">
            <div>
                <h6>Gasto del mes</h6>
                <p class="det-subtitulo">Variación del deterioro contable contra el corte anterior</p>
            </div>
            <div class="btn-group btn-group-sm det-vistas" role="group" id="vistasGasto">
                <input type="radio" class="btn-check" name="vistaGasto" id="gastoRango" checked onchange="pintarComparativo()">
                <label class="btn btn-light" for="gastoRango">Por rango</label>
                <input type="radio" class="btn-check" name="vistaGasto" id="gastoProducto" onchange="pintarComparativo()">
                <label class="btn btn-light" for="gastoProducto">Por producto</label>
            </div>
        </div>
        <div class="det-leyenda" id="leyendaComparativo"></div>
        <div id="vacioComparativo"></div>
        <div class="det-scroll" id="scrollComparativo">
            <table class="det-tabla" id="tablaComparativo">
                <thead>
                    <tr>
                        <th class="det-fija" id="thDimension">Rango</th>
                        <th class="num">Operaciones</th>
                        <th class="num" id="thDeterioroAnt">Deterioro anterior</th>
                        <th class="num" id="thDeterioroAct">Deterioro de este corte</th>
                        <th class="num">Gasto del período</th>
                        <th>Efecto</th>
                    </tr>
                </thead>
                <tbody id="tbodyComparativo"></tbody>
                <tfoot id="tfootComparativo"></tfoot>
            </table>
        </div>
        <p class="det-subtitulo mt-3 mb-0" id="notaComparativo"></p>
    </div>

    <div id="avisoFiscalEvolucion"></div>

    <div class="det-panel" id="panelSerie">
        <div class="det-panel-cab">
            <div>
                <h6>Serie histórica</h6>
                <p class="det-subtitulo">Tamaño de la cartera, deterioro contable, gasto del período y bloque fiscal de cada corte</p>
            </div>
        </div>
        <div id="serieGrafico"></div>
        <div class="det-scroll det-sombra">
            <table class="det-tabla" id="tablaSerie">
                <thead>
                    <tr class="det-grupo">
                        <th class="vacio det-fija"></th>
                        <th colspan="2">Cartera</th>
                        <th colspan="2">Contable</th>
                        <th colspan="3">Fiscal</th>
                    </tr>
                    <tr>
                        <th class="det-fija">Corte</th>
                        <th class="num">Operaciones</th>
                        <th class="num" data-bs-toggle="tooltip" title="Capital vencido más interés vencido más interés de prórroga de SIESA (RN-03)">Base</th>
                        <th class="num">Deterioro contable</th>
                        <th class="num" data-bs-toggle="tooltip" title="Deterioro del corte menos deterioro del corte anterior">Gasto del período</th>
                        <th class="num">Fiscal acumulado</th>
                        <th class="num">Diferencia temporaria</th>
                        <th class="num">Impuesto diferido</th>
                    </tr>
                </thead>
                <tbody id="tbodySerie"></tbody>
            </table>
        </div>
    </div>

    <div class="det-panel" id="panelBajas">
        <div class="det-panel-cab">
            <div>
                <h6>Bajas del período</h6>
                <p class="det-subtitulo" id="subtituloBajas">Operaciones presentes en el corte anterior que ya no están en este corte</p>
            </div>
            <div class="d-flex gap-4" id="cifrasBajas"></div>
        </div>
        <div id="contenidoBajas">
            <div class="det-scroll">
                <table class="det-tabla" id="tablaBajas">
                    <thead>
                        <tr>
                            <th>Operación</th>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th>Rango</th>
                            <th class="num">Días de mora</th>
                            <th class="num">Base anterior</th>
                            <th class="num">Deterioro que liberó</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyBajas"></tbody>
                </table>
            </div>
            <div id="cuadreBajas"></div>
            <p class="det-subtitulo mt-2 mb-0">
                El módulo aún no distingue el motivo de la baja (pago total, castigo, venta de cartera, refinanciación).
                Estas operaciones se listan tal como salieron del corte.
            </p>
        </div>
        <div id="vacioBajas"></div>
    </div>

    <div class="det-panel">
        <h6>Controles de cuadre</h6>
        <div id="listaCuadresEv"></div>
    </div>

</div>
<script src="{{ asset('js/deterioro-comun.js') }}"></script>
<script src="{{ asset('js/deterioro-exportar.js') }}"></script>
<script src="{{ asset('js/deterioro-evolucion.js') }}"></script>
@endsection
