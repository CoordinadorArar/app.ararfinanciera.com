@extends('layouts.app')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/gestion-sitio.css') }}">
@endpush
@section('content')
    <div class="text-center">
        <header class="ui-encabezado text-start">
            <div>
                <h1 class="ui-titulo">Administración del aplicativo</h1>
                <p class="ui-descripcion">Roles, valores variables y proveedores de centrales de riesgo</p>
            </div>
        </header>
        <div class="row">
            <meta name="csrf-token-admin-management" content="{{ csrf_token() }}" />
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <input type="hidden" id="rol-usuario-validar" value="{{ $rol[0]->IdRol }}" onkeypress="return noStrangeCharacters(event)">
                <div class="card" id="card-roles">
                    <div class="card-body">
                        <h5>Roles</h5><hr>
                        <form action="" method="post" id="form-roles">
                            <div class="">
                                {{-- <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12"> --}}
                                <div class="row">
                                    <div class="col-lg-10 col-md-10 col-sm-10 col-xs-10">
                                        @if($roles)
                                        <select class="form-select form-select-sm" id="listaRoles" onchange="gestionRol('editar',this.value)">
                                            <option value="0">Roles registrados...</option>
                                                @foreach($roles as $data)
                                                    <option value="{{ $data->IdRol }}">{{ $data->NombreRol }}</option>
                                                @endforeach
                                        </select>
                                        @endif
                                    </div>
                                    <div class="col-lg-1 col-md-1 col-sm-1 col-xs-">
                                        <button type="button" class="btn btn-primary" title="Nuevo Rol" onclick="gestionRol('nuevo','')">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                {{-- </div> --}}
                                {{-- <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 mt-2"> --}}
                                    <div class="form-group my-2" id="inputNuevoRol">
                                        <label for="">Nombre del Rol</label>
                                        <input type="text" class="form-control form-control-sm" id="nombreRol" name="nombreRol" placeholder="Ingresa nombre de nuevo rol..." onkeypress="return noStrangeCharacters(event)">
                                    </div>
                                {{-- </div> --}}
                                {{-- <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 mt-2"> --}}
                                    <h5 class="mt-2">Acceso a items del menú</h5>
                                    <div id="submenusRol"></div>
                                {{-- </div> --}}
                                <button type="submit" class="btn btn-primary mt-2" id="btnGuardarRol" onclick="guardarRol(event)">Guardar</button>
                                <button type="button" class="btn btn-danger mt-2" id="cancelRol" onclick="cancelarGuardado('rol')">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 text-start">
                <section class="ui-card" aria-labelledby="titulo-variables">
                    <div class="ui-card-cab">
                        <h2 id="titulo-variables">Valores variables</h2>
                        <button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="cancelarGuardado('variable');document.getElementById('nombreVariable').focus()">
                            <i class="fas fa-plus" aria-hidden="true"></i><span>Nueva variable</span>
                        </button>
                    </div>
                    <div class="ui-scroll mb-3">
                        <table class="ui-tabla" id="listaVariables">
                            <thead>
                                <tr>
                                    <th scope="col">Variable</th>
                                    <th scope="col" class="num">Valor</th>
                                    <th scope="col" class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($valoresVariables ?: [] as $data)
                                    @php($clave = strtolower(trim($data->NombreValorVariable)))
                                    <tr data-variable="{{ $data->IdValorVariable }}">
                                        <td>
                                            @if($clave == 'salariominimomensual')
                                                Salario mínimo mensual <i class="fas fa-lock text-secondary ms-1" title="Variable del sistema: no se puede renombrar" aria-label="Variable del sistema: no se puede renombrar"></i>
                                            @elseif($clave == 'tasainteres')
                                                Tasa de interés <i class="fas fa-lock text-secondary ms-1" title="Variable del sistema: no se puede renombrar" aria-label="Variable del sistema: no se puede renombrar"></i>
                                            @else
                                                {{ $data->NombreValorVariable }}
                                            @endif
                                        </td>
                                        <td class="num">
                                            @if($clave == 'salariominimomensual' && is_numeric($data->ValorVariable))
                                                $ {{ number_format($data->ValorVariable,0,',','.') }}
                                            @elseif($clave == 'tasainteres' && is_numeric($data->ValorVariable))
                                                {{ number_format($data->ValorVariable,2,',','.') }} % mensual
                                            @else
                                                {{ $data->ValorVariable }}
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="editarValorVariable({{ $data->IdValorVariable }})">
                                                <i class="fas fa-pen" aria-hidden="true"></i><span>Editar</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <form action="" method="post" id="form-variables" class="border-top pt-3" novalidate>
                        <h3 class="ui-cifra-etiqueta mb-3" id="tituloFormVariable">Nueva variable</h3>
                        <input type="hidden" id="idVariable" value="">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="nombreVariable" class="form-label">Nombre de la variable</label>
                                <input type="text" class="form-control" id="nombreVariable" name="nombreVariable" aria-describedby="error-nombreVariable ayuda-nombreVariable" onkeypress="return noStrangeCharacters(event)">
                                <span class="invalid-feedback" role="alert" id="error-nombreVariable"></span>
                                <span class="ui-campo-ayuda d-none" id="ayuda-nombreVariable">El nombre de esta variable es fijo</span>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="valorVariable" class="form-label">Valor</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text d-none" id="prefijoValor">$</span>
                                    <input type="text" class="form-control" id="valorVariable" name="valorVariable" aria-describedby="error-valorVariable ayuda-valorVariable" oninput="formatearValorVariable(this)">
                                    <span class="input-group-text d-none" id="sufijoValor">% mensual</span>
                                    <span class="invalid-feedback" role="alert" id="error-valorVariable"></span>
                                </div>
                                <span class="ui-campo-ayuda" id="ayuda-valorVariable"></span>
                            </div>
                        </div>
                        <div class="ui-card-pie">
                            <button type="button" class="btn btn-outline-secondary ui-btn" id="cancelVariable" onclick="cancelarGuardado('variable')">
                                <i class="fas fa-xmark" aria-hidden="true"></i><span>Cancelar</span>
                            </button>
                            <button type="submit" class="btn btn-primary ui-btn" id="btnGuardarVariable" onclick="guardarVariable(event)">
                                <i class="fas fa-floppy-disk" aria-hidden="true"></i><span id="textoGuardarVariable">Crear variable</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>
            @if(collect($rol)->contains('IdRol',1))
            <div class="col-12 text-start">
                <section class="ui-card" aria-labelledby="titulo-centrales">
                    <div class="ui-card-cab">
                        <h2 id="titulo-centrales">Centrales de riesgo</h2>
                    </div>
                    <p class="ui-descripcion mt-0 mb-3">Proveedores para consultar el historial crediticio. Las credenciales se configuran en el servidor y no se editan aquí.</p>
                    <form id="form-centrales" novalidate onsubmit="guardarCentrales(event)">
                        <div id="avisoCentrales"></div>
                        <div class="ui-scroll mb-3">
                            <table class="ui-tabla ui-tabla-tarjetas">
                                <caption class="visually-hidden">Proveedores de centrales de riesgo</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Proveedor</th>
                                        <th scope="col">Habilitado</th>
                                        <th scope="col">Credenciales</th>
                                        <th scope="col">Ambiente</th>
                                        <th scope="col">Última consulta</th>
                                        <th scope="col">Predeterminado</th>
                                        <th scope="col" class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="filasCentrales"></tbody>
                            </table>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="proveedorDefecto" id="predet-ninguno" value="" onchange="limpiarErrorCentrales('predeterminadoCentral')">
                            <label class="form-check-label" for="predet-ninguno">Ninguno: cada usuario elige el proveedor al consultar</label>
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-predeterminadoCentral"></span>
                        <div class="row mt-3">
                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="vigenciaCentrales" class="form-label">Vigencia de la consulta</label>
                                <div class="input-group has-validation">
                                    <input type="number" class="form-control" id="vigenciaCentrales" min="1" max="365" step="1" inputmode="numeric" aria-describedby="error-vigenciaCentrales ayuda-vigenciaCentrales" oninput="limpiarErrorCentrales('vigenciaCentrales')">
                                    <span class="input-group-text">días</span>
                                    <span class="invalid-feedback" role="alert" id="error-vigenciaCentrales"></span>
                                </div>
                                <span class="ui-campo-ayuda" id="ayuda-vigenciaCentrales">Durante este tiempo se reutiliza la última consulta del cliente y no se genera un nuevo cobro. Entre 1 y 365 días.</span>
                            </div>
                        </div>
                        <div class="ui-card-pie">
                            <button type="button" class="btn btn-outline-secondary ui-btn" onclick="cancelarCentrales()">
                                <i class="fas fa-xmark" aria-hidden="true"></i><span>Cancelar</span>
                            </button>
                            <button type="submit" class="btn btn-primary ui-btn" id="btnGuardarCentrales">
                                <i class="fas fa-floppy-disk" aria-hidden="true"></i><span>Guardar cambios</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>
            @endif
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/gestion-sitio.js') }}"></script>
@endpush
