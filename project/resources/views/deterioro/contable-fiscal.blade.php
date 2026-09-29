@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="{{ asset('css/deterioro.css') }}">
<meta name="csrf-token-deterioro" content="{{ csrf_token() }}" />
<div class="det-contenedor" id="divComparativo">

    <div class="det-encabezado">
        <div class="det-titular">
            <nav class="det-migas">
                <a href="{{ url('/deterioro-cortes') }}">Cortes</a>
                <span>&rsaquo;</span>
                <a id="migaResumen" href="#">Resumen <span id="migaFecha"></span></a>
                <span>&rsaquo;</span>
                <span class="actual">Contable contra fiscal</span>
            </nav>
            <h4 class="det-titulo">Contable contra fiscal <span id="badgeAlcance"></span></h4>
            <p class="det-subtitulo">Diferencia temporaria entre el deterioro contable y el deterioro fiscal acumulado, y el impuesto diferido que se deriva de ella</p>
        </div>
        <div class="det-navegacion">
            <a class="btn btn-secondary btn-sm" href="{{ url('/deterioro-cortes') }}"><i class="fas fa-arrow-left"></i>&nbsp; Cortes</a>
            <a class="btn btn-light btn-sm" id="btnResumenCF" href="#"><i class="fas fa-table"></i>&nbsp; Resumen</a>
            <a class="btn btn-light btn-sm" id="btnDetalleCF" href="#"><i class="fas fa-list"></i>&nbsp; Detalle</a>
            <a class="btn btn-light btn-sm" id="btnEvolucionCF" href="#"><i class="fas fa-chart-line"></i>&nbsp; Evolución</a>
            <a class="btn btn-light btn-sm" id="btnSuspensionesCF" href="#"><i class="fas fa-circle-pause"></i>&nbsp; Intereses suspendidos</a>
            <a class="btn btn-light btn-sm" id="btnConciliacionCF" href="#"><i class="fas fa-scale-unbalanced"></i>&nbsp; Conciliación</a>
            <a class="btn btn-success btn-sm" id="btnControlesCF" href="#"><i class="fas fa-lock"></i>&nbsp; Controles y cierre</a>
        </div>
        <div class="det-acciones">
            <a class="btn btn-sm det-btn-ayuda" href="{{ url('/deterioro-ayuda') }}?volver={{ urlencode(request()->getRequestUri()) }}"
               data-bs-toggle="tooltip" title="Guía de uso del módulo" aria-label="Guía de uso del módulo"><i class="fas fa-question"></i><span class="rot">&nbsp; Guía de uso</span></a>
            <span id="accionesExportar"></span>
        </div>
    </div>

    <div id="avisoDiciembre"></div>

    <div class="det-puente" id="franjaEcuacion"></div>

    <div class="det-panel">
        <div class="det-panel-cab">
            <div>
                <h6>Puente contable&ndash;fiscal</h6>
                <p class="det-subtitulo">Del deterioro contable al deterioro fiscal acumulado, y de la diferencia temporaria al impuesto diferido</p>
            </div>
            <div class="btn-group btn-group-sm det-vistas" role="group">
                <input type="radio" class="btn-check" name="vistaPuente" id="vistaRango" checked onchange="pintarPuente()">
                <label class="btn btn-light" for="vistaRango">Por rango</label>
                <input type="radio" class="btn-check" name="vistaPuente" id="vistaProducto" onchange="pintarPuente()">
                <label class="btn btn-light" for="vistaProducto">Por producto</label>
            </div>
        </div>
        <div class="det-scroll det-sombra">
            <table class="det-tabla" id="tablaPuente">
                <thead>
                    <tr class="det-grupo">
                        <th class="vacio det-fija"></th>
                        <th class="vacio"></th>
                        <th class="vacio"></th>
                        <th colspan="2">Contable</th>
                        <th colspan="3">Fiscal</th>
                        <th colspan="2">Diferencia temporaria</th>
                        <th colspan="2" data-bs-toggle="tooltip" title="Art. 240 ET">Impuesto diferido (tarifa de renta <span class="det-tarifa-renta">&mdash;</span>)</th>
                    </tr>
                    <tr>
                        <th class="det-fija" id="thPrimera">Rango</th>
                        <th class="num">Operaciones</th>
                        <th class="num" data-bs-toggle="tooltip" title="Capital vencido más interés vencido más interés de prórroga de SIESA (RN-03)">Base</th>
                        <th class="num" data-bs-toggle="tooltip" title="Deterioro contable sobre la base">%</th>
                        <th class="num">Deterioro contable</th>
                        <th class="num" data-bs-toggle="tooltip" title="Deducciones tomadas en años gravables anteriores">Acum. anterior</th>
                        <th class="num" data-bs-toggle="tooltip" title="Provisión individual deducible · 33 % anual. Art. 145 ET · RN-07">Deducción del año</th>
                        <th class="num" data-bs-toggle="tooltip" title="Acumulado anterior más deducción del año">Fiscal acumulado</th>
                        <th class="num" data-bs-toggle="tooltip" title="Deterioro contable mayor que el fiscal acumulado">Deducible</th>
                        <th class="num" id="thImponible" data-bs-toggle="tooltip" title="Deterioro fiscal acumulado mayor que el contable">Imponible</th>
                        <th class="num" data-bs-toggle="tooltip" title="Diferencia temporaria deducible por la tarifa de renta">Activo</th>
                        <th class="num" id="thPasivo" data-bs-toggle="tooltip" title="Diferencia temporaria imponible por la tarifa de renta">Pasivo</th>
                    </tr>
                </thead>
                <tbody id="tbodyPuente"></tbody>
                <tfoot id="tfootPuente"></tfoot>
            </table>
        </div>
        <p class="det-subtitulo mt-3 mb-0" id="notaImponible"></p>
        <p class="det-subtitulo mt-2 mb-0">
            El 33 % es la provisión anual deducible (art. 145 ET) que forma el fiscal acumulado.
            El <span class="det-tarifa-renta">&mdash;</span> es la tarifa de renta (art. 240 ET) que se aplica sobre la diferencia temporaria.
            No son acumulables entre sí.
        </p>
    </div>

    <div class="det-fila">
        <div class="det-panel">
            <h6>Movimiento del período</h6>
            <p class="det-subtitulo">Las cifras del puente en este corte y en el corte anterior, con su variación</p>
            <div class="det-scroll">
                <table class="det-tabla" id="tablaMovimiento">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th class="num" id="thCorteAnterior">Corte anterior</th>
                            <th class="num" id="thCorteActual">Este corte</th>
                            <th class="num">Variación</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyMovimiento"></tbody>
                </table>
            </div>
            <p class="det-subtitulo mt-3 mb-0" id="notaMovimiento"></p>
        </div>

        <div class="det-panel">
            <h6>Reversión proyectada</h6>
            <p class="det-subtitulo">Año gravable en que cada operación termina de deducir el 100 % fiscal</p>
            <div id="reversionGrafico"></div>
            <div class="det-scroll" id="reversionTabla"></div>
        </div>
    </div>

    <div class="det-panel">
        <h6>Evolución de la diferencia temporaria</h6>
        <div id="evolucionCabecera"></div>
        <div id="evolucionGrafico"></div>
        <div class="det-scroll">
            <table class="det-tabla" id="tablaEvolucion">
                <thead>
                    <tr>
                        <th>Corte</th>
                        <th class="num">Deterioro contable</th>
                        <th class="num">Fiscal acumulado</th>
                        <th class="num">Diferencia temporaria</th>
                        <th class="num">Impuesto diferido</th>
                        <th class="num">Variación</th>
                    </tr>
                </thead>
                <tbody id="tbodyEvolucion"></tbody>
            </table>
        </div>
    </div>

    <div class="det-panel">
        <h6>Controles de cuadre</h6>
        <div id="listaCuadresCF"></div>
    </div>

</div>
<script src="{{ asset('js/deterioro-comun.js') }}"></script>
<script src="{{ asset('js/deterioro-exportar.js') }}"></script>
<script src="{{ asset('js/deterioro-comparativo.js') }}"></script>
@endsection
