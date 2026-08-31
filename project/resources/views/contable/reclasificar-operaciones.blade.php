@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/reclasificar-operaciones.css') }}">
@section('content')
    <div class="content-contable" style="min-height: 500px">
        <H4 class="text-center">Importacion Documento Operaciones Siesa</H4>
        <meta name="csrf-token-reclasificar" content="{{ csrf_token() }}" />
        <div class="container mb-2">
            <div class="row">
                <div class="col-md-4 col-xs-12 col-lg-2 form-group">
                    <input id="operacion" class="form-control form-control-sm" placeholder="Operación..." onkeypress="return noStrangeCharacters(event)">
                </div>
                <div class="col-md-5 col-xs-12 col-lg-3">
                    <input type="text" id="fechaOperacion" class="form-control form-control-sm" placeholder="Fecha Operación..." autocomplete="false" onkeypress="return noStrangeCharacters(event)">
                </div>
            <div class="col-md-3 col-xs-12 col-lg-2 form-group">
                <button class="btn btn-primary btn-sm" id="btn_buscar" onclick="BusquedaOperacion();">Buscar</button>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-xs-12 col-sm-12 col-md-12 col-lg-7" id="div-table">
                    <table class="table table-sm table-primary table-bordered table-hover" id="table-reclasificaciones">
                        <thead>
                            <tr>
                                <th class="text-center">Tipo Documento</th>
                                <th class="text-center">Operación</th>
                                <th class="text-center">Enviar</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyOperaciones">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
<script src="{{ asset('js/reclasificar-operaciones.js') }}"></script>