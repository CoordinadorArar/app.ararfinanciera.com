@extends('layouts.blank')

@section('contenedor', 'auth-raiz')

@push('styles')
<link href="{{ asset('css/auth.css') }}" rel="stylesheet">
@endpush

@section('content')
<div class="auth">
    @include('auth.partials.marca')
    <div class="auth-panel">
        <div class="auth-card">
            <a href="https://ararfinanciera.com" class="auth-logo" aria-label="Ir a ararfinanciera.com">
                <img src="{{ asset('images/LogoArar.png') }}" alt="Arar Financiera">
            </a>
            <h1 class="auth-titulo">Recupera tu contraseña</h1>
            <p class="auth-sub">Ingresa el correo registrado en tu cuenta y te enviaremos un enlace para restablecerla.</p>

            @if (session('status'))
                <div class="ui-alerta ui-alerta-exito auth-alerta" role="status">
                    <i class="fas fa-circle-check" aria-hidden="true"></i><span class="ui-alerta-texto">{{ session('status') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="auth-grupo">
                    <label for="email" class="auth-label">Correo electrónico</label>
                    <div class="auth-campo">
                        <i class="fas fa-envelope auth-icono" aria-hidden="true"></i>
                        <input id="email" type="email" class="form-control auth-input @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="nombre@empresa.com" required autocomplete="email" autofocus onkeypress="return noStrangeCharacters(event)" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email')
                            <span class="invalid-feedback" id="email-error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary auth-btn" data-cargando="Enviando…">{{ session('status') ? 'Reenviar enlace' : 'Enviar enlace' }}</button>
            </form>

            <div class="auth-pie">
                <a href="{{ route('login') }}" class="auth-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Volver a iniciar sesión</a>
            </div>
        </div>
    </div>
</div>
@endsection
