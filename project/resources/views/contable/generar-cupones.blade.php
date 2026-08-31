<div class="container" style="min-height: 75vh;" id="divGeneracionCupon">
    <button class="btn btn-danger btn-sm float-left" onclick="cambiarVista('tabla')">
        <i class="fas fa-arrow-left"></i> Ver cupones
    </button>
    <h5 class="text-center"><strong>Generar Cupones Bancarios <i class="fa fa-ticket"></i></strong></h5>
    <!-- Content Form Cupones -->
    <div class="my-3">
        <div class="form-group input-group-sm">
            <select name="IdBancaria" class="form-select input-sm col-12 col-sm-12 col-lg-12">
                <option value="">-- Seleccione Banco --</option>
                    @if(!empty($bancos))
                        @foreach ($bancos as $keyBanco => $dataBanco)
                            <option value="{{ $dataBanco->IdBanco }}">{{ $dataBanco->Banco }}</option>
                        @endforeach
                    @endif
                ?>
            </select>
        </div><hr>
        <div class="my-3">
            <form id="form-tercero-cupon" onsubmit="return false;" class="row">
                <h6 class="fw-900">Datos de la Operación <i class="fas fa-tasks"></i></h6>
                <div class="row">
                    <div class="form-group input-group-sm col-lg-3 col-md-3 col-sm-6 col-xs-12 my-1">
                        <label for="IdOperacion" class="col-12 f-bolder f-small">No. OP</label>
                        <input placeholder="No. Operación..." name="IdOperacion" class="form-control" onkeyup="this.value < 0 ? this.value = 0 : '' " type="number">
                    </div>
                    <div class="form-group input-group-sm col-lg-3 col-md-3 col-sm-6 col-xs-12 my-1">
                        <label for="DocumentoTercero" class="col-12 f-bolder f-small">No. Identificación Tercero</label>
                        <input placeholder="No. Identificación..." name="DocumentoTercero" class="form-control" onkeyup="this.value < 0 ? this.value = 0 : '' " type="number" disabled readonly>
                    </div>
                    <div class="form-group input-group-sm col-lg-6 col-md-6 col-sm-12 col-xs-12 my-1">
                        <label for="NombreTercero" class="col-12 f-bolder f-small">Nombre(s) Apellido(s) / Razón Social</label>
                        <input placeholder="Esperando..." name="NombreTercero" class="form-control" type="text" disabled readonly>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group input-group-sm col-12 col-lg-4 my-1">
                        <label for="ValorCupon" class="col-12 f-bolder f-small">Valor (<i class="fa fa-dollar-sign"></i>)</label>
                        <input name="ValorCupon" data-type="currency" class="form-control" type="text" placeholder="Valor operación..." onkeypress="return soloNumeros(event)">
                    </div>
                    <div class="form-group input-group-sm col-12 col-lg-4 my-1">
                        <label for="FechaLimiteCupon" class="col-12 f-bolder f-small">Fecha Límite Cupón</label>
                        <input name="FechaLimiteCupon" class="form-control" type="text" placeholder="Fecha límite para el cupón..." onkeypress="return noStrangeCharacters(event)">
                    </div>
                    <div class="col-12 col-lg-12 my-3 text-center">
                        <center>
                            <button id="btnAgregarTercero" title="Generar Archivo Plano" data-toggle="tooltip" class="btn btn-xs btn-primary  f-small fw-900">Agregar <i class="fa fa-plus"></i></button>
                        </center>
                    </div>
                </div>
            </form>
        </div><hr>
        <div class="my-3">
            <div class="">
                <button id="btnGenerarArchivo" class="btn btn-sm btn-primary float-end">
                    Generar Archivo Plano &nbsp;<i class="fas fa-file-download"></i>
                </button>
                <h6 class="text-center">Terceros a Generar <i class="fa fa-tasks"></i></h6>
            </div><hr>
            <div class="row">
                <div class="table-responsive">
                    <table id="TableContent" class="table table-condensed table-striped table-hover f-small text-center">
                        <thead>
                            <tr>
                                <th>No. Operación</th>
                                <th>No. Tercero</th>
                                <th>Tercero</th>
                                <th>Valor (<i class="fa fa-dollar-sign"></i>)</th>
                                <th>Fecha Límite Pago</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>