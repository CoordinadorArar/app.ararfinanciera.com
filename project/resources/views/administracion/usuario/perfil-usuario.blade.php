@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/perfil-usuario.css') }}">
@section('content')
    @if($user)
        @foreach($user as $data)
            <div class="text-center">
                <h4>Perfil de usuario</h4>
                <div class="row">
                    <div class="col-lg-6 col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h4>Datos registrados</h4><hr>
                                <div class="row">
                                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                        <form action="{{ route('editar-datos-usuario') }}" method="post" id="form-data-user">
                                            <meta name="csrf-token-form-data" content="{{ csrf_token() }}" />
                                            <div class="form-group text-start">
                                                <label for="nombre">Nombre</label>
                                                <input type="text" id="nombreUsuario" name="nombreUsuario" class="form-control input-data" value="{{ $data->nombreUsuario }}" placeholder="Nombre" disabled onkeypress="return noStrangeCharacters(event)">
                                                <span class="invalid-feedback" role="alert" id="error-nombreUsuario">
                                            </div>
                                            <div class="form-group text-start">
                                                <label for="email">Correo electrónico</label>
                                                <input type="text" id="email" name="email" class="form-control input-data" value="{{ $data->email }}" placeholder="Email" disabled onkeypress="return noStrangeCharacters(event)">
                                                <span class="invalid-feedback" role="alert" id="error-email">
                                            </div>
                                            <div class="form-group text-start">
                                                <label for="documento">Documento</label>
                                                <input type="text" id="documento" name="documento" class="form-control input-data" value="{{ $data->documentoUsuario }}" placeholder="Doocumento" disabled onkeypress="return soloNumeros(event)">
                                                <span class="invalid-feedback" role="alert" id="error-documento">
                                            </div><br>
                                            <div class="row">
                                                <div class="col">
                                                    <button class="btn btn-primary mr-1" id="btn-save" onclick="guardarDatos(event)">Guardar</button>
                                                    <button type="button" class="btn btn-secondary" id="btn-cancel" onclick="cancel()">Cancelar</button>
                                                    <button type="button" class="btn btn-warning" id="btn-enable" onclick="enableInputs()">Modificar datos</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                        <form action="{{ route('editar-contrasena') }}" method="post" id="form-password">
                                            <meta name="csrf-token-form-password" content="{{ csrf_token() }}" />
                                            <p class="lead">Cambiar contraseña</p><hr>
                                            <div class="form-group text-start">
                                                <label for="contraseña">Nueva contraseña</label>
                                                <input type="password" id="password" name="password" class="form-control" placeholder="Ingresa nueva contraseña" onkeypress="return noStrangeCharacters(event)">
                                                <span class="invalid-feedback" role="alert" id="error-password">
                                            </div>
                                            <div class="form-group text-start">
                                                <label for="contraseña">Confirmar contraseña</label>
                                                <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" placeholder="Confirmar contraseña" onkeypress="return noStrangeCharacters(event)">
                                                <span class="invalid-feedback" role="alert" id="error-confirmPassword">
                                            </div>
                                            <br>
                                            <button type="submit" class="btn btn-primary" onclick="changePassword(event)">Cambiar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6">
                        <div class="card">
                            <div class="card-body">
                                @if(empty($data->nombreImagen))
                                    <div class="row py-5">
                                        <div class="col"></div>
                                        <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                            <div class="form-group">
                                                <label for="">Imagen de perfil</label>
                                                <input type="file" class="form-control" id="img-perfil" name="img-perfil" onchange="cargarBotonEnvio()">
                                                <meta name="csrf-token-upload-photo" content="{{ csrf_token() }}" />
                                            </div>
                                            <div class="form-group mt-2" id="div-boton-subida">
                                                <button class="btn btn-primary" id="boton-subida" onclick="subirFoto()">Subir</button>
                                            </div>
                                        </div>
                                        <div class="col"></div>
                                    </div>
                                @else
                                    <img src="{{ url('/mostrar-foto-perfil',$data->nombreImagen) }}" alt="foto-perfil" id="foto-perfil" class="img-fluid">
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
<script src="{{ asset('js/perfil-usuario.js') }}"></script>