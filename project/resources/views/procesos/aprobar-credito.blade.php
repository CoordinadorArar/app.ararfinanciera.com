<div id="aprobacion-creditos" class="procesos-div">
    <button class="btn btn-sm btn-danger" id="backButton" onclick="backToTable('aprobacion-creditos')" title="Volver a vista de procesos">
        <i class="fas fa-arrow-left"></i> Volver
    </button>
    <h4 class="text-center">Aprobación de créditos</h4><hr>
    <div class="row">
        <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
            <div class="card mb-2">
                <div class="card-body">
                    <h5 class="title-credit text-center">Datos Personales</h5>
                    <div id="personal-data">
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="title-credit text-center">Datos Centrales de Riesgo</h5>
                    <div id="transunion-data">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
            <div class="card mb-2">
                <div class="card-body">
                    <h5 class="title-credit text-center">Datos Financieros y del crédito</h5>
                    <div id="financial-data" class="mb-2">
                    </div>
                    <table class="" id="tablaInformacion">
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="title-credit text-center">Documentos adjuntos</h5>
                    <div id="document-data">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="text-center mt-2 mb-2">
        <button class="btn btn-primary btn-credit" id="btnAprobarCredito" title="Aprobar crédito" data-toggle="tooltip">
            <i class="fas fa-thumbs-up"></i>
        </button>
        <button class="btn btn-danger btn-credit" id="btnRechazarCredito" title="Rechazar crédito" data-toggle="tooltip">
            <i class="fas fa-thumbs-down"></i>
        </button>
        <button class="btn btn-warning btn-credit" id="btnEditarCredito" title="Editar datos crédito" data-toggle="tooltip">
            <i class="fas fa-pencil-alt"></i>
        </button>
        <button class="btn btn-warning btn-credit" id="btnDescargarHistorial" title="Descargar documento estudio de crédito" data-toggle="tooltip">
            <i class="fas fa-download"></i>
        </button>
    </div>
</div>
<div class="modal fade" id="modalCuerpoCorreo" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <div id="cuerpoCorreo">
                    <div class="form-group mb-2">
                        <label for="">Asunto</label>
                        <input type="hidden" name="idProcesoCorreo" id="idProcesoCorreo">
                        <input type="hidden" name="accionCorreo" id="accionCorreo">
                        <input type="text" name="asuntoCorreo" id="asuntoCorreo" class="form-control">
                    </div>
                    <div class="form-group mb-2">
                        <label for="">Texto del cuerpo</label>
                        <textarea type="text" name="textoCorreo" id="textoCorreo" class="form-control" cols="50" rows="5"></textarea>
                    </div>
                    <div class="form-group mb-2 d-none">
                        <label for="">Motivos rechazo</label>
                        <select name="motivosRechazo" id="motivosRechazo" class="form-select">
                            <option value="0">Selecciona un motivo...</option>
                            <option value="1">No tiene cupo</option>
                            <option value="2">Mal hábito de pago</option>
                            <option value="3">Compra cartera en proceso</option>
                            <option value="4">No cumple política de antigüedad</option>
                            <option value="5">Proceso jurídico en curso</option>
                            <option value="6">No cumple política compra de cartera</option>
                        </select>
                    </div>
                    <div class="form-group text-center">
                        <button type="submit" class="btn btn-primary" onclick="enviarEmail(event)">Enviar <i class="fas fa-envelope"></i></button>
                        <!--<button type="button" class="btn btn-primary">Enviar <i class="fas fa-envelope"></i></button>-->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>