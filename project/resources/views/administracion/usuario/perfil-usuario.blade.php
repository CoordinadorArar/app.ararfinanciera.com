@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/perfil-usuario.css') }}">
@section('content')
    @if($user)
        @foreach($user as $data)
            <div>
                <h4 class="text-center">Perfil de usuario</h4>
                <div class="row">
                    <div class="col-12 col-md-6 col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="pb-2 mb-3 border-bottom">Datos registrados</h5>
                                <div class="row">
                                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                        <form action="{{ route('editar-datos-usuario') }}" method="post" id="form-data-user">
                                            <meta name="csrf-token-form-data" content="{{ csrf_token() }}" />
                                            <div class="form-group mb-3">
                                                <label for="nombre">Nombre</label>
                                                <input type="text" id="nombreUsuario" name="nombreUsuario" class="form-control input-data" value="{{ $data->nombreUsuario }}" placeholder="Nombre" disabled onkeypress="return noStrangeCharacters(event)">
                                                <span class="invalid-feedback" role="alert" id="error-nombreUsuario"></span>
                                            </div>
                                            <div class="form-group mb-3">
                                                <label for="email">Correo electrónico</label>
                                                <input type="text" id="email" name="email" class="form-control input-data" value="{{ $data->email }}" placeholder="Email" disabled onkeypress="return noStrangeCharacters(event)">
                                                <span class="invalid-feedback" role="alert" id="error-email"></span>
                                            </div>
                                            <div class="form-group mb-3">
                                                <label for="documento">Documento</label>
                                                <input type="text" id="documento" name="documento" class="form-control input-data" value="{{ $data->documentoUsuario }}" placeholder="Doocumento" disabled onkeypress="return soloNumeros(event)">
                                                <span class="invalid-feedback" role="alert" id="error-documento"></span>
                                            </div>
                                            <div class="row">
                                                <div class="col">
                                                    <button class="btn btn-primary me-1" id="btn-save" onclick="guardarDatos(event)">Guardar</button>
                                                    <button type="button" class="btn btn-secondary" id="btn-cancel" onclick="cancel()">Cancelar</button>
                                                    <button type="button" class="btn btn-warning" id="btn-enable" onclick="enableInputs()">Modificar datos</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                        <form action="{{ route('editar-contrasena') }}" method="post" id="form-password">
                                            <meta name="csrf-token-form-password" content="{{ csrf_token() }}" />
                                            <p class="lead pb-2 mb-3 border-bottom">Cambiar contraseña</p>
                                            <div class="form-group mb-3">
                                                <label for="contraseña">Nueva contraseña</label>
                                                <input type="password" id="password" name="password" class="form-control" placeholder="Ingresa nueva contraseña" onkeypress="return noStrangeCharacters(event)">
                                                <span class="invalid-feedback" role="alert" id="error-password"></span>
                                            </div>
                                            <div class="form-group mb-3">
                                                <label for="contraseña">Confirmar contraseña</label>
                                                <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" placeholder="Confirmar contraseña" onkeypress="return noStrangeCharacters(event)">
                                                <span class="invalid-feedback" role="alert" id="error-confirmPassword"></span>
                                            </div>
                                            <button type="submit" class="btn btn-primary" onclick="changePassword(event)">Cambiar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
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
                @if(\App\Support\Ambiente::puedeConmutar())
                    @php($ambienteActual = config('database.ambiente'))
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card amb-card text-start{{ $ambienteActual === 'demo' ? ' demo' : '' }}">
                                <div class="card-body">
                                    <div class="pb-2 mb-3 border-bottom">
                                        <h5 class="mb-1"><i class="fas fa-flask me-2"></i>Ambiente de trabajo</h5>
                                        <p class="text-muted small mb-0">Define con qué datos trabaja tu sesión. El cambio aplica sólo a tu usuario.</p>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                        <div>
                                            @if($ambienteActual === 'demo')
                                                <span class="badge text-bg-warning">Demo</span>
                                                <p class="small mb-0 mt-1">Estás en Demo. Los datos que consultas y guardas son de prueba y no afectan la información real.</p>
                                            @else
                                                <span class="badge bg-primary">Producción</span>
                                                <p class="small mb-0 mt-1">Estás en Producción. Los datos que consultas y guardas son reales.</p>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" role="switch" id="switchAmbiente" onchange="conmutarAmbiente(this.checked ? 'demo' : 'produccion',this)" {{ $ambienteActual === 'demo' ? 'checked' : '' }}>
                                                <label class="form-check-label" for="switchAmbiente">Activar ambiente Demo</label>
                                            </div>
                                            <div class="form-text">Al cambiar de ambiente se recarga la página y se reinicia el contexto de datos.</div>
                                            <span class="invalid-feedback" role="alert" id="error-ambiente"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
@endsection
<script src="{{ asset('js/perfil-usuario.js') }}"></script>