@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/deterioro.css') }}">
@section('content')
<meta name="csrf-token-deterioro" content="{{ csrf_token() }}" />
<div class="det-contenedor" id="divResumen">

    <div class="det-encabezado">
        <div>
            <h4 class="det-titulo">Resumen del corte <span id="tituloFecha" class="text-muted"></span></h4>
            <p class="det-subtitulo">Matriz de producto por rango de mora, con capital, interés, base y deterioro</p>
        </div>
        <div>
            <a class="btn btn-light btn-sm" href="{{ url('/deterioro-cortes') }}"><i class="fas fa-arrow-left"></i>&nbsp; Cortes</a>
            <a class="btn btn-light btn-sm" id="btnComparativo" href="#"><i class="fas fa-scale-balanced"></i>&nbsp; Contable vs. fiscal</a>
            <a class="btn btn-primary btn-sm" id="btnDetalle" href="#"><i class="fas fa-list"></i>&nbsp; Ver detalle</a>
        </div>
    </div>

    <div class="det-tarjetas" id="tarjetas"></div>

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
                        <th class="num" title="Tarifa fiscal efectiva sobre la base">%</th>
                        <th class="num">Operaciones</th>
                        <th class="num" title="Capital vencido más interés vencido (RN-03)">Base</th>
                        <th class="num" title="Base por la tarifa anual del método individual (RN-07)">Individual 33 %</th>
                        <th class="num" title="Deducciones tomadas en años gravables anteriores">Acum. anterior</th>
                        <th class="num" title="Tope del acumulado deducible: el menor entre el saldo de SIESA y la base">Saldo topado</th>
                        <th class="num" title="Individual del año limitado por el tope disponible">Deducción del año</th>
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
<script src="{{ asset('js/deterioro-resumen.js') }}"></script>
