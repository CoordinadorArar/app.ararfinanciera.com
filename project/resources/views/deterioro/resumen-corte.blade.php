@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/deterioro.css') }}">
@section('content')
<meta name="csrf-token-deterioro" content="{{ csrf_token() }}" />
<div class="det-contenedor" id="divResumen">

    <div class="det-encabezado">
        <div class="det-titular">
            <nav class="det-migas">
                <a href="{{ url('/deterioro-cortes') }}">Cortes</a>
                <span>&rsaquo;</span>
                <span class="actual">Resumen <span id="migaFecha"></span></span>
            </nav>
            <h4 class="det-titulo">Resumen del corte <span id="tituloFecha" class="text-muted"></span></h4>
            <p class="det-subtitulo">Matriz de producto por rango de mora, con capital, interés, base y deterioro</p>
        </div>
        <div class="det-navegacion">
            <a class="btn btn-secondary btn-sm" href="{{ url('/deterioro-cortes') }}"><i class="fas fa-arrow-left"></i>&nbsp; Cortes</a>
            <a class="btn btn-light btn-sm" id="btnEvolucion" href="#"><i class="fas fa-chart-line"></i>&nbsp; Evolución</a>
            <a class="btn btn-light btn-sm" id="btnComparativo" href="#"><i class="fas fa-scale-balanced"></i>&nbsp; Contable contra fiscal</a>
            <a class="btn btn-light btn-sm" id="btnSuspensiones" href="#"><i class="fas fa-circle-pause"></i>&nbsp; Intereses suspendidos</a>
            <a class="btn btn-light btn-sm" id="btnConciliacion" href="#"><i class="fas fa-scale-unbalanced"></i>&nbsp; Conciliación</a>
            <a class="btn btn-primary btn-sm" id="btnDetalle" href="#"><i class="fas fa-list"></i>&nbsp; Ver detalle</a>
            <a class="btn btn-success btn-sm" id="btnControles" href="#"><i class="fas fa-lock"></i>&nbsp; Controles y cierre</a>
        </div>
        <div class="det-acciones">
            <a class="btn btn-sm det-btn-ayuda" href="{{ url('/deterioro-ayuda') }}?volver={{ urlencode(request()->getRequestUri()) }}"
               data-bs-toggle="tooltip" title="Guía de uso del módulo" aria-label="Guía de uso del módulo"><i class="fas fa-question"></i><span class="rot">&nbsp; Guía de uso</span></a>
            <span id="accionesExportar"></span>
        </div>
    </div>

    <div class="det-tarjetas" id="tarjetas"></div>
    <div id="avisoProrroga"></div>

    <div class="det-panel">
        <div class="det-panel-cab">
            <h6>Vencido por rango de mora</h6>
            <div class="det-leyenda mb-0" id="leyendaRangos"><span><i class="corriente"></i>Capital vencido</span><span><i class="siguiente"></i>Interés vencido</span></div>
        </div>
        <div class="det-grafico" id="contGraficoRangos">
            <canvas id="graficoRangos" role="img" aria-label="Capital e interés vencido por rango de mora"></canvas>
        </div>
    </div>

    <div class="det-panel">
        <h6>Producto por rango</h6>
        <div class="det-scroll">
            <table class="det-tabla" id="tablaResumen">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Rango</th>
                        <th class="num">%</th>
                        <th class="num">Operaciones</th>
                        <th class="num">Capital corriente</th>
                        <th class="num">Capital vencido</th>
                        <th class="num">Interés corriente</th>
                        <th class="num">Interés vencido</th>
                        <th class="num" data-bs-toggle="tooltip" title="Saldo de prórroga vencido que reporta SIESA. Se trata como interés y entra a la base (RN-03). No es la prórroga del control C-1">Interés de prórroga</th>
                        <th class="num">Base de deterioro</th>
                        <th class="num">Deterioro contable</th>
                    </tr>
                </thead>
                <tbody id="tbodyResumen"></tbody>
                <tfoot id="tfootResumen"></tfoot>
            </table>
        </div>
    </div>

    <div class="det-panel">
        <h6>Deducción fiscal del año gravable <span id="badgeFiscal"></span></h6>
        <div id="avisoFiscal"></div>
        <div class="det-tarjetas compacta" id="tarjetasFiscales"></div>
        <div class="det-scroll">
            <table class="det-tabla" id="tablaFiscal">
                <thead>
                    <tr class="det-grupo">
                        <th class="vacio"></th>
                        <th class="vacio"></th>
                        <th class="vacio"></th>
                        <th class="vacio"></th>
                        <th class="vacio"></th>
                        <th colspan="2">Cálculo D-04</th>
                        <th colspan="2">Tope RN-09</th>
                    </tr>
                    <tr>
                        <th>Producto</th>
                        <th>Rango</th>
                        <th class="num" data-bs-toggle="tooltip" title="Tarifa fiscal efectiva sobre la base">%</th>
                        <th class="num">Operaciones</th>
                        <th class="num" data-bs-toggle="tooltip" title="Capital vencido más interés vencido más interés de prórroga de SIESA (RN-03)">Base</th>
                        <th class="num" data-bs-toggle="tooltip" title="Base por la tarifa anual del método individual (RN-07)">Individual 33 %</th>
                        <th class="num" data-bs-toggle="tooltip" title="Deducciones tomadas en años gravables anteriores">Acum. anterior</th>
                        <th class="num" data-bs-toggle="tooltip" title="Tope del acumulado deducible: el menor entre el saldo de SIESA y la base">Saldo topado</th>
                        <th class="num" data-bs-toggle="tooltip" title="Individual del año limitado por el tope disponible">Deducción del año</th>
                    </tr>
                </thead>
                <tbody id="tbodyFiscal"></tbody>
                <tfoot id="tfootFiscal"></tfoot>
            </table>
        </div>
    </div>

    <details class="det-panel det-referencia">
        <summary>
            <i class="fas fa-chevron-right det-caret"></i>
            Método general · <span class="det-badge det-inactivo">Desactivado</span>
            <span class="det-subtitulo">Cálculo paralelo, solo para análisis comparativo. No produce deducción.</span>
        </summary>
        <div class="det-scroll">
            <table class="det-tabla" id="tablaGeneral">
                <thead>
                    <tr>
                        <th>Rango</th>
                        <th class="num">%</th>
                        <th class="num">Base</th>
                        <th class="num">Fiscal general</th>
                    </tr>
                </thead>
                <tbody id="tbodyGeneral"></tbody>
            </table>
        </div>
        <p class="det-subtitulo mt-3 mb-0">
            Los métodos individual y general son excluyentes (RN-08). La política adoptada es el individual.
        </p>
    </details>

    <div class="det-panel">
        <h6>Controles de cuadre</h6>
        <div id="listaCuadres"></div>
        <p class="det-subtitulo mt-3 mb-0">
            Los controles que dependen de SIESA entran en la fase 6.
        </p>
    </div>

</div>
@endsection
<script src="{{ asset('js/deterioro-comun.js') }}"></script>
<script src="{{ asset('js/deterioro-exportar.js') }}"></script>
<script src="{{ asset('js/chart.min.js') }}"></script>
<script src="{{ asset('js/deterioro-resumen.js') }}"></script>
