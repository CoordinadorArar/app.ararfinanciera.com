@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/pagadurias.css') }}">
@section('content')
    <div class="text-center">
        <h4>Pagadurias</h4><hr>
        <div class="row">
            <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="text-center">Pagadurias registradas</h5><hr>
                        <select name="lista-pagadurias" id="lista-pagadurias" class="form-select" onchange="validarTipoDescuento(this.value)">
                            <option value="">--Elije una pagaduria--</option>
                            @if($pagadurias)
                                @foreach($pagadurias as $data)
                                    <option value="{{ $data->IdPagaduria }}">{{ $data->NombrePagaduria }}</option>
                                @endforeach
                            @endif
                        </select><br>
                        <select name="id-configuracion" id="id-configuracion" class="form-select"></select>
                    </div>
                </div><br>
                <div class="card">
                    <div class="card-body">
                        <h5 class="text-center">Nueva pagaduria</h5><hr>
                        <form action="{{ route('guardar-pagaduria') }}" method="post" id="form-pagaduria">
                            <meta name="csrf-token-form-pagaduria" content="{{ csrf_token() }}" />
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <label for="nombrePagaduria">Nombre pagaduria</label>
                                        <input type="text" id="nombrePagaduria" name="nombrePagaduria" class="form-control" value="{{ old('nombrePagaduria') }}" placeholder="Ingresa nombre de la pagaduria" onkeypress="return noStrangeCharacters(event)">
                                    </div>
                                    <span class="invalid-feedback" role="alert" id="error-nombrePagaduria">
                                </div>
                            </div><br>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary" id="" onclick="guardarPagaduria(event)">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <div class="card">
                    <div class="card-body" id="card-1">
                        <input type="hidden" id="id-config-pagaduria" onkeypress="return noStrangeCharacters(event)">
                        <div id="form-configuracion">

                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                <div class="card">
                    <div class="card-body" id="card-2">
                        <h5 class="text-center">Nuevo Rubro</h5>
                        <form action="{{ route('guardar-rubro-configuracion') }}" method="post" id="form-rubro-config">
                            <meta name="csrf-token-form-rubro" content="{{ csrf_token() }}" />
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <input type="text" id="nombreRubro" name="nombreRubro" class="form-control" value="{{ old('nombreRubro') }}" placeholder="Ingresa nombre de rubro" onkeypress="return noStrangeCharacters(event)">
                                    </div>
                                    <span class="invalid-feedback" role="alert" id="error-nombreRubro">
                                </div>
                            </div>
                            <div class="d-grid mt-1">
                                <button type="submit" class="btn btn-primary" id="" onclick="guardarRubro(event)">Guardar</button>
                            </div>
                        </form>
                        <hr>
                        <h5 class="text-center">Agregar elementos a la operación</h5>
                        <div id="form-datos-configuracion">
                            <div class="form-group">
                                <select name="datosVariables" id="datosVariables" class="form-select" onchange="botonRubro(this.value)">
                                    <option value="">Elije un dato...</option>
                                </select>
                            </div><br>
                            <div id="botonRubro">

                            </div>
                            <hr>
                            <div id="numeros&operadores">
                                <div id="fila-1">
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_1">1</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_2">2</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_3">3</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_/">/</button>
                                </div>
                                <div id="fila-2">
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_4">4</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_5">5</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_6">6</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_x">x</button>
                                </div>
                                <div id="fila-3">
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_7">7</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_8">8</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_9">9</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_+">+</button>
                                </div>
                                <div id="fila-4">
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_0">0</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_(">(</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_)">)</button>
                                    <button class="btn btn-symbol-2" draggable="true" ondragstart="insertarElementoOperacion(this)" id="symbol_-">-</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--Modal edición de operacion-->
    <div class="modal fade" id="modalEdicion" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body p-2">
                    <div id="vistaPreviaEdicion" class="text-center">

                    </div>
                    <div class="text-center">
                        <button type="button" class="btn btn-secondary" id="btnCancelarEdicion">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
<script src="{{ asset('js/pagadurias.js') }}"></script>