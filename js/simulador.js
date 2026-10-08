window.onload = function(){
    $('#fechaEdad').datetimepicker({
        format:'d/m/Y',
        timepicker:false,
        datepicker:true,
        maxDate:0,
        scrollInput:false
    });
    cargarPagaduriasSimulador();
}
const tokenSimulador = function(){
    return $('meta[name="csrf-token-simulador"]').attr('content');
}
const fechaSimulador = function(){
    let partes = document.getElementById('fechaEdad').value.trim().split('/');
    return partes.length == 3 && partes[2].length == 4 ? `${partes[2]}-${partes[1].padStart(2,'0')}-${partes[0].padStart(2,'0')}` : '';
}
const edadLocal = function(fecha){
    let nacimiento = new Date(fecha+'T00:00:00');
    let hoy = new Date();
    let edad = hoy.getFullYear()-nacimiento.getFullYear();
    if(hoy.getMonth() < nacimiento.getMonth() || (hoy.getMonth() == nacimiento.getMonth() && hoy.getDate() < nacimiento.getDate())){
        edad--;
    }
    return isNaN(edad) ? null : edad;
}
const errorCampoSimulador = function(campo,mensaje){
    let input = document.getElementById(campo);
    input.classList.toggle('is-invalid',!!mensaje);
    document.getElementById('error-'+campo).textContent = mensaje || '';
}
const cargarPagaduriasSimulador = async function(){
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-pagadurias`,new FormData(),'post',$('meta[name="csrf-token-menus"]').attr('content'));
    let html = '<option value="">Selecciona una pagaduría</option>';
    res.pagadurias.forEach(item=>{
        html += `<option value="${escapeHtml(item.IdPagaduria)}">${escapeHtml(String(item.NombrePagaduria).trim())}</option>`;
    });
    document.getElementById('idPagaduria').innerHTML = html;
}
const plazoSimulador = function(plazos,texto){
    let select = document.getElementById('periodoCredito');
    let anterior = select.value;
    let html = `<option value="">${escapeHtml(texto)}</option>`;
    plazos.forEach(plazo=>{
        html += `<option value="${plazo}"${plazo == anterior ? ' selected' : ''}>${plazo} ${plazo == 1 ? 'mes' : 'meses'}</option>`;
    });
    select.innerHTML = html;
    select.disabled = plazos.length == 0;
}
const avisoPlazo = function(texto){
    document.getElementById('aviso-plazo').innerHTML = texto ? `<div class="ui-alerta ui-alerta-adv mb-0" role="status"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><span class="ui-alerta-texto">${escapeHtml(texto)}</span></div>` : '';
}
const validarPeriodo = async function(){
    let idPagaduria = document.getElementById('idPagaduria').value;
    let fecha = fechaSimulador();
    let edad = fecha ? edadLocal(fecha) : null;
    errorCampoSimulador('idPagaduria',''); errorCampoSimulador('fechaEdad',''); errorCampoSimulador('periodoCredito','');
    avisoPlazo('');
    document.getElementById('ayuda-edad').textContent = edad != null && edad >= 0 ? `Edad: ${edad} años` : '';
    if(document.getElementById('fechaEdad').value.trim() != '' && (!fecha || edad == null)){
        errorCampoSimulador('fechaEdad','Usa el formato dd/mm/aaaa.');
    }else if(edad != null && edad < 0){
        errorCampoSimulador('fechaEdad','La fecha de nacimiento no puede ser futura.');
        fecha = '';
    }
    if(!idPagaduria || !fecha){
        plazoSimulador([],'Selecciona pagaduría y fecha');
        return;
    }
    let dataToSend = new FormData();
    dataToSend.append('edadFecha',fecha); dataToSend.append('idPagaduria',idPagaduria);
    try{
        let res = await makeOptionsFetch(`${globalUrl}/validar-meses`,dataToSend,'post',tokenSimulador(),true);
        if(res.estado == 'menor'){
            errorCampoSimulador('fechaEdad','El cliente debe ser mayor de edad.');
            plazoSimulador([],'Selecciona pagaduría y fecha');
        }else if(!res.plazos || res.plazos.length == 0){
            plazoSimulador([],'Sin plazos disponibles');
            avisoPlazo(`La pagaduría no tiene plazos para ${res.edad} años.`);
        }else{
            document.getElementById('ayuda-edad').textContent = `Edad: ${res.edad} años · Plazo máximo ${res.plazoMaximo} meses`;
            plazoSimulador(res.plazos,'Selecciona el plazo');
        }
    }catch(error){
        plazoSimulador([],'Sin plazos disponibles');
        if(error.data && error.data.errors && error.data.errors.edadFecha){
            errorCampoSimulador('fechaEdad',[].concat(error.data.errors.edadFecha)[0]);
        }else if(error.status == 422){
            avisoPlazo(edad != null ? `La pagaduría no tiene plazos para ${edad} años.` : error.message);
        }else if(error.status && error.status != 403){
            mostrarErrorHttp(error.data,error.status);
        }
    }
}
const tarjetaSimulador = function(etiqueta,valor,destacada=false){
    return `<div class="ui-tarjeta${destacada ? ' destacada' : ''}"><span class="ui-cifra-etiqueta">${etiqueta}</span><span class="ui-cifra">${formatearMoneda(valor)}</span></div>`;
}
const pintarSimulacion = function(datos,tabla){
    let totales = {cuota:0,capital:0,interes:0,seguro:0,cuotaTotal:0};
    let filas = `<tr><td>0</td><td class="num"></td><td class="num"></td><td class="num"></td><td class="num"></td><td class="num"></td><td class="num">${formatearMoneda(datos.monto)}</td></tr>`;
    tabla.forEach(fila=>{
        Object.keys(totales).forEach(campo=>{ totales[campo] += Number(fila[campo]) || 0; });
        filas += `<tr><td>${escapeHtml(fila.numero)}</td>${['cuota','capital','interes','seguro','cuotaTotal','saldo'].map(campo=>`<td class="num">${formatearMoneda(fila[campo])}</td>`).join('')}</tr>`;
    });
    document.getElementById('resultado-simulacion').innerHTML = `
        <section class="ui-card" aria-labelledby="titulo-resumen">
            <div class="ui-card-cab"><h2 id="titulo-resumen" tabindex="-1">Resumen de la simulación</h2></div>
            <div class="ui-tarjetas">
                ${tarjetaSimulador('Cuota',datos.cuota)}
                ${tarjetaSimulador('Seguro',datos.seguro)}
                ${tarjetaSimulador('Cuota total',datos.cuotaTotal,true)}
                ${tarjetaSimulador('Monto',datos.monto)}
            </div>
            <p class="ui-descripcion mb-0">Plazo ${escapeHtml(datos.plazo)} meses · Tasa ${formatearPorcentaje(datos.tasa)} mensual · Seguro ${formatearPorcentaje(datos.porcentajeSeguro*100,4)} del monto · Edad ${escapeHtml(datos.edad)} años</p>
        </section>
        <section class="ui-card" aria-labelledby="titulo-amortizacion">
            <div class="ui-card-cab"><h2 id="titulo-amortizacion">Tabla de amortización</h2></div>
            <div class="ui-scroll ui-scroll-amortizacion">
                <table class="ui-tabla ui-tabla-amortizacion">
                    <thead>
                        <tr><th scope="col">N°</th><th scope="col" class="num">Cuota</th><th scope="col" class="num">Capital</th><th scope="col" class="num">Interés</th><th scope="col" class="num">Seguro</th><th scope="col" class="num">Cuota total</th><th scope="col" class="num">Saldo</th></tr>
                    </thead>
                    <tbody>${filas}</tbody>
                    <tfoot>
                        <tr><td>Total</td>${['cuota','capital','interes','seguro','cuotaTotal'].map(campo=>`<td class="num">${formatearMoneda(totales[campo])}</td>`).join('')}<td></td></tr>
                    </tfoot>
                </table>
            </div>
        </section>`;
    document.getElementById('titulo-resumen').focus();
}
const calcular = async function(e){
    e.preventDefault();
    let campos = {
        idPagaduria:document.getElementById('idPagaduria').value,
        fechaEdad:fechaSimulador(),
        periodoCredito:document.getElementById('periodoCredito').value,
        valorCredito:numeroLimpio(document.getElementById('valorCredito').value)
    };
    let mensajes = {
        idPagaduria:'Selecciona una pagaduría.',
        fechaEdad:'Ingresa la fecha de nacimiento.',
        periodoCredito:'Selecciona el plazo.',
        valorCredito:'Ingresa un monto mayor a cero.'
    };
    let valido = true;
    Object.keys(campos).forEach(campo=>{
        let falta = campo == 'valorCredito' ? !(Number(campos[campo]) > 0) : campos[campo] === '';
        if(campo == 'fechaEdad' && document.getElementById('fechaEdad').classList.contains('is-invalid')){
            falta = true;
        }else{
            errorCampoSimulador(campo,falta ? mensajes[campo] : '');
        }
        valido = valido && !falta;
    });
    if(!valido){
        return;
    }
    let dataToSend = new FormData();
    Object.keys(campos).forEach(campo=>dataToSend.append(campo,campos[campo]));
    let boton = document.getElementById('btnCalcular');
    boton.disabled = true;
    try{
        let res = await makeOptionsFetch(`${globalUrl}/simulacion-credito`,dataToSend,'post',tokenSimulador(),true);
        pintarSimulacion(res.datos,res.tabla);
    }catch(error){
        let errores = (error.data && error.data.errors) || {};
        let enLinea = Object.keys(errores).filter(campo=>document.getElementById('error-'+campo));
        enLinea.forEach(campo=>errorCampoSimulador(campo,[].concat(errores[campo])[0]));
        if(!enLinea.length && error.data){
            mostrarErrorHttp(error.data,error.status);
        }
    }finally{
        boton.disabled = false;
    }
}
