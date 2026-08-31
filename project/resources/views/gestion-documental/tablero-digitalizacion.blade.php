@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/gestion-documental-folios.css') }}">
@section('content')
    <div class="container" style="min-height: 75vh;" id="divTableroDigitalizacion">
        <h5 class="text-center"><strong>Tablero Digitalización <i class="fas fa-chart-bar"></i></strong></h5>
        <meta name="csrf-token-tablero-digitalizacion" content="{{ csrf_token() }}" />

        <div class="container-fluid my-3">
            <div class="row text-center mb-4">
                <div class="col">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted">Total facturas pendientes de digitalizar</h6>
                            <h2 id="totalPendientes">-</h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <h6 class="text-center">Por Asesor</h6>
                    <canvas id="chartPorAsesor"></canvas>
                </div>
                <div class="col-md-6 mb-4">
                    <h6 class="text-center">Por Mes</h6>
                    <canvas id="chartPorMes"></canvas>
                </div>
            </div>

            <div class="table-responsive mt-4">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr class="bg-primary">
                            <th>Asesor</th>
                            <th>Facturas pendientes</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPorAsesor">
                        <tr><td colspan="2" class="text-center">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/chart.min.js') }}"></script>
    <script src="{{ asset('js/gestion-documental-tablero.js') }}"></script>
@endsection
