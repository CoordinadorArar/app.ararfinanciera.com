<div id="consulta-centrales" class="procesos-div">
    <button class="btn btn-sm btn-danger" id="backButton" onclick="backToTable('consulta-centrales')" title="Volver a vista de procesos">
        <i class="fas fa-arrow-left"></i> Volver
    </button>
	<h4 class="text-center">Consulta centrales de riesgo</h4><hr>
	<meta name="csrf-token-consulta-centrales" content="{{ csrf_token() }}" />
	<div class="" id="div-info-basica">
		<p class="text-center lead">INFORMACIÓN BÁSICA </p>
		<table class="table table-sm table striped" id="tabla-info-basica">
		</table>
	</div><hr>
	<div class="d-flex justify-content-around">
		<div class="col-lg-5 col-md-5">
			<p>*Todos los valores de la consulta están expresados en miles de pesos</p>
		</div>
		<div class="col-lg-7 col-md-7">
			<p>
				Se presenta reporte negativo cuando la(s) persona(s) naturales y juridicas efectivamente se encuentran en mora en sus cuotas u obligaciones.
				Se presenta reporte positivo cuando la(s) persona(s) naturales o juridicas están al día en sus obligaciones.				
			</p>
		</div>
	</div>
	<div class="" id="div-resumen-endeudamiento">
		<p class="text-center lead">RESUMEN ENDEUDAMIENTO </p>
		<table class="table table-sm table striped" id="tabla-resumen-endeudamiento">
			<thead class="text-center">
				<tr>
					<th colspan="11">RESUMEN OBLIGACIONES (COMO PRINCIPAL)</th>
				</tr>
				<tr>
					<th rowspan="2">OBLIGACIONES</th>
					<th colspan="3">TOTALES</th>
					<th colspan="3">OBLIGACIONES AL DÍA</th>
					<th colspan="4">OBLIGACIONES EN MORA</th>
				</tr>
				<tr>
					<th>CANT</th>
					<th>SALDO TOTAL</th>
					<th class="border-r">PADE</th>
					<th>CANT</th>
					<th>SALDO TOTAL</th>
					<th class="border-r">CUOTA</th>
					<th>CANT</th>
					<th>SALDO TOTAL</th>
					<th>CUOTA</th>
					<th>VALOR EN MORA</th>
				</tr>
			</thead>
			<tbody id="resumen-endeudamiento-body"></tbody>
		</table>
	</div>
	<div id="div-informe-detallado">
		<h4 class="text-center">INFORME DETALLADO</h4><br>
		<!--<p class="text-center lead">INFORMACIÓN DE CUENTAS</p>
		<table class="table table-sm table striped" id="tabla-informe-detallado">
			<thead>
				<tr>
					<th>FECHA CORTE</th>
					<th>TIPO CONTRATO</th>
					<th>No CUENTA</th>
					<th>ESTADO</th>
					<th>TIPO ENT</th>
					<th>ENTIDAD</th>
					<th>CIUDAD</th>
					<th>SUCURSAL</th>
					<th>FECHA APERTURA</th>
					<th>CUPO SOBREGIRO</th>
					<th>DIAS AUTOR</th>
					<th>FECHA PERMANENCIA</th>
					<th>CHEQ DEVUELTOS ULTIMO MES</th>
				</tr>
			</thead>
			<tbody id="informe-detallado-body"></tbody>
		</table>-->
		<p class="lead text-center">INFORMACIÓN ENDEUDAMIENTO EN SECTORES FINANCIERO, ASEGURADOR Y SOLIDARIO</p>
		<div class="table-responsive">
			<table class="table table-sm table-striped" id="tabla-informe-sectores">
				<thead>
					<tr>
						<th colspan="2">FECHA CORTE</th>
						<th>MODA</th>
						<th>No. OBLIG</th>
						<th>TIPO ENT</th>
						<th>NOMBRE ENTIDAD</th>
						<th>CIUDAD</th>
						<th>CAL</th>
						<th>MRC</th>
						<th>TIPO GAR</th>
						<th>F INICIO</th>
						<th style="width:10%;">
							<table>
								<tbody>
									<tr><th colspan="3">No CUOTAS</th></tr>
									<tr><th style="width:33%;">PAC </th><th style="width:33%;">PAG </th><th style="width:33%;">MOR </th></tr>
								</tbody>
							</table>
						</th>
						<th>CUPO APROB- VLR INIC</th>
						<th>PAGO MINIM - VLR CUOTA</th>
						<th>SIT OBLIG</th>
						<th>NATU REES</th>
						<th>No. REE</th>
						<th>TIP PAG</th>
						<th>F PAGO F EXTIN</th>
					</tr>
					<tr>
						<th>TIPO CON</th>
						<th>PADE</th>
						<th>LCRE</th>
						<th>EST. CONTR</th>
						<th>CLF</th>
						<th>ORIGEN CARTERA</th>
						<th>SUCURSAL</th>
						<th>EST TITU</th>
						<th>CLS</th>
						<th>COB GAR</th>
						<th>F TERM</th>
						<th>PERM</th>
						<th>CUPO UTILI SALDO CORT</th>
						<th></th>		
						<th>VALOR MORA</th>
						<th>REES</th>
						<th>MOR MAX</th>
						<th>MOD EXT</th>
						<th>F PERMAN</th>
					</tr>
				</thead>
				<tbody id="informe-detallado-body"></tbody>
			</table>
		</div>
	</div>
	<div class="" id="botones-aprobacion">
		<div class="text-center">
			<button class="btn btn-primary btn-central-aprobar" id="" title="Aprobar y continuar con el proceso"><i class="fas fa-check"></i></button>
			<button class="btn btn-danger btn-central-aprobar" id="" title="Canclar proceso"><i class="fas fa-ban"></i></button>
		</div>
	</div>
</div>