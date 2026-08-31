@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/crear-cliente-siesa.css') }}">
@section('content')
    <div style="min-height: 500px" id="div-crear-cliente">
        <h4 class="text-center">Creacion Clientes Siesa</h4>
        <div class="container">
            <meta name="csrf-token-crear-cliente" content="{{ csrf_token() }}" />
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                    <div class="col-lg-10 col-md-12 col-sm-12 col-xs-12">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Nombres</th>
                                        <th>Apellidos</th>
                                        <th>Gestionar</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyClientes">
                                    @if($validarUsuariosSiesa != 0)
                                        
                                        @foreach($validarUsuariosSiesa as $data)
                                            @if($data->IdSiesa == 0 || $data->cliente == 0 || $data->Idproveedortercero === 0)
                                                
                                                <tr>
                                                    <td>{{ $data->IdCliente }}</td>
                                                    <td>{{ $data->NomCliente }}</td>
                                                    <td>{{ $data->ApeCliente }}</td>
                                                    <td>
                                                        <button data-toggle="tooltip" title="Creacion Cliente" class="btn btn-primary" id="btn_crear" name="btn_crear" 
                                                        onclick="CrearCliente({{ $data->IdCliente }});">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>    
                        </div>               
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
<script src="{{ asset('js/crear-cliente-siesa.js') }}"></script>