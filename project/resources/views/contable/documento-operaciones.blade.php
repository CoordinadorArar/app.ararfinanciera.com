@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/documento-operaciones.css') }}">
@section('content')
    <div class="container" style="min-height: 500px" id="divDocuementoOperacines">
        <h4 class="text-center">Importación Documento Operaciones Siesa</h4>
        <meta name="csrf-token-documento-operaciones" content="{{ csrf_token() }}" />
        <div class="container mb-2">
            <div class="row">
                <div class="col-md-4 col-xs-12 col-lg-2">
                    <input id="fechaInicial" type="text" class="form-control form-control-sm" placeholder="Fecha Inicial..." autocomplete="off" onkeypress="return noStrangeCharacters(event)">
                </div>
                <div class="col-md-4 col-xs-12 col-lg-2">
                    <input id="fechaFinal" type="text" class="form-control form-control-sm" placeholder="Fecha Final..." autocomplete="off" onkeypress="return noStrangeCharacters(event)">
                </div>
                <div class="col-md-4 col-xs-12 col-lg-2">
                    <button class="form-control btn btn-primary btn-sm" id="btnBuscar" onclick="BusquedaFecha();">Buscar</button>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-xs-12 col-sm-12 col-md-12 col-lg-6" id="div-table">
                    <table class="table table-bordered table-hover" id="table-operaciones">
                        <thead>
                            <tr>
                                <th class="text-center">Tipo Documento</th>
                                <th class="text-center">Operacion</th>
                                <th class="text-center">Seleccionar</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyOperaciones">
                            @php $idOperaciones = '' @endphp
                            @if($datosOperaciones != 0)
                                @foreach($datosOperaciones as $data)
                                    @php $idOperaciones .= $data->IdOperacion.','; @endphp
                                    <tr>
                                        <td class="text-center">{{ $data->tipdocumento }}</td>
                                        <td class="text-center">{{ $data->IdOperacion }}</td>
                                        <td class="text-center">
                                            <button data-toggle="tooltip" title="Enviar Operación {{ $data->IdOperacion; }}" class="btn btn-primary btn-envio-td" onclick="EnviarOperacion('{{ $data->IdOperacion; }}')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach                                
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="container" id="containerBtnOperacion">
            @if($idOperaciones != "")
                @php $idOperaciones = substr($idOperaciones,0,-1) @endphp
            @endif
            <div class="row">
                <div class="col-md-6 col-sm-12 col-xs-12 col-lg-6 text-center mt-2 p-2">
                    <button class="btn btn-primary" id="btn_enviar" data-toggle="tooltip" title="Enviar Todas Las Facturas"
                    onclick='EnviarTodos("{{ $idOperaciones }}")' >
                        <i class="far fa-paper-plane"></i>&nbsp;&nbsp;Enviar Todos
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
<script src="{{ asset('js/documento-operaciones.js?v=2.0.1') }}"></script>