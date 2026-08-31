<div id="form-datos-financieros" class="forms-datos-tercero">
    <div class="container">
        <button class="btn btn-secondary btn-sm float-left" onclick="volverFormularioPersonal('form-datos-personales','form-datos-financieros')">
            <i class="fa fa-arrow-left"></i> Atras
        </button>
        <h4 class="text-center">Ingreso de datos requeridos</h4>
    </div>
    <div class="card w-75 ms-auto me-auto mb-5">
        <div class="card-body">
            <form id="form-financiero" action="{{ route('guardar-datos-financieros') }}" method="post">
                <meta name="csrf-token-form-financial-data" content="{{ csrf_token() }}" />
                <p class="lead">Datos financieros (*)</p>
                <div class="row mb-2">
                    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <div class="form-group mb-2">
                            <input type="hidden" id="idProcesoHidden" name="idProcesoHidden">
                            <label for="">Ingresos Básicos</label>
                            <input type="number" id="ingresosMensuales" name="ingresosMensuales" class="form-control" placeholder="Digite ingresos" value="{{ old('ingresosMensuales') }}" onkeypress="return soloNumeros(event)">
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-ingresosMensuales">
                        </span>
                    </div>
                    <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                        <div class="form-group mb-2">
                            <label for="">Pagaduría</label>
                            <select id="pagaduriaTercero" name="pagaduriaTercero" class="form-select" value="{{ old('pagaduriaTercero') }}" onchange="cambiarPagaduria()"></select>
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-pagaduriaTercero">
                        </span>
                    </div>
                    <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                        <div class="form-group mb-2">
                            <label for="">Valor solicitado</label>
                            <input type="number" id="valorSolicitado" name="valorSolicitado" class="form-control" placeholder="Digite valor a solicitar" value="{{ old('valorSolicitado') }}" onkeypress="return soloNumeros(event)">                            
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-valorSolicitado">
                        </span>
                    </div>
                    <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                        <div class="form-group mb-2">
                            <label for="">Número cuotas</label>
                            <select name="numeroCuotas" id="numeroCuotas" class="form-select" value="{{ old('numeroCuotas') }}">
                                <option value="0">Elija número de cuotas...</option>
                            </select>
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-numeroCuotas">
                        </span>
                    </div>
                    <div class="text-center">
                        <button type="button" class="btn btn-primary" id="show-inputs-btn" onclick="mostrarInputs()">Continuar</button>
                    </div>
                </div><hr>
            </form>
            <div id="inputs-config">
            </div>
            <div id="cupoDisponible">
            </div>
            <button onclick="enviarDatosFinancieros(event)" class="btn btn-primary" id="btnSendValues" disabled>Calcular</button>
            <button onclick="editarEstadoProceso()" class="btn btn-primary" id="btnEditState">Siguiente</button>
        </div>
    </div>
</div>