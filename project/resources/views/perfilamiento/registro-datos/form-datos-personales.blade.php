<section id="form-datos-personales" class="ui-card ui-paso-card d-none" aria-labelledby="titulo-paso-1">
    <div class="ui-paso-cab">
        <h2 class="ui-titulo ui-titulo-paso" id="titulo-paso-1" tabindex="-1">Datos personales</h2>
        <p class="ui-descripcion">Identificación, datos básicos y residencia del cliente</p>
    </div>
    <form id="form-datos" novalidate onsubmit="enviarDatosPersonales(event)">
        <meta name="csrf-token-form-personal-data" content="{{ csrf_token() }}" />
        <input type="hidden" id="idTercero" name="idTercero">
        <div id="alertaPersonales"></div>
        <fieldset class="ui-seccion">
            <legend class="ui-seccion-titulo">Identificación</legend>
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="tipoDocumento" class="form-label">Tipo de documento</label>
                    <select id="tipoDocumento" name="tipoDocumento" class="form-select" aria-describedby="error-tipoDocumento">
                        <option value="">Selecciona el tipo de documento</option>
                    </select>
                    <span class="invalid-feedback" role="alert" id="error-tipoDocumento"></span>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="documentoTercero" class="form-label">Número de documento</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text" id="indicadorDocumento"><i class="fas fa-id-card" aria-hidden="true"></i></span>
                        <input type="text" id="documentoTercero" name="documentoTercero" class="form-control" inputmode="numeric" autocomplete="off" maxlength="15" aria-describedby="error-documentoTercero">
                        <span class="invalid-feedback" role="alert" id="error-documentoTercero"></span>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="fechaExpedicion" class="form-label">Fecha de expedición</label>
                    <input type="text" id="fechaExpedicion" name="fechaExpedicion" class="form-control campo-fecha" inputmode="numeric" maxlength="10" placeholder="dd/mm/aaaa" autocomplete="off" aria-describedby="error-fechaExpedicion">
                    <span class="invalid-feedback" role="alert" id="error-fechaExpedicion"></span>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="lugarExpedicion" class="form-label">Lugar de expedición</label>
                    <input type="text" id="lugarExpedicion" name="lugarExpedicion" class="form-control" maxlength="70" autocomplete="off" data-mayus aria-describedby="error-lugarExpedicion">
                    <span class="invalid-feedback" role="alert" id="error-lugarExpedicion"></span>
                </div>
            </div>
        </fieldset>
        <fieldset class="ui-seccion">
            <legend class="ui-seccion-titulo">Datos básicos</legend>
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="nombres" class="form-label">Nombres</label>
                    <input type="text" id="nombres" name="nombres" class="form-control" maxlength="40" autocomplete="off" data-mayus aria-describedby="error-nombres">
                    <span class="invalid-feedback" role="alert" id="error-nombres"></span>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="apellidos" class="form-label">Apellidos</label>
                    <input type="text" id="apellidos" name="apellidos" class="form-control" maxlength="40" autocomplete="off" data-mayus aria-describedby="error-apellidos">
                    <span class="invalid-feedback" role="alert" id="error-apellidos"></span>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="fechaNacimiento" class="form-label">Fecha de nacimiento</label>
                    <input type="text" id="fechaNacimiento" name="fechaNacimiento" class="form-control campo-fecha" inputmode="numeric" maxlength="10" placeholder="dd/mm/aaaa" autocomplete="off" aria-describedby="error-fechaNacimiento ayuda-fechaNacimiento">
                    <span class="invalid-feedback" role="alert" id="error-fechaNacimiento"></span>
                    <span class="ui-campo-ayuda" id="ayuda-fechaNacimiento"></span>
                </div>
            </div>
        </fieldset>
        <fieldset class="ui-seccion">
            <legend class="ui-seccion-titulo">Contacto y residencia</legend>
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="telefonoTercero" class="form-label">Celular o teléfono</label>
                    <input type="tel" id="telefonoTercero" name="telefonoTercero" class="form-control" inputmode="tel" maxlength="15" autocomplete="off" aria-describedby="error-telefonoTercero">
                    <span class="invalid-feedback" role="alert" id="error-telefonoTercero"></span>
                </div>
                <div class="col-12 col-md-6 col-lg-8">
                    <label for="emailTercero" class="form-label">Correo electrónico</label>
                    <input type="email" id="emailTercero" name="emailTercero" class="form-control" maxlength="100" autocomplete="off" aria-describedby="error-emailTercero">
                    <span class="invalid-feedback" role="alert" id="error-emailTercero"></span>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="departamentoResidencia" class="form-label">Departamento</label>
                    <select id="departamentoResidencia" name="departamentoResidencia" class="form-select" aria-describedby="error-departamentoResidencia">
                        <option value="">Selecciona un departamento</option>
                    </select>
                    <span class="invalid-feedback" role="alert" id="error-departamentoResidencia"></span>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="ciudadResidencia" class="form-label">Ciudad</label>
                    <select id="ciudadResidencia" name="ciudadResidencia" class="form-select" aria-describedby="error-ciudadResidencia" disabled>
                        <option value="">Selecciona un departamento</option>
                    </select>
                    <span class="invalid-feedback" role="alert" id="error-ciudadResidencia"></span>
                </div>
                <div class="col-12 col-lg-4">
                    <label for="direccionTercero" class="form-label">Dirección</label>
                    <input type="text" id="direccionTercero" name="direccionTercero" class="form-control" maxlength="70" autocomplete="off" data-mayus aria-describedby="error-direccionTercero">
                    <span class="invalid-feedback" role="alert" id="error-direccionTercero"></span>
                </div>
            </div>
        </fieldset>
        <div class="ui-asistente-acciones">
            <span></span>
            <div class="ui-acciones">
                <button type="submit" class="btn btn-primary ui-btn" id="btnGuardarPersonales">
                    <span>Guardar y continuar</span><i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </form>
</section>
