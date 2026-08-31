@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/gestion-sitio.css') }}">
@section('content')
    <div class="text-center">
        <h4>Administración del aplicativo</h4><hr>
        <div class="row">
            <meta name="csrf-token-admin-management" content="{{ csrf_token() }}" />
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <input type="hidden" id="rol-usuario-validar" value="{{ $rol[0]->IdRol }}" onkeypress="return noStrangeCharacters(event)">
                <div class="card" id="card-roles">
                    <div class="card-body">
                        <h5>Roles</h5><hr>
                        <form action="" method="post" id="form-roles">
                            <div class="">
                                {{-- <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12"> --}}
                                <div class="row">
                                    <div class="col-lg-10 col-md-10 col-sm-10 col-xs-10">
                                        @if($roles)
                                        <select class="form-select form-select-sm" id="listaRoles" onchange="gestionRol('editar',this.value)">
                                            <option value="0">Roles registrados...</option>
                                                @foreach($roles as $data)
                                                    <option value="{{ $data->IdRol }}">{{ $data->NombreRol }}</option>
                                                @endforeach
                                        </select>
                                        @endif
                                    </div>
                                    <div class="col-lg-1 col-md-1 col-sm-1 col-xs-">
                                        <button type="button" class="btn btn-primary" title="Nuevo Rol" onclick="gestionRol('nuevo','')">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                {{-- </div> --}}
                                {{-- <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 mt-2"> --}}
                                    <div class="form-group my-2" id="inputNuevoRol">
                                        <label for="">Nombre del Rol</label>
                                        <input type="text" class="form-control form-control-sm" id="nombreRol" name="nombreRol" placeholder="Ingresa nombre de nuevo rol..." onkeypress="return noStrangeCharacters(event)">
                                    </div>
                                {{-- </div> --}}
                                {{-- <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 mt-2"> --}}
                                    <h5 class="mt-2">Acceso a items del menú</h5>
                                    <div id="submenusRol"></div>
                                {{-- </div> --}}
                                <button type="submit" class="btn btn-primary mt-2" id="btnGuardarRol" onclick="guardarRol(event)">Guardar</button>
                                <button type="button" class="btn btn-danger mt-2" id="cancelRol" onclick="cancelarGuardado('rol')">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="card">
                    <div class="card-body">
                        <h5>Valores variables</h5><hr>
                        <div class="row">
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                <table class="table table-sm table-striped table-bordered" id="listaVariables">
                                    @if($valoresVariables)
                                        @foreach($valoresVariables as $data)
                                        <tr>
                                            <th>{{ $data->NombreValorVariable }}</th>
                                            <td>{{ $data->ValorVariable }}</td>
                                            <td><button class="btn btn-warning btn-sm" onclick="editarValorVariable({{ $data->IdValorVariable }})"><i class="fas fa-pencil"></i></button></td>
                                        </tr>
                                        @endforeach
                                    @endif
                                </table>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                <h5>Nueva variable</h5>
                                <form action="" method="post" id="form-variables">
                                    <div class="form-group my-2">
                                        <label for="">Nombre variable</label>
                                        <input type="hidden" id="idVariable" value="" onkeypress="return noStrangeCharacters(event)">
                                        <input type="text" class="form-control form-control-sm" id="nombreVariable" name="nombreVariable" placeholder="Ingresa nombre de nueva variable..." onkeypress="return noStrangeCharacters(event)">
                                    </div>
                                    <span class="invalid-feedback" role="alert" id="error-nombreVariable">
                                    </span>
                                    <div class="form-group my-2">
                                        <label for="">Valor variable</label>
                                        <input type="text" class="form-control form-control-sm" id="valorVariable" name="valorVariable" placeholder="Ingresa valor de variable..." onkeypress="return noStrangeCharacters(event)">
                                    </div>
                                    <span class="invalid-feedback" role="alert" id="error-valorVariable">
                                    </span>
                                    <button type="submit" class="btn btn-primary" id="btnGuardarVariable" onclick="guardarVariable(event)">Guardar</button>
                                    <button type="button" class="btn btn-danger" id="cancelVariable" onclick="cancelarGuardado('variable')">Cancelar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
<script src="{{ asset('js/gestion-sitio.js') }}"></script>