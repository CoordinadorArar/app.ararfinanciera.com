@extends('layouts.app')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/simulador.css') }}">
@endpush
@section('content')
    @php($tasaVigente = \App\Services\CalculadoraCredito::valorVariable('TasaInteres'))
    <div class="ui-contenedor">
        <meta name="csrf-token-simulador" content="{{ csrf_token() }}"/>
        <header class="ui-encabezado">
            <div>
                <h1 class="ui-titulo">Simulador de crédito</h1>
                <p class="ui-descripcion">Calcula cuota, seguro y amortización</p>
            </div>
        </header>
        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <section class="ui-card" aria-labelledby="titulo-datos-simulacion">
                    <div class="ui-card-cab">
                        <h2 id="titulo-datos-simulacion">Datos</h2>
                    </div>
                    <form id="form-simulador" novalidate onsubmit="calcular(event)">
                        <div class="mb-3">
                            <label for="idPagaduria" class="form-label">Pagaduría</label>
                            <select class="form-select" id="idPagaduria" aria-describedby="error-idPagaduria" onchange="validarPeriodo()">
                                <option value="">Selecciona una pagaduría</option>
                            </select>
                            <span class="invalid-feedback" role="alert" id="error-idPagaduria"></span>
                        </div>
                        <div class="mb-3">
                            <label for="fechaEdad" class="form-label">Fecha de nacimiento</label>
                            <input type="text" class="form-control" id="fechaEdad" autocomplete="off" placeholder="dd/mm/aaaa" aria-describedby="ayuda-edad error-fechaEdad" onchange="validarPeriodo()">
                            <span class="invalid-feedback" role="alert" id="error-fechaEdad"></span>
                            <span class="ui-campo-ayuda" id="ayuda-edad" aria-live="polite"></span>
                        </div>
                        <div class="mb-3">
                            <label for="periodoCredito" class="form-label">Plazo</label>
                            <select class="form-select" id="periodoCredito" aria-describedby="error-periodoCredito" disabled onchange="this.classList.remove('is-invalid')">
                                <option value="">Selecciona pagaduría y fecha</option>
                            </select>
                            <span class="invalid-feedback" role="alert" id="error-periodoCredito"></span>
                            <div id="aviso-plazo" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label for="valorCredito" class="form-label">Monto</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">$</span>
                                <input type="text" inputmode="numeric" class="form-control" id="valorCredito" autocomplete="off" aria-describedby="error-valorCredito" oninput="formatearInputMoneda(this);this.classList.remove('is-invalid')">
                                <span class="invalid-feedback" role="alert" id="error-valorCredito"></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <p class="form-label mb-0">Tasa mensual</p>
                            <p class="form-control-plaintext py-1" id="tasaSimulador">
                                {{ is_numeric($tasaVigente) ? number_format($tasaVigente,2,',','.').' % mensual' : 'Sin definir' }}
                                <i class="fas fa-lock text-secondary ms-1" aria-hidden="true"></i>
                            </p>
                            <span class="ui-campo-ayuda mt-0">Definida en Administración › Variables</span>
                            @unless(is_numeric($tasaVigente))
                                <div class="ui-alerta ui-alerta-adv mt-2 mb-0" role="status">
                                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i><span class="ui-alerta-texto">No hay tasa configurada en Variables</span>
                                </div>
                            @endunless
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary ui-btn" id="btnCalcular" @unless(is_numeric($tasaVigente)) disabled @endunless>
                                <i class="fas fa-calculator" aria-hidden="true"></i><span>Calcular</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>
            <div class="col-12 col-lg-8">
                <div id="resultado-simulacion" aria-live="polite">
                    <section class="ui-card">
                        <div class="ui-vacio">
                            <i class="fas fa-calculator" aria-hidden="true"></i>
                            <p class="ui-vacio-titulo">Completa los datos y pulsa Calcular</p>
                            <p class="ui-vacio-texto mb-0">Verás la cuota, el seguro y la tabla de amortización.</p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/simulador.js') }}"></script>
@endpush
