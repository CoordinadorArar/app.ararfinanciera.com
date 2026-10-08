<section id="form-tratamiento-datos" class="ui-card ui-paso-card d-none" aria-labelledby="titulo-paso-3">
    <div class="ui-paso-cab">
        <h2 class="ui-titulo ui-titulo-paso" id="titulo-paso-3" tabindex="-1">Autorización de tratamiento de datos</h2>
        <p class="ui-descripcion">Elige cómo autorizará el cliente el uso de sus datos personales</p>
    </div>
    <meta name="csrf-token-form-approve-data" content="{{ csrf_token() }}" />
    <fieldset class="ui-seccion">
        <legend class="ui-seccion-titulo">¿Cómo autorizará el cliente?</legend>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <input type="radio" class="btn-check" name="metodoAutorizacion" id="opcionCorreo" value="email" autocomplete="off" onchange="elegirMetodo('email')">
                <label class="ui-opcion" for="opcionCorreo">
                    <i class="fas fa-envelope-open-text ui-opcion-icono" aria-hidden="true"></i>
                    <span>
                        <span class="ui-opcion-titulo">Enlace por correo</span>
                        <span class="ui-opcion-desc">El cliente acepta desde un enlace enviado a su correo.</span>
                        <span id="badgeCorreo"></span>
                    </span>
                    <i class="fas fa-circle-check ui-opcion-check" aria-hidden="true"></i>
                </label>
            </div>
            <div class="col-12 col-md-6">
                <input type="radio" class="btn-check" name="metodoAutorizacion" id="opcionDocumento" value="pdf" autocomplete="off" onchange="elegirMetodo('pdf')">
                <label class="ui-opcion" for="opcionDocumento">
                    <i class="fas fa-file-signature ui-opcion-icono" aria-hidden="true"></i>
                    <span>
                        <span class="ui-opcion-titulo">Documento firmado</span>
                        <span class="ui-opcion-desc">Sube el formato impreso, firmado y escaneado (PDF).</span>
                        <span id="badgeDocumento"></span>
                    </span>
                    <i class="fas fa-circle-check ui-opcion-check" aria-hidden="true"></i>
                </label>
            </div>
        </div>
    </fieldset>
    <div>
        <div id="panelCorreo" class="ui-panel-opcion d-none"><div id="alertasCorreo" aria-live="polite"></div><div id="destinoCorreo"></div></div>
        <div id="panelDocumento" class="ui-panel-opcion d-none">
            <a class="btn ui-btn ui-btn-sec mb-3" id="enlaceFormato" href="#" download>
                <i class="fas fa-download" aria-hidden="true"></i><span>Descargar formato</span>
            </a>
            <form id="form-file-tratamiento" novalidate onsubmit="subirArchivoTratamientoDatos(event)">
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label for="fileTratamientoDatos" class="form-label">Formato firmado</label>
                        <input type="file" class="form-control" id="fileTratamientoDatos" name="fileTratamientoDatos" accept="application/pdf" aria-describedby="error-fileTratamientoDatos ayuda-fileTratamientoDatos" onchange="marcarError('fileTratamientoDatos','')">
                        <span class="invalid-feedback" role="alert" id="error-fileTratamientoDatos"></span>
                        <span class="ui-campo-ayuda" id="ayuda-fileTratamientoDatos">PDF, máximo 2 MB</span>
                    </div>
                    <div class="col-12 col-md-4 ui-campo-boton">
                        <button type="submit" class="btn btn-primary ui-btn w-100" id="btnCargarDocumento">
                            <i class="fas fa-upload" aria-hidden="true"></i><span>Cargar documento</span>
                        </button>
                    </div>
                </div>
            </form>
            <div id="estadoDocumento" aria-live="polite"></div>
        </div>
    </div>
    <div class="ui-asistente-acciones">
        <button type="button" class="btn ui-btn ui-btn-sec" onclick="irAPaso(2)">
            <i class="fas fa-arrow-left" aria-hidden="true"></i><span>Atrás</span>
        </button>
        <div class="ui-acciones">
            <span class="ui-campo-ayuda m-0" id="ayudaFinalizar">Envía el correo o carga el documento para finalizar.</span>
            <button type="button" class="btn btn-primary ui-btn" id="btnFinalizar" onclick="finalizarRegistro()" aria-describedby="ayudaFinalizar" disabled>
                <span>Finalizar registro</span><i class="fas fa-check" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</section>
