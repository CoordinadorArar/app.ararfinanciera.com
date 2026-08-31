<link href="{{ asset('css/login.css') }}" rel="stylesheet">
@extends('layouts.app')

@section('content')
<div class="card" id="login">
    <div class="text-center">
        <h4 class="display-6 text-center" id="title-login">Inicio de sesión</h4>
        <img src="{{ asset('images/inicio_sesion.png') }}" alt="" class="img-fluid">
    </div>
    <div class="card-body">
        <div class="text-center">
            <form action="{{ route('login') }}" id="form" method="post" onsubmit="return onSubmit(event)"><!--  -->
                {{ csrf_field() }}
                <meta name="csrf-token-login" content="{{ csrf_token() }}" />
                <div class="row">
                    <div class="col">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text" ><i class="fa fa-user"></i></span>
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="Ingrese correo electrónico" required autocomplete="email" autofocus onpaste="return false" onkeypress="return noStrangeCharacters(event)">
                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <label for="Contraseña" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" value="{{ old('password') }}" placeholder="Ingrese contraseña" required autocomplete="current-password" onpaste="return false" onkeypress="return noStrangeCharacters(event)">
                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>
                </div><br>
                <div class="row">
                    <div class="col">
                        <button type="submit" class="btn btn-primary">Iniciar</button>
                    </div>
                </div>
            </form>
        </div>
    </div><hr>
    <div class="mb-3 d-flex justify-content-around align-items-baseline" id="links-login">
        <h6><a href="https://ararfinanciera.com">Inicio</a></h6><h6><a href="http://app.ararfinanciera.com/password/reset" class="text-center">Olvidé mi contraseña</a></h6>
    </div>
</div>
@endsection
<script src="{{ asset('js/login.js') }}"></script>

