<div id="calculate-credit">
    <div class="card">
        <div class="card-body">
            <div class="container">
                <button class="btn btn-secondary btn-sm float-left" onclick="backForm()">
                    <i class="fa fa-arrow-left"></i> Atras
                </button>
                <h5 class="text-center">Calcular Cupo Disponible</h5>
            </div><hr>
            {{-- <form action="{{ route('calculate') }}" method="post" id="form-calculate"> --}}
                <meta name="csrf-token-form-calculate" content="{{ csrf_token() }}" />
                <div class="row">
                    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="ingresosMensuales">Salario básico</label>
                            <input type="text" id="ingresosMensuales" name="ingresosMensuales" class="form-control" value="{{ old('ingresosMensuales') }}" placeholder="Digite ingresos mensuales" disabled onkeypress="return soloNumeros(event)">
                            <input type="hidden" id="idProceso" name="idProceso">                            
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="ingresosExtras">Otros ingresos (referentes a la pagaduria)</label>
                            <div class="input-group mb-3">
                                <input type="text" id="ingresosExtras" name="ingresosExtras" class="form-control" value="{{ old('ingresosExtras') }}" placeholder="Digite suma total de ingresos extras" onkeypress="return soloNumeros(event)">
                                <span class="input-group-text"><i class="fa fa-question text-danger"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="descuentosLey">Descuentos de ley</label>
                            <div class="input-group mb-3">
                                <input type="text" id="descuentosLey" name="descuentosLey" class="form-control" value="{{ old('descuentosLey') }}" placeholder="Digite total descuentos de ley" onkeypress="return soloNumeros(event)">
                                <span class="input-group-text"><i class="fa fa-question text-danger"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="deducciones">Otras Deducciones</label>
                            <div class="input-group mb-3">
                                <input type="text" id="deducciones" name="deducciones" class="form-control" value="{{ old('deducciones') }}" placeholder="Digite el total de deducciones" onkeypress="return soloNumeros(event)">
                                <span class="input-group-text"><i class="fa fa-question text-danger"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col"></div>
                    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="cupoDisponible">Cupo disponible</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fa fa-dollar"></i></span>
                                <input type="text" id="cupoDisponible" name="cupoDisponible" class="form-control" value="{{ old('cupoDisponible') }}" disabled onkeypress="return soloNumeros(event)">
                            </div>
                        </div>
                    </div>
                    <div class="col"></div>
                </div>
                <br>
                <div class="container">
                    <div class="d-grid w-50 me-auto ms-auto">
                        <button type="button" class="btn btn-primary" id="calculateButton" onclick="calculate()">Calcular</button>
                        {{-- <div class="d-grid w-50 me-auto ms-auto"> --}}<br>
                        <button type="button" class="btn btn-primary" onclick="startProcess()" id="send-petition">Enviar solicitud</button>
                        {{-- </div> --}}
                    </div>
                </div>
            {{-- </form> --}}
        </div>
    </div>
</div>