@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/procesos.css') }}">
@section('content')
    <div class="content-procesos">
        <meta name="csrf-token-lista-procesos" content="{{ csrf_token() }}" />
        <div id="div-tabla-procesos" class="procesos-div">
            <h4 class="text-center">Lista de usuarios con procesos iniciados</h4><hr>
            <div class="d-flex justify-content-between mb-2">
                <div class="input-group" id="div-busqueda-documento">
                    <input type="text" class="form-control form-control-sm" placeholder="Documento..." id="busquedaProceso" onkeyup="filtrarProcesos()" title="Documento de tercero a buscar" onkeypress="return noStrangeCharacters(event)">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
                <div id="botones-filtro-procesos">
                    @if(in_array($rol[0]->IdRol,[1,2,5,6])) {{--admin, gerente, analista credito, asesor--}}
                        <button class="btn" id="btn-centrales" onclick="filtrarProcesos(2)" title="Centrales de riesgo"><i class="fas fa-search-dollar"></i></button>
                    @endif
                    @if(in_array($rol[0]->IdRol,[1,2,5,6])) {{--admin, gerente, analista crédito, asesor--}}
                        <button class="btn" id="btn-documentos" onclick="filtrarProcesos(3)" title="Documentos de soporte"><i class="fas fa-file-upload"></i></button>
                    @endif
                    @if(in_array($rol[0]->IdRol,[1,2,3])) {{--admin, gerente, comité de crédito--}}
                        <button class="btn btn-primary" id="" onclick="filtrarProcesos(4)" title="Aprobación de crédito"><i class="fas fa-thumbs-up"></i></button>
                    @endif
                    @if(in_array($rol[0]->IdRol,[1,2,6])) {{--admin, gerente, asesor--}}
                        <button class="btn" id="btn-aprobados" onclick="filtrarProcesos(5)" title="Aprobados"><i class="fas fa-check"></i></button>
                    @endif
                    @if(in_array($rol[0]->IdRol,[1,2])) {{--admin, gerente--}}
                        <button class="btn btn-danger" id="" onclick="filtrarProcesos(0)" title="Rechazados"><i class="fas fa-ban"></i></button>
                    @endif
                </div>
            </div>
            <div class="table-responsive mb-2">
                <table class="table table-sm table-striped" id="tablaGestionProcesos">
                    <thead>
                        <tr class="bg-primary">
                            <th scope="col">Documento</th>
                            <th scope="col">Nombre</th>
                            <th scope="col">Fecha Creación</th>
                            <th scope="col">Pagaduria</th>
                            <th scope="col">Fecha Cambio de Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($procesos)
                            @foreach($procesos as $data)
                                <tr>
                                    <td>{{ $data->DocumentoTercero }}</td>
                                    <td>{{ $data->NombresTercero }} {{ $data->ApellidosTercero }}</td>
                                    <td>{{ $data->FechaCreacion }}</td>
                                    <td>{{ $data->NombrePagaduria }}</td>
                                    <td>{{ $data->updated_at }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <a class="btn btn-warning btn-sm dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                                <i class="fas fa-chevron-circle-down"></i>
                                            </a>
                                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                                                @foreach($rol as $dataRol)
                                                    @if(in_array($dataRol->IdRol,[1,2,5]) && $data->EstadoProceso == 2)
                                                        <li><a class="dropdown-item" href="#" onclick="mostrarVistas({{ $data->IdProceso }},'centrales')"><i class="fas fa-search-dollar"></i> Centrales de riesgo</a></li>
                                                    @endif
                                                    @if(in_array($dataRol->IdRol,[1,2,6]) && $data->EstadoProceso >= 3)
                                                        <li><a class="dropdown-item" href="#" onclick="mostrarVistas({{ $data->IdProceso }},'docs soporte')"><i class="fas fa-file-upload"></i> Documentos de soporte</a></li>
                                                    @endif
                                                    @if(in_array($dataRol->IdRol,[1,2,5]) && $data->EstadoProceso >= 3)
                                                        <li><a class="dropdown-item" href="#" onclick="mostrarVistas({{ $data->IdProceso }},'docs aprobar')"><i class="fas fa-tasks"></i> Aprobar documentos</a></li>
                                                    @endif
                                                    @if(in_array($dataRol->IdRol,[1,2,3]) && in_array($data->EstadoProceso,[4,5]))
                                                        <li><a class="dropdown-item" href="#" onclick="mostrarVistas({{ $data->IdProceso }},'credito aprobar')"><i class="fas fa-thumbs-up"></i> Aprobar crédito</a></li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        @include('procesos.consulta-centrales')
        @include('procesos.documentos-soporte')
        @include('procesos.check-documentos-soporte')
        @include('procesos.aprobar-credito')
    </div>
@endsection
<script src="{{ asset('js/jspdf.umd.min.js') }}"></script>
<script src="{{ asset('js/procesos.js') }}"></script>