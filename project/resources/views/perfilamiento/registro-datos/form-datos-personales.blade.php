<div id="form-datos-personales" class="forms-datos-tercero">
    <div class="card w-75 ms-auto me-auto mb-5">
        <div class="card-body">
            <form id="form-datos" action="{{ route('guardar-datos-personales') }}" method="post">
                <meta name="csrf-token-form-personal-data" content="{{ csrf_token() }}" />
                <p class="lead">Datos personales (*)</p>
                <div class="row mb-2">
                    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="tipoDocumento">Tipo Documento</label>
                            <select id="tipoDocumento" name="tipoDocumento" class="form-select" value="{{ old('tipoDocumento') }}"></select>
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-tipoDocumento">  
                        </span>
                    </div>
                    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="documento">No Documento</label>
                            <input type="text" id="documentoTercero" name="documentoTercero" class="form-control" value="{{ old('documentoTercero') }}" onblur="validarDocumento(this.value)" placeholder="Ingresa número de documento" onkeypress="return noStrangeCharacters(event)">
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-documentoTercero">
                        </span>
                    </div>
                    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="nombres">Nombres</label>
                            <input type="hidden" id="idTercero" name="idTercero">
                            <input type="text" id="nombres" name="nombres" class="form-control" value="{{ old('nombres') }}" placeholder="Ingresa nombre" onkeyup="toUpperValues(this)" onkeypress="return noStrangeCharacters(event)">
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-nombres">                                
                        </span>
                    </div>
                    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                        <div class="form-group">
                            <label for="apellidos">Apellidos</label>
                            <input type="text" id="apellidos" name="apellidos" class="form-control" value="{{ old('apellidos') }}" placeholder="Ingresa apellido" onkeyup="toUpperValues(this)" onkeypress="return noStrangeCharacters(event)">
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-apellidos">  
                        </span>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                        <div class="form-group">
                            <label for="fechaExpedicion">Fecha Expedición</label>
                            <input type="text" id="fechaExpedicion" name="fechaExpedicion" class="form-control" value="{{ old('fechaExpedicion') }}" placeholder="Ingresa fecha expedición de documento" autocomplete="off" onkeypress="return noStrangeCharacters(event)">
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-fechaExpedicion">
                        </span>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                        <div class="form-group">
                            <label for="lugarExpedicion">Lugar Expedición</label>
                            <input type="text" id="lugarExpedicion" name="lugarExpedicion" class="form-control" value="{{ old('lugarExpedicion') }}" placeholder="Ingresa ciudad de expedición" onkeyup="toUpperValues(this)" onkeypress="return noStrangeCharacters(event)">
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-lugarExpedicion">
                        </span>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                        <div class="form-group">
                            <label for="fechaNacimiento">Fecha Nacimiento</label>
                            <input type="text" id="fechaNacimiento" name="fechaNacimiento" class="form-control" value="{{ old('fechaNacimiento') }}" placeholder="Ingresa fecha de nacimiento" autocomplete="off" onkeypress="return noStrangeCharacters(event)">
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-fechaNacimiento">
                        </span>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                        <div class="form-group">
                            <label for="telefonoTercero">Teléfono</label>
                            <input type="text" id="telefonoTercero" name="telefonoTercero" class="form-control" value="{{ old('telefonoTercero') }}" placeholder="Ingresa teléfono" onkeypress="return soloNumeros(event)">
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-telefonoTercero">
                        </span>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                        <div class="form-group">
                            <label for="emailTercero">Correo electrónico</label>
                            <input type="email" id="emailTercero" name="emailTercero" class="form-control" value="{{ old('emailTercero') }}" placeholder="Ingresa correo electrónico" onkeypress="return noStrangeCharacters(event)">
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-emailTercero">
                        </span>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
                        <div class="form-group">
                            <label for="direccionTercero">Dirección</label>
                            <input type="text" id="direccionTercero" name="direccionTercero" class="form-control" value="{{ old('direccionTercero') }}" placeholder="Ingresa dirección" onkeyup="toUpperValues(this)" onkeypress="return noStrangeCharacters(event)">
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-direccionTercero">
                        </span>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <div class="form-group">
                            <label for="departamentoResidencia">Departamento</label>
                            <select id="departamentoResidencia" name="departamentoResidencia" onchange="mostrarCiudades(this.value)" class="form-select" value="{{ old('departamentoResidencia') }}"></select>
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-departamentoResidencia">
                        </span>
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <div class="form-group">
                            <label for="ciudadResidencia">Ciudad</label>
                            <select id="ciudadResidencia" name="ciudadResidencia" class="form-select" value="{{ old('ciudadResidencia') }}"></select>
                        </div>
                        <span class="invalid-feedback" role="alert" id="error-ciudadResidencia">
                        </span>
                    </div>
                </div><hr>
            </form>
            <div class="d-grid">
                <button type="submit" onclick="enviarDatosPersonales(event)" class="btn btn-primary">Siguiente</button>
            </div>
        </div>
    </div>
</div>