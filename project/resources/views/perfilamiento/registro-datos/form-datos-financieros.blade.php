<section id="form-datos-financieros" class="ui-card ui-paso-card d-none" aria-labelledby="titulo-paso-2">
    <div class="ui-paso-cab">
        <h2 class="ui-titulo ui-titulo-paso" id="titulo-paso-2" tabindex="-1">Datos financieros</h2>
        <p class="ui-descripcion">Pagaduría, ingresos, deducciones y monto solicitado</p>
    </div>
    <div id="avisoFinanciero" aria-live="polite"></div>
    <form id="form-financiero" novalidate onsubmit="return false">
        <meta name="csrf-token-form-financial-data" content="{{ csrf_token() }}" />
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="pagaduriaTercero" class="form-label">Pagaduría</label>
                <select id="pagaduriaTercero" name="pagaduriaTercero" class="form-select" aria-describedby="error-pagaduriaTercero" onchange="cambiarPagaduria()">
                    <option value="">Selecciona una pagaduría</option>
                </select>
                <span class="invalid-feedback" role="alert" id="error-pagaduriaTercero"></span>
            </div>
            <div class="col-12 col-md-6">
                <label for="ingresosMensuales" class="form-label">Ingresos básicos</label>
                <div class="input-group has-validation">
                    <span class="input-group-text">$</span>
                    <input type="text" inputmode="numeric" autocomplete="off" id="ingresosMensuales" name="ingresosMensuales" class="form-control" aria-describedby="error-ingresosMensuales" oninput="formatearInputMoneda(this);marcarError('ingresosMensuales','')" onchange="cargarRubros()">
                    <span class="invalid-feedback" role="alert" id="error-ingresosMensuales"></span>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <label for="valorSolicitado" class="form-label">Valor solicitado</label>
                <div class="input-group has-validation">
                    <span class="input-group-text">$</span>
                    <input type="text" inputmode="numeric" autocomplete="off" id="valorSolicitado" name="valorSolicitado" class="form-control" aria-describedby="error-valorSolicitado alerta-monto" oninput="editarMontoSolicitado(this)">
                    <span class="invalid-feedback" role="alert" id="error-valorSolicitado"></span>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <label for="numeroCuotas" class="form-label">Plazo (número de cuotas)</label>
                <select name="numeroCuotas" id="numeroCuotas" class="form-select" aria-describedby="ayuda-numeroCuotas error-numeroCuotas" disabled onchange="marcarError('numeroCuotas','')">
                    <option value="0">Selecciona una pagaduría</option>
                </select>
                <span class="invalid-feedback" role="alert" id="error-numeroCuotas"></span>
                <span class="ui-campo-ayuda" id="ayuda-numeroCuotas"></span>
            </div>
            <div class="col-12" id="alerta-monto" role="alert"></div>
        </div>
    </form>
    <div id="inputs-config"></div>
    <div id="cupoDisponible" class="mt-4"></div>
    <div class="ui-asistente-acciones">
        <button type="button" class="btn ui-btn ui-btn-sec" onclick="irAPaso(1)">
            <i class="fas fa-arrow-left" aria-hidden="true"></i><span>Atrás</span>
        </button>
        <div class="ui-acciones">
            <button type="button" onclick="calcularFinancieros(this)" class="btn btn-primary ui-btn" id="btnSendValues">
                <i class="fas fa-calculator" aria-hidden="true"></i><span>Calcular</span>
            </button>
            <button type="button" onclick="confirmarFinancieros(this)" class="btn btn-primary ui-btn d-none" id="btnEditState">
                <span>Confirmar y continuar</span><i class="fas fa-arrow-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</section>
