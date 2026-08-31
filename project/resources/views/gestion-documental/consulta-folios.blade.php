@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/gestion-documental-folios.css') }}">
@section('content')
    <div class="container" style="min-height: 75vh;" id="divConsultaFolios">
        <h5 class="text-center"><strong>Consulta de Folios <i class="fas fa-search"></i></strong></h5>
        <meta name="csrf-token-consulta-folios" content="{{ csrf_token() }}" />

        <div class="container-fluid my-3">
            <form id="formConsultaFolios" class="row g-2 align-items-end justify-content-center">
                <div class="col-auto">
                    <label class="form-label mb-0"><strong>Buscar por</strong></label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipoBusqueda" id="tipoCliente" value="cliente" checked>
                        <label class="form-check-label" for="tipoCliente">Cliente</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipoBusqueda" id="tipoFolio" value="folio">
                        <label class="form-check-label" for="tipoFolio">Folio</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipoBusqueda" id="tipoFactura" value="factura">
                        <label class="form-check-label" for="tipoFactura">Factura</label>
                    </div>
                </div>
                <div class="col-auto">
                    <label for="valorBusqueda" class="form-label mb-0">Documento, nombre, folio o factura</label>
                    <input type="text" class="form-control" id="valorBusqueda" name="valorBusqueda" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Consultar</button>
                </div>
            </form>

            <div id="divResultadosFolios" class="mt-4"></div>
        </div>
    </div>
    <script src="{{ asset('js/gestion-documental-folios.js') }}"></script>
@endsection
