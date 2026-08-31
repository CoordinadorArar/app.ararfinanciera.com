<div id="form-tratamiento-datos" class="forms-datos-tercero">
    <div class="container">
        <button class="btn btn-secondary btn-sm float-left" onclick="volverFormularioPersonal('form-datos-financieros','form-tratamiento-datos')">
            <i class="fa fa-arrow-left"></i> Atras
        </button>
        <h4 class="text-center">Aprobación de tratamiento de datos</h4>
    </div>
    <div class="card w-75 ms-auto me-auto mb-5">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <div class="alert alert-primary" role="alert" onclick="prepararAprobacionDatos('email')">
                            <img src="{{ asset('images/checklist.png') }}" alt="checklist">
                            <h6>Enviar correo electrónico al usuario (Aprobación digital)</h6>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <div class="alert alert-primary" role="alert" onclick="prepararAprobacionDatos('pdf')">
                            <img src="{{ asset('images/pdf.png') }}" alt="pdf" class="p-3">
                            <h6>Adjuntar formato físico, firmado y escaneado</h6>
                        </div>
                    </div>
                </div>
                <hr>
                <div id="form-virtual-acept">
                    <div class="row">
                        
                    </div>
                </div>
                <div id="form-upload-file">
                    <div class="row">
                        <form id="form-file-tratamiento" action="{{ route('subir-archivo-tratamiento-datos') }}" method="post" enctype="multipart/form-data">
                            <meta name="csrf-token-form-approve-data" content="{{ csrf_token() }}" />
                            <button type="button" class="btn btn-primary" onclick="descargarPdf()">
                                Desacargar formato
                                <i class="fas fa-download"></i>
                            </button><hr>
                            <div class="form-group">
                                <label for="documento">Adjuntar formato escaneado</label>
                                <input type="file" class="form-control" id="fileTratamientoDatos" name="fileTratamientoDatos" accept=".pdf" required>
                            </div>
                            <span class="invalid-feedback" role="alert" id="error-fileTratamientoDatos">
                            </span>
                            <div class="d-grid mt-2">
                                <button type="submit" class="btn btn-primary" onclick="subirArchivoTratamientoDatos(event)">Cargar archivo</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="modalCorreoTratamiento" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <h4 class="text-center">Tratamiento de datos</h4>
                        <hr>
                        <p class="lead">A continuaci&oacuten se enviar&aacute un correo electr&oacutenico para que la persona acepte el tratamiento de sus datos personales</p>
                        <button class="btn btn-primary" onclick="enviarEmail()"><i class="fas fa-envelope"></i> Enviar</button>
                        <button class="btn btn-danger" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>