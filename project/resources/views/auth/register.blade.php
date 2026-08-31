@extends('layouts.app')
<link href="{{ asset('css/register.css') }}" rel="stylesheet">
@section('content')
<div class="mb-3">
    <div class="card" id="register">
        <h4 class="display-6 text-center">Registro</h4>
        <hr>
        <div class="card-body">
            <div class="text-center">
                <form method="post" action="{{ route('register') }}" id="form">
                    @csrf
                    <div class="row">
                        <div class="col-lg-6 col-md-6 col-sm-12">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <div class="input-group">
                                    <span class="input-group-text" ><i class="fa fa-user"></i></span>
                                    <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="Ingrese nombre" required autocomplete="name" autofocus onkeypress="return noStrangeCharacters(event)">
                                </div>
                                <span class="invalid-feedback" role="alert" id="error-name">
                                </span>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-12">
                            <div class="mb-3">
                                <label for="documento" class="form-label">Documento</label>
                                <div class="input-group">
                                    <span class="input-group-text" ><i class="fa fa-id-card"></i></span>
                                    <input id="documento" type="text" class="form-control @error('document') is-invalid @enderror" name="documento" value="{{ old('document') }}" placeholder="Ingrese documento" required autocomplete="document" autofocus onkeypress="return soloNumeros(event)">
                                </div>
                                <span class="invalid-feedback" role="alert" id="error-document">
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo electrónico</label>
                                <div class="input-group">
                                    <span class="input-group-text" ><i class="fa fa-envelope"></i></span>
                                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="Ingrese correo electrónico" required autocomplete="email" onkeypress="return noStrangeCharacters(event)">
                                </div>
                                <span class="invalid-feedback" role="alert" id="error-email">
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 col-md-6 col-sm-12">
                            <div class="mb-3">
                                <label for="Contraseña" class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="Ingrese contraseña" required autocomplete="new-password" onkeypress="return noStrangeCharacters(event)">
                                </div>
                                <span class="invalid-feedback" role="alert" id="error-password">
                                </span>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-12">
                            <div class="mb-3">
                                <label for="confirmContrasena" class="form-label">Confirmar contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirmPassword" id="confirmPassword" placeholder="Confirme contraseña..." onkeypress="return noStrangeCharacters(event)">
                                    <span class="input-group-text" id="show-pass" onclick="showPass()"><i class="fa fa-eye"></i></span>
                                </div>
                                <span class="invalid-feedback" role="alert" id="error-confirmPassword">
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" onclick="register(event)" class="btn btn-primary">Registrar</button>
                    </div>
                </form><hr>
                <a class="btn btn-danger" href="{{ route('gestion-usuarios') }}">Cancelar</a>
            </div>
        </div>
    </div>
</div>
@endsection
<script src="{{ asset('js/register.js') }}"></script>