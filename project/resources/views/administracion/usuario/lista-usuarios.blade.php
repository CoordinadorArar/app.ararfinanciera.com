@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/lista-usuarios.css') }}">
@section('content')
    <div class="text-center">
        <h4>Administración de usuarios</h4><hr>
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="card">
                    <div class="card-body">
                        <h4>Lista de usuarios</h4>
                        <ul class="list-group" id="lista-usuarios">
                            @if($users)
                                @foreach($users as $data)
                                    <li class="list-group-item text-start user-list" onclick="mostrarInfo({{ $data->IdUsuario }})">
                                        {{ $data->nombreUsuario }} | 
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                        <a class="btn btn-primary mt-2" id="btnAgregarUsuario" href="{{ route('register') }}"><i class="fas fa-user-plus"></i> Agregar</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="card" id="info-user">
                    <div class="card-body">
                        <h4 class="display-6 text-center" id="title-info-user">Selecciona un usuario</h4>
                        <div id="div-form-user">
                            <img src="" alt=""><hr>
                            <h4 id="name-user"></h4>
                            <form action="{{ route('editar-usuarios') }}" id="form-user-update" method="post">
                                <meta name="csrf-token-form-user-update" content="{{ csrf_token() }}" />
                                <div class="row">
                                    <div class="line-info">
                                        <div class="form-gorup">
                                            <input type="hidden" id="id-user-update" name="id-user-update" onkeypress="return noStrangeCharacters(event)">
                                            <input type="text" class="form-control" name="nombreUsuario" id="nombreUsuario" placeholder="Nombre del usuario" onkeypress="return noStrangeCharacters(event)">
                                        </div>
                                    </div><br>
                                    <div class="line-info">
                                        <input type="text" class="form-control" name="documentoUsuario" id="documentoUsuario" placeholder="Documento del usuario" onkeypress="return soloNumeros(event)">
                                    </div>
                                    <div class="line-info">
                                        <input type="text" class="form-control" name="email" id="email" placeholder="Correo electrónico" onkeypress="return noStrangeCharacters(event)">
                                    </div>
                                    <div class="line-info">
                                        <select name="rol-user" id="rol-user" class="form-select">
                                            <option value="">Elija un rol...</option>
                                        </select>
                                    </div>
                                    <div class="line-info">
                                        <button type="button" class="btn btn-success" id="btn-enable-user" onclick="cambiarEstado(1)" title="Activar usuario">
                                            <i class="fa fa-user-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger" id="btn-disable-user" onclick="cambiarEstado(0)" title="Inactivar usuario">
                                            <i class="fa fa-user-slash"></i>
                                        </button>
                                    </div>
                                    <div class="line-info" id="div-save-button">
                                        <div class="d-grid">
                                            <hr>
                                            <button type="submit" class="btn btn-primary" onclick="actualizarInfo(event)">Actualizar</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
<script src="{{ asset('js/lista-usuarios.js') }}"></script>