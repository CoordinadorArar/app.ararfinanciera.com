@extends('layouts.app')
<link href="{{ asset('css/forms-datos.css') }}" rel="stylesheet">
@section('content')
    <div class="text-center">
        <div class="card w-75 ms-auto me-auto mb-5" id="data-finished">
            <div class="card">
                <div class="card-body">
                    <p class="lead">Perfecto!</p>
                    <br>
                    <p class="lead">
                        El proceso se iniciará. Próximo paso: consulta en centrales de riesgo.
                    </p><br>
                    <div class="d-grid">
                        <a href="{{ route('home') }}" class="btn btn-primary">Inicio</a>
                    </div>
                </div>
            </div>
        </div>
        @include('perfilamiento.registro-datos.form-datos-personales')
        @include('perfilamiento.registro-datos.form-datos-financieros')
        @include('perfilamiento.registro-datos.form-tratamiento-datos')
    </div>
@endsection
<script src="{{ asset('js/form-datos.js') }}"></script>