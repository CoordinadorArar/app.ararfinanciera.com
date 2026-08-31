@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/simulador.css') }}">
@section('content')
    <div class="text-center">
        <h3 class="text-center">Simulador de Cr&eacutedito</h3><hr>
        <div class="container">
            <div class="row">
                <meta name="csrf-token-simulador" content="{{ csrf_token() }}"/>
                <div class="col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4 text-center mb-2">
                    <h5 class="text-start">Valores para realizar la simulación</h5><hr>
                    <div class="col-12 col-sm-12 col-md-12 col-lg-12 col-xl-10">
                        <input type="text" max="{{ $fecha_actual }}" class="form-control"
                            id="fechaEdad" name="fechaEdad" data-toggle="tooltip" data-bs-placement="top" title="Fecha Nacimiento"  data-bs-html="true" 
                            onclick="validarPeriodo(this)" onchange="validarPeriodo(this)" placeholder="Ingresa Fecha de nacimiento" onkeypress="return noStrangeCharacters(event)">
                        <span class="invalid-feedback" role="alert" id="error-fechaEdad"></span>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 col-xl-10 mt-2">
                        <select class="form-select" name="periodoCredito" id="periodoCredito">
                            <option value=" ">- Selecciona periodo de crédito -</option>
                        </select>
                        <span class="invalid-feedback" role="alert" id="error-periodoCredito"></span>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 col-xl-10 mt-2">
                        <input type="text" class="form-control" placeholder="Ingresa Valor Credito..." id="valorCredito" data-type="currency" onblur="formatCurrency(this,'blur')" onkeyup="formatCurrency(this)" onkeypress="return soloNumeros(event)">
                        <span class="invalid-feedback" role="alert" id="error-valorCredito"></span>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 col-xl-10 mt-2">
                        <input type="text" class="form-control" placeholder="Ingresa Tasa Credito..." id="tasaInteres" onkeypress="return noStrangeCharacters(event)">
                        <span class="invalid-feedback" role="alert" id="error-tasaInteres"></span>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 col-xl-10 mt-2">
                        <button data-toggle="tooltip" title="Calcular" class="btn btn-primary" type="button" id="btnCalcular" onclick="calcular()">
                            <i class="fas fa-calculator"></i> Calcular
                        </button>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-12 col-md-8 col-lg-8 col-xl-8">
                    <table id="tablaInformacion">
                        <tr>
                            <td>Valor Credito : $ </td>
                        </tr>
                        <tr>
                            <td>Valor Cuota Mensual : $ </td>
                        </tr>
                        <tr>
                            <td>Numero Cuota : </td>
                        </tr>
                        <tr>
                            <td>Tipo Credito : </td>
                        </tr>
                        <tr>
                            <td>Tasa Mensual : % </td>
                        </tr>
                        <tr style="border-top: 0.5px solid;">
                            <td><h2 style="font-size: 18px;font-weight: 700;">Tabla de simulaci&oacuten de cr&eacutedito</h2></td>
                        </tr>
                    </table>
                    <div class="table-responsive">
                        <table class="table" id="tablaSimulacion">
                            <thead>
                                <tr class="bg-primary">
                                    <th>Cuota N°</th>
                                    <th>Cuota</th>
                                    <th>Capital</th>
                                    <th>Interes</th>
                                    <th>Seguros</th>
                                    <th>Valor Cuota</th>
                                    <th>Saldo</th>
                                </tr>
                            </thead>
                            <tbody id="tbodySimulacion">
                                <tr>
                                    <td colspan="7" style="text-align:center;color:#b5b5b5;">
                                        <h4 class="display-5">Ingresa los valores para la simulación</h4>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
<script src="{{ asset('js/simulador.js') }}"></script>