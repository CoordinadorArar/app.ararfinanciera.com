@extends('layouts.app')

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
            <h1 class="auth-titulo">Iniciar sesión</h1>
            <p class="auth-sub">Ingresa con tu correo corporativo.</p>

            @if (session('status'))
                <div class="ui-alerta ui-alerta-exito auth-alerta" role="status">
                    <i class="fas fa-circle-check" aria-hidden="true"></i><span class="ui-alerta-texto">{{ session('status') }}</span>
                </div>
            @endif

            <form action="{{ route('login') }}" id="form" method="post" onsubmit="return onSubmit(event)">
                {{ csrf_field() }}
                <meta name="csrf-token-login" content="{{ csrf_token() }}" />

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

                <div class="auth-grupo">
                    <label for="password" class="auth-label">Contraseña</label>
                    <div class="auth-campo">
                        <i class="fas fa-lock auth-icono" aria-hidden="true"></i>
                        <input id="password" type="password" class="form-control auth-input auth-input-clave @error('password') is-invalid @enderror" name="password" required autocomplete="current-password" onkeypress="return noStrangeCharacters(event)" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        <button type="button" class="auth-toggle" aria-label="Mostrar contraseña" aria-pressed="false">
                            <span class="auth-ojo"><i class="fas fa-eye" aria-hidden="true"></i></span>
                            <span class="auth-ojo-off"><i class="fas fa-eye-slash" aria-hidden="true"></i></span>
                        </button>
                        @error('password')
                            <span class="invalid-feedback" id="password-error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="auth-olvido">
                    <a href="{{ route('password.request') }}" class="auth-link">¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="btn btn-primary auth-btn">Iniciar sesión</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/login.js') }}"></script>
@endpush
