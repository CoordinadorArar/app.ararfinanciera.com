@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/cupones.css') }}">
@section('content')
    <div class="container" style="min-height: 75vh;" id="divTablaCupones">
        <button class="btn btn-primary btn-sm float-left" onclick="cambiarVista('generar')">
            <i class="fas fa-plus"></i> Generar cupón
        </button>
        <h5 class="text-center"><strong>Histórico Cupones Bancarios <i class="fa fa-ticket"></i></strong></h5>
        <meta name="csrf-token-cupones" content="{{ csrf_token() }}" />
        <!-- Content Form Cupones -->
        <div class="container-fluid my-3">
            <table id="TableHistoryCupones" class="table table-stripted text-center" style="font-size: 12px !important;" cellpadding="0" Cellspacing="0">
                <thead>
                    <tr class="bg-primary">
                        <th>No. Agrupación</th>
                        <th>Consecutivo</th>
                        <th>Cod Operación</th>
                        <th>No. Tercero</th>
                        <th>Tercero</th>
                        <th>Fecha Limite Cupón</th>
                        <th>Valor Cupón ($)</th>
                        <th>Usuario Creación</th>
                        <th>Fecha Creación</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($cupones))
                        @foreach($cupones as $key => $data)
                            <tr>
                                <td><?= $data->IdCupon ?></td>
                                <td><?= $data->Consecutivo ?></td>
                                <td><?= $data->IdOperacion ?></td>
                                <td><?= $data->IdCliente ?></td>
                                <td><?= $data->NomCliente.' '.$data->ApeCliente ?></td>
                                <td><?= $data->FechaLimiteCupon ?></td>
                                <td>$ <?= number_format($data->ValorCupon) ?></td>
                                <td><?= $data->NombreUsuarioRegistro ?></td>
                                <td><?= $data->FechaRegistro ?></td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @include('contable.generar-cupones')
@endsection
<script src="{{ asset('js/cupones.js') }}"></script>