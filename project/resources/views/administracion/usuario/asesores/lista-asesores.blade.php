@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/gestion-asesores.css') }}">
@section('content')
    <div class="text-center">
        <h4>Gestión de asesores</h4><hr>
        <div class="row">
            <div class="col-lg-2 col-md-4 col-sm-12 col-xs-12">
                <div class="card">
                    <div class="card-body">
                        <h4>Asesores</h4>
                        <ul class="list-group" id="lista-asesores">
                            @if($asesores)
                                @foreach($asesores as $data)
                                    <li class="list-group-item list-item-asesores" onclick="mostrarProcesosAsesor({{ $data->IdUsuario }})">{{ $data->nombreUsuario }}</li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-10 col-md-8 col-sm-12 col-xs-12">
                <div class="card text-start" id="div-table-procesos">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <h4>Procesos</h4>
                            <div class="d-flex" id="filter-buttons">
                                <button class="btn btn-sm btn-danger me-1" onclick="filterTable(0)" title="Cancelados" data-toggle="tooltip">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <button class="btn btn-sm btn-success me-1" onclick="filterTable(5)" title="Exitosos" data-toggle="tooltip">
                                    <i class="fas fa-thumbs-up"></i>
                                </button>
                                <button class="btn btn-sm btn-warning me-1" onclick="filterTable('any')" title="En proceso..." data-toggle="tooltip">
                                    <i class="fas fa-spinner"></i>
                                </button>
                            </div>
                        </div>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-sm" id="table-procesos">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Documento</th>
                                        <th>Fecha Creación</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($procesos)
                                        @foreach($procesos as $data)
                                            <tr>
                                                <td>{{ $data->NombresTercero.' '.$data->ApellidosTercero }}</td>
                                                <td>{{ $data->DocumentoTercero }}</td>
                                                <td>{{ $data->FechaCreacion }}</td>
                                                <td>
                                                    @if(in_array($data->EstadoProceso,[1,2,3,4]))
                                                        <button class="btn btn-warning btn-sm" {{--onclick="mostrarInfoProcesos({{ $data->IdProceso }})"--}}>
                                                            <i class="fas fa-spinner"></i>
                                                        </button>
                                                    @elseif($data->EstadoProceso == 0)
                                                        <button class="btn btn-danger btn-sm" {{--onclick="mostrarInfoProcesos({{ $data->IdProceso }})"--}}>
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    @elseif($data->EstadoProceso == 5)
                                                        <button class="btn btn-warning btn-sm" {{--onclick="mostrarInfoProcesos({{ $data->IdProceso }})"--}}>
                                                            <i class="fas fa-thumbs-up"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @include('administracion.usuario.asesores.info-proceso-individual')
            </div>
        </div>
    </div>
@endsection
<script src="{{ asset('js/chart.min.js') }}"></script>
<script src="{{ asset('js/lista-asesores.js') }}"></script>