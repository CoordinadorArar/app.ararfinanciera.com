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
            <h1 class="auth-titulo">Crea una nueva contraseña</h1>
            <p class="auth-sub">Elige una contraseña segura para tu cuenta.</p>

            @if (session('status'))
                <div class="ui-alerta ui-alerta-exito auth-alerta" role="status">
                    <i class="fas fa-circle-check" aria-hidden="true"></i><span class="ui-alerta-texto">{{ session('status') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                <div class="auth-grupo">
                    <label for="email" class="auth-label">Correo electrónico</label>
                    <div class="auth-campo">
                        <i class="fas fa-envelope auth-icono" aria-hidden="true"></i>
                        <input id="email" type="email" class="form-control auth-input @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" placeholder="nombre@empresa.com" required autocomplete="email" autofocus onkeypress="return noStrangeCharacters(event)" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email')
                            <span class="invalid-feedback" id="email-error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="auth-grupo">
                    <label for="password" class="auth-label">Nueva contraseña</label>
                    <div class="auth-campo">
                        <i class="fas fa-lock auth-icono" aria-hidden="true"></i>
                        <input id="password" type="password" class="form-control auth-input auth-input-clave @error('password') is-invalid @enderror" name="password" required autocomplete="new-password" onkeypress="return noStrangeCharacters(event)" aria-describedby="password-requisitos @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                        <button type="button" class="auth-toggle" aria-label="Mostrar contraseña" aria-pressed="false">
                            <span class="auth-ojo"><i class="fas fa-eye" aria-hidden="true"></i></span>
                            <span class="auth-ojo-off"><i class="fas fa-eye-slash" aria-hidden="true"></i></span>
                        </button>
                        @error('password')
                            <span class="invalid-feedback" id="password-error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}</span>
                        @enderror
                    </div>
                    <ul class="auth-requisitos" id="password-requisitos" aria-live="polite">
                        @foreach (['longitud' => 'Mínimo 8 caracteres', 'mayuscula' => 'Una mayúscula', 'minuscula' => 'Una minúscula', 'numero' => 'Un número'] as $req => $texto)
                            <li data-req="{{ $req }}"><span class="auth-req-no"><i class="far fa-circle" aria-hidden="true"></i><span class="visually-hidden">Pendiente:</span></span><span class="auth-req-ok"><i class="fas fa-circle-check" aria-hidden="true"></i><span class="visually-hidden">Cumplido:</span></span>{{ $texto }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="auth-grupo">
                    <label for="password-confirm" class="auth-label">Confirmar contraseña</label>
                    <div class="auth-campo">
                        <i class="fas fa-lock auth-icono" aria-hidden="true"></i>
                        <input id="password-confirm" type="password" class="form-control auth-input auth-input-clave" name="password_confirmation" required autocomplete="new-password" onkeypress="return noStrangeCharacters(event)">
                        <button type="button" class="auth-toggle" aria-label="Mostrar contraseña" aria-pressed="false">
                            <span class="auth-ojo"><i class="fas fa-eye" aria-hidden="true"></i></span>
                            <span class="auth-ojo-off"><i class="fas fa-eye-slash" aria-hidden="true"></i></span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary auth-btn" data-cargando="Guardando…">Guardar contraseña</button>
            </form>

            <div class="auth-pie">
                <a href="{{ route('login') }}" class="auth-link"><i class="fas fa-arrow-left" aria-hidden="true"></i> Volver a iniciar sesión</a>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/resetPassword.js') }}"></script>
@endpush
