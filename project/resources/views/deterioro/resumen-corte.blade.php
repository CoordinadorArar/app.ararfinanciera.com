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
        <h6>Controles de cuadre</h6>
        <div id="listaCuadres"></div>
        <p class="det-subtitulo mt-3 mb-0">
            Los controles que dependen de SIESA y del cálculo fiscal entran en las fases 2 y 6.
        </p>
    </div>

</div>
@endsection
<script src="{{ asset('js/deterioro-comun.js') }}"></script>
<script src="{{ asset('js/deterioro-resumen.js') }}"></script>
