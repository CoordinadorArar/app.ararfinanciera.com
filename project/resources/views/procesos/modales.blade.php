<div class="modal fade proc-modal" id="modalRechazo" tabindex="-1" aria-labelledby="tituloRechazo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <form class="modal-content ui-modal" novalidate onsubmit="confirmarRechazo(event)">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title" id="tituloRechazo">Rechazar proceso</h2>
                    <p class="ui-descripcion" id="contextoRechazo"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="errorRechazo"></div>
                <div class="mb-3">
                    <label for="motivoRechazo" class="form-label">Motivo <span aria-hidden="true">*</span></label>
                    <select id="motivoRechazo" class="form-select" required aria-describedby="error-motivoRechazo" onchange="marcarError(this.id,'');actualizarObligatoria()"></select>
                    <span class="invalid-feedback" role="alert" id="error-motivoRechazo"></span>
                </div>
                <div class="mb-3">
                    <label for="observacionRechazo" class="form-label">Observación <span class="fw-normal" id="obligatoriaRechazo"></span></label>
                    <textarea id="observacionRechazo" class="form-control" rows="3" maxlength="500" aria-describedby="error-observacionRechazo ayudaRechazo contador-observacionRechazo" oninput="contar(this.id);marcarError(this.id,'')"></textarea>
                    <span class="invalid-feedback" role="alert" id="error-observacionRechazo"></span>
                    <div class="d-flex justify-content-between gap-2">
                        <span class="ui-campo-ayuda" id="ayudaRechazo"></span>
                        <span class="ui-campo-ayuda" id="contador-observacionRechazo" aria-live="polite">0 / 500</span>
                    </div>
                </div>
                <div class="ui-alerta ui-alerta-adv mb-0" id="alertaRechazo">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <span class="ui-alerta-texto">Esta acción cierra el proceso y no se puede deshacer.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn ui-btn ui-btn-sec" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger ui-btn" id="btnRechazar"><i class="fas fa-ban" aria-hidden="true"></i><span id="textoBtnRechazar">Rechazar proceso</span></button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade proc-modal" id="modalCentrales" tabindex="-1" aria-labelledby="tituloCentrales" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <form class="modal-content ui-modal" novalidate onsubmit="confirmarCentrales(event)">
            <div class="modal-header">
                <h2 class="modal-title" id="tituloCentrales">¿Aprobar centrales de riesgo?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="errorCentrales"></div>
                <p class="ui-descripcion mt-0 mb-3">El proceso pasará a Documentos de soporte.</p>
                <label for="observacionCentrales" class="form-label">Observación <span class="fw-normal">(opcional)</span></label>
                <textarea id="observacionCentrales" class="form-control" rows="3" maxlength="500" aria-describedby="contador-observacionCentrales" oninput="contar(this.id)"></textarea>
                <span class="ui-campo-ayuda text-end" id="contador-observacionCentrales" aria-live="polite">0 / 500</span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn ui-btn ui-btn-sec" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary ui-btn" id="btnConfirmarCentrales"><i class="fas fa-check" aria-hidden="true"></i><span>Aprobar centrales</span></button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade proc-modal" id="modalAprobar" tabindex="-1" aria-labelledby="tituloAprobar" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
        <form class="modal-content ui-modal" novalidate onsubmit="confirmarAprobacion(event)">
            <div class="modal-header">
                <h2 class="modal-title" id="tituloAprobar">Aprobar crédito y notificar</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="errorAprobar"></div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="correoPara" class="form-label">Para</label>
                        <input type="text" id="correoPara" class="form-control" readonly>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="correoAsunto" class="form-label">Asunto</label>
                        <input type="text" id="correoAsunto" class="form-control" readonly>
                    </div>
                    <div class="col-12">
                        <label for="correoMensaje" class="form-label">Mensaje</label>
                        <textarea id="correoMensaje" class="form-control" rows="8" maxlength="2000" aria-describedby="error-correoMensaje contador-correoMensaje" oninput="contar(this.id);marcarError(this.id,'')"></textarea>
                        <span class="invalid-feedback" role="alert" id="error-correoMensaje"></span>
                        <span class="ui-campo-ayuda text-end" id="contador-correoMensaje" aria-live="polite">0 / 2000</span>
                    </div>
                    <div class="col-12">
                        <label for="observacionAprobar" class="form-label">Observación para el historial <span class="fw-normal">(opcional)</span></label>
                        <textarea id="observacionAprobar" class="form-control" rows="2" maxlength="500" aria-describedby="contador-observacionAprobar" oninput="contar(this.id)"></textarea>
                        <span class="ui-campo-ayuda text-end" id="contador-observacionAprobar" aria-live="polite">0 / 500</span>
                    </div>
                    <div class="col-12">
                        <h3 class="ui-seccion-titulo">Condiciones aprobadas</h3>
                        <div id="resumenAprobar"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn ui-btn ui-btn-sec" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary ui-btn" id="btnConfirmarAprobar"><i class="fas fa-paper-plane" aria-hidden="true"></i><span>Aprobar y enviar correo</span></button>
            </div>
        </form>
    </div>
</div>
