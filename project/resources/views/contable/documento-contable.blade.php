@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/documento-contable.css') }}">
@section('content')
    <div class="content-contable" id="content-contable" style="min-height: 500px"> 
        <h4 class="text-center">Importacion Documento Factoring Siesa</h4>
        <div class="container">
            <meta name="csrf-token-documento-contable" content="{{ csrf_token() }}" />
            <div class="row">
                <div class="container">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12  mb-2">
                            <input type="text" class="form-control form-control-sm" id="desde" name="desde" placeholder="Fecha inicial..." autocomplete="off" onkeypress="return noStrangeCharacters(event)"> 
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12 mb-2">
                            <input type="text" class="form-control form-control-sm" id="hasta" name="hasta" placeholder="Fecha final..." autocomplete="off" onkeypress="return noStrangeCharacters(event)">
                        </div>
                    </div>
                </div>
                <div class="container">
                    <div class="row">
                        <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12  mb-2">
                            <select name="tipoDocumento" id="tipoDocumento" class="form-select form-select-sm" onchange="cargarDatos(this.value)">
                                <option value="">Seleccionar Tipo Documento--</option>
                                <option value="FEX">FEX</option>
                                <option value="FAT">FAT</option>
                                <option value="NCR">NCR</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12" id="div-table">
                    <table class="table table-sm table-bordered" id="table-documentos">
                        <thead>
                            <tr class="bg-primary">
                                <th class="text-center">Tipo Documento</th>
                                <th class="text-center">Factura / Nota Credito</th>
                                <th class="text-center">Seleccionar</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyFactoringsiesa">
                        </tbody>
                    </table>
                </div>
                <div class="container" id="containerEnvios">
                </div>
            </div>
        </div>
    </div>
@endsection
<script src="{{ asset('js/documento-contable.js') }}"></script>