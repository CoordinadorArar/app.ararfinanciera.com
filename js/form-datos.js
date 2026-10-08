const PASOS = ['Datos personales','Datos financieros','Autorización'];
const SECCIONES = ['form-datos-personales','form-datos-financieros','form-tratamiento-datos'];
const CAMPOS_PERSONALES = ['tipoDocumento','documentoTercero','fechaExpedicion','lugarExpedicion','nombres','apellidos','fechaNacimiento','telefonoTercero','emailTercero','departamentoResidencia','ciudadResidencia','direccionTercero'];
const TAMANO_MAXIMO_PDF = 2 * 1024 * 1024;
const registro = {paso:1, alcanzado:1, idTercero:'', idProceso:'', estadoProceso:null, sucio:false, bloqueado:false, precargado:false, docConsultado:'', calculo:null, tratamiento:{}, metodo:'', archivo:null, reemplazando:false, avisoReintento:'', final:null, cargaRubros:0};
const tokenMenus = ()=>$('meta[name="csrf-token-menus"]').attr('content');
const tokenPersonales = ()=>$('meta[name="csrf-token-form-personal-data"]').attr('content');
const tokenFinancieros = ()=>$('meta[name="csrf-token-form-financial-data"]').attr('content');
const tokenTratamiento = ()=>$('meta[name="csrf-token-form-approve-data"]').attr('content');
const elemento = id=>document.getElementById(id);
const contenedor = ()=>document.querySelector('.registro-tercero');

window.addEventListener('load', function(){
    $.datetimepicker.setLocale('es');
    $('.campo-fecha').datetimepicker({
        format:'d/m/Y',
        timepicker:false,
        datepicker:true,
        maxDate:0,
        scrollInput:false,
        validateOnBlur:false,
        onShow:function(ct,$input){ return !$input.prop('readonly'); }
    });
    registro.catalogos = Promise.all([mostrarPagadurias(), mostrarTiposDocumentos(), mostrarDepartmentos()]);
    let form = elemento('form-datos');
    form.addEventListener('input', e=>{
        let campo = e.target;
        campo.dataset.tocado = '1';
        if(campo.classList.contains('campo-fecha')){
            let d = campo.value.replace(/\D/g,'').slice(0,8);
            campo.value = d.replace(/^(\d{2})(\d)/,'$1/$2').replace(/^(\d{2}\/\d{2})(\d)/,'$1/$2');
        }
        if(campo.id == 'documentoTercero' && registro.precargado && campo.value.trim() != registro.docConsultado){
            limpiarRegistro();
        }
        if(campo.classList.contains('is-invalid')){
            validarCampo(campo.id);
        }
    });
    form.addEventListener('focusout', e=>{
        let campo = e.target;
        if(campo.hasAttribute('data-mayus') && !campo.readOnly){
            campo.value = campo.value.toUpperCase();
        }
        if(campo.dataset.tocado){
            validarCampo(campo.id);
        }
        if(campo.id == 'documentoTercero'){
            consultarDocumento();
        }
    });
    form.addEventListener('change', e=>{
        let campo = e.target;
        if(campo.id == 'departamentoResidencia'){
            cargarCiudades(campo.value);
        }
        if(campo.tagName == 'SELECT' || campo.classList.contains('campo-fecha')){
            campo.dataset.tocado = '1';
            validarCampo(campo.id);
        }
        if(campo.id == 'tipoDocumento'){
            if(elemento('documentoTercero').value.trim() != ''){
                validarCampo('documentoTercero');
                consultarDocumento();
            }
        }
        if(campo.id == 'fechaNacimiento' && elemento('fechaExpedicion').value != ''){
            validarCampo('fechaExpedicion');
        }
    });
    SECCIONES.forEach(id=>['input','change'].forEach(evento=>elemento(id).addEventListener(evento, e=>{
        if(!e.target.classList.contains('btn-check')){
            registro.sucio = true;
        }
    })));
    ['input','change'].forEach(evento=>elemento('form-datos-financieros').addEventListener(evento, e=>{
        if(e.target.matches('input, select')){
            desactualizarCalculo();
        }
    }));
    window.addEventListener('beforeunload', e=>{
        if(registro.sucio){
            e.preventDefault();
            e.returnValue = '';
        }
    });
    iniciarRegistro();
});

const iniciarRegistro = async function(){
    let params = new URLSearchParams(location.search);
    let idProceso = params.get('proceso'), idTercero = params.get('tercero');
    if(!idProceso && !idTercero){
        irAPaso(1, null, false);
        return;
    }
    elemento('cargaRegistro').classList.remove('d-none');
    contenedor().setAttribute('aria-busy','true');
    try{
        let datos = new FormData();
        datos.append(idProceso ? 'idProceso' : 'idTercero', idProceso || idTercero);
        let res = await makeOptionsFetch(`${globalUrl}/estado-registro`,datos,'post',tokenPersonales(),true);
        if(!res.tercero){
            irAPaso(1, null, false);
            return procesoAjeno(res);
        }
        await cargarEstado(res);
        if(res.paso == 'finalizado'){
            mostrarFinal(res.proceso && Number(res.proceso.EstadoProceso) === 0 ? 'cerrado' : 'exito', res.mensaje);
        }else{
            irAPaso(res.paso);
        }
    }catch(e){
        renderStepper(0);
        if(e.status == 422){
            elemento('errorRegistro').classList.remove('d-none');
        }else if(e.status != 403){
            mostrarErrorHttp(e.data,e.status);
        }
    }finally{
        elemento('cargaRegistro').classList.add('d-none');
        contenedor().removeAttribute('aria-busy');
    }
}

const renderStepper = function(actual){
    let rechazado = registro.final == 'rechazado' || registro.final == 'cerrado';
    elemento('listaPasos').innerHTML = PASOS.map((nombre,i)=>{
        let n = i + 1;
        let estado = registro.final ? (rechazado && n == 3 ? 'pendiente' : 'completo') : (n == actual ? 'actual' : (n <= registro.alcanzado ? 'completo' : 'pendiente'));
        let textos = {completo:['Completo','(completo)'], actual:['En curso','(paso actual)'], pendiente:['Pendiente','(pendiente)']}[estado];
        let marca = estado == 'completo' ? '<i class="fas fa-check" aria-hidden="true"></i>' : n;
        let interior = `<span class="ui-paso-marca" aria-hidden="true">${marca}</span><span><span class="ui-paso-texto">${nombre}</span><span class="ui-paso-estado" aria-hidden="true">${textos[0]}</span></span><span class="visually-hidden">${textos[1]}</span>`;
        let contenido = estado == 'completo' && !registro.final ? `<button type="button" class="ui-paso-cont ui-paso-btn" onclick="irAPaso(${n})">${interior}</button>` : `<span class="ui-paso-cont">${interior}</span>`;
        return `<li class="ui-paso is-${estado}"${estado == 'actual' ? ' aria-current="step"' : ''}>${contenido}</li>`;
    }).join('');
    elemento('stepperResumen').textContent = registro.final ? (rechazado ? 'Cerrado' : 'Registro completo') : (actual ? `Paso ${actual} de 3 · ${PASOS[actual-1]}` : '');
}

const actualizarUrl = function(){
    let consulta = registro.idProceso ? `?proceso=${registro.idProceso}` : (registro.idTercero ? `?tercero=${registro.idTercero}` : '');
    history.replaceState(null,'',location.pathname+consulta);
}

const irAPaso = function(n, foco=null, enfocar=true){
    registro.paso = n;
    registro.alcanzado = Math.max(registro.alcanzado, n);
    SECCIONES.forEach((id,i)=>elemento(id).classList.toggle('d-none', i + 1 != n));
    elemento('data-finished').classList.add('d-none');
    renderStepper(n);
    actualizarUrl();
    document.title = `Paso ${n} de 3 · Registro de cliente`;
    elemento('anuncioPaso').textContent = `Paso ${n} de 3: ${PASOS[n-1]}`;
    if(n == 2){
        modoFinanciero();
    }
    if(n == 3){
        renderTratamiento();
    }
    if(enfocar){
        (foco ? elemento(foco) : elemento(`titulo-paso-${n}`)).focus();
    }
}

const cargando = async function(boton, texto, accion){
    let original = boton.innerHTML, padre = boton.parentElement;
    boton.disabled = true;
    boton.classList.add('is-cargando');
    padre.setAttribute('aria-busy','true');
    boton.innerHTML = `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>${texto}</span>`;
    try{
        return await accion();
    }finally{
        boton.innerHTML = original;
        boton.disabled = false;
        boton.classList.remove('is-cargando');
        padre.removeAttribute('aria-busy');
    }
}

const marcarError = function(campo, mensaje){
    let input = elemento(campo);
    let error = elemento('error-'+campo);
    if(input){
        input.classList.toggle('is-invalid', !!mensaje);
    }
    if(error){
        error.textContent = mensaje || '';
        error.classList.toggle('d-block', !!mensaje);
    }
}

const alerta = function(tipo, icono, texto, accion='', rol=''){
    return `<div class="ui-alerta ui-alerta-${tipo}"${rol ? ` role="${rol}"` : ''}><i class="fas ${icono}" aria-hidden="true"></i><span class="ui-alerta-texto">${texto}</span>${accion ? `<div class="ui-alerta-accion ui-acciones">${accion}</div>` : ''}</div>`;
}

const mostrarPagadurias = async function(){
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-pagadurias`,new FormData(),'post',tokenMenus());
    let html = '<option value="">Selecciona una pagaduría</option>';
    res.pagadurias.forEach(item=>{
        html += `<option value="${escapeHtml(item.IdPagaduria)}">${escapeHtml(item.NombrePagaduria)}</option>`;
    });
    elemento('pagaduriaTercero').innerHTML = html;
}

const mostrarTiposDocumentos = async function(){
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-tipos-documentos`,new FormData(),'post',tokenMenus());
    let html = '<option value="">Selecciona el tipo de documento</option>';
    res.tipos.forEach(item=>{
        html += `<option value="${escapeHtml(item.IdTipoDocumento)}">${escapeHtml(item.NombreTipoDocumento)}</option>`;
    });
    elemento('tipoDocumento').innerHTML = html;
}

const mostrarDepartmentos = async function(){
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-departamentos`,new FormData(),'post',tokenMenus());
    let html = '<option value="">Selecciona un departamento</option>';
    res.departamentos.forEach(item=>{
        html += `<option value="${escapeHtml(item.IdDepartamento)}">${escapeHtml(item.NombreDepartamento)}</option>`;
    });
    elemento('departamentoResidencia').innerHTML = html;
}

const cargarCiudades = async function(idDepartamento, seleccion=''){
    let select = elemento('ciudadResidencia');
    select.disabled = true;
    if(!idDepartamento){
        select.innerHTML = '<option value="">Selecciona un departamento</option>';
        return;
    }
    select.innerHTML = '<option value="">Cargando ciudades…</option>';
    let datos = new FormData();
    datos.append('idDepartamento',idDepartamento);
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-ciudades`,datos,'post',tokenMenus());
    if(elemento('departamentoResidencia').value != idDepartamento){
        return;
    }
    let html = '<option value="">Selecciona una ciudad</option>';
    res.municipios.forEach(item=>{
        html += `<option value="${escapeHtml(item.IdMunicipio)}"${item.IdMunicipio == seleccion ? ' selected' : ''}>${escapeHtml(item.NombreMunicipio)}</option>`;
    });
    select.innerHTML = html;
    select.disabled = registro.bloqueado;
}

const tipoDocumentoSigla = function(){
    let select = elemento('tipoDocumento');
    if(!select.value){
        return '';
    }
    let nombre = select.options[select.selectedIndex].text.toLowerCase();
    let sigla = nombre.replace(/[^a-z]/g,'');
    if(nombre.includes('nit') || nombre.includes('tributari')) return 'NIT';
    if(nombre.includes('extranjer') || sigla == 'ce') return 'CE';
    if(nombre.includes('pasaporte') || sigla == 'pa' || sigla == 'pp') return 'PA';
    if(nombre.includes('ciudadan') || sigla == 'cc') return 'CC';
    return '';
}

const aFecha = function(texto){
    let m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(texto || '');
    if(!m){
        return null;
    }
    let fecha = new Date(+m[3], +m[2] - 1, +m[1]);
    return fecha.getDate() == +m[1] && fecha.getMonth() == +m[2] - 1 ? fecha : null;
}

const fechaVista = function(ymd){
    let m = /^(\d{4})-(\d{2})-(\d{2})/.exec(ymd || '');
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
}

const fechaServidor = function(texto){
    let m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(texto || '');
    return m ? `${m[3]}-${m[2]}-${m[1]}` : texto;
}

const calcularEdad = function(nacimiento, hoy=new Date()){
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    if(hoy.getMonth() < nacimiento.getMonth() || (hoy.getMonth() == nacimiento.getMonth() && hoy.getDate() < nacimiento.getDate())){
        edad--;
    }
    return edad;
}

const mensajeDocumento = function(valor){
    let reglas = {
        CC:[/^\d{5,10}$/,'La cédula debe tener entre 5 y 10 dígitos.'],
        CE:[/^\d{3,10}$/,'La cédula de extranjería debe tener entre 3 y 10 dígitos.'],
        NIT:[/^\d{6,10}(-\d)?$/,'El NIT debe tener entre 6 y 10 dígitos y, opcionalmente, el dígito de verificación separado por guion (900123456-7).'],
        PA:[/^\d{5,10}$/,'El pasaporte debe tener entre 5 y 10 dígitos.']
    };
    let [patron, mensaje] = reglas[tipoDocumentoSigla()] || [/^[A-Za-z0-9]{3,15}$/,'El documento debe tener entre 3 y 15 letras o números.'];
    if(!patron.test(valor)){
        return mensaje;
    }
    let base = valor.split('-')[0];
    return /^\d+$/.test(base) && Number(base) <= 2147483647 ? '' : 'El número de documento no puede superar 2.147.483.647.';
}

const mensajeCampo = function(id){
    let input = elemento(id);
    let valor = input.value.trim();
    if(id == 'tipoDocumento') return valor ? '' : 'Selecciona el tipo de documento.';
    if(id == 'departamentoResidencia') return valor ? '' : 'Selecciona un departamento.';
    if(id == 'ciudadResidencia'){
        let dep = elemento('departamentoResidencia');
        return valor ? '' : (dep.value ? `Selecciona una ciudad de ${dep.options[dep.selectedIndex].text}.` : 'Selecciona primero un departamento.');
    }
    if(valor == '') return 'Este campo es obligatorio.';
    if(id == 'documentoTercero') return mensajeDocumento(valor);
    if(id == 'telefonoTercero'){
        let tel = valor.replace(/[\s\-.()]/g,'');
        return /^3\d{9}$/.test(tel) || /^(?:[1-9]\d{6}|[124-9]\d{7,9})$/.test(tel) ? '' : 'Ingresa un celular de 10 dígitos que empiece por 3 o un fijo de 7 a 10 dígitos.';
    }
    if(id == 'emailTercero') return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(valor) ? '' : 'Ingresa un correo válido, por ejemplo nombre@dominio.com.';
    if(id == 'fechaNacimiento' || id == 'fechaExpedicion'){
        let fecha = aFecha(valor);
        let hoy = new Date();
        if(!fecha) return 'Ingresa una fecha válida con el formato dd/mm/aaaa.';
        if(fecha > hoy) return id == 'fechaNacimiento' ? 'La fecha de nacimiento no puede ser futura.' : 'La fecha de expedición no puede ser futura.';
        if(id == 'fechaNacimiento'){
            let edad = calcularEdad(fecha);
            if(edad < 18) return `El cliente debe ser mayor de edad (tiene ${edad} ${edad == 1 ? 'año' : 'años'}).`;
            if(edad >= 100) return 'El cliente debe ser menor de 100 años.';
            return '';
        }
        let nacimiento = aFecha(elemento('fechaNacimiento').value);
        if(nacimiento && fecha < new Date(nacimiento.getFullYear() + 18, nacimiento.getMonth(), nacimiento.getDate())) return 'La fecha de expedición debe ser posterior a la fecha en que el cliente cumplió 18 años.';
    }
    return '';
}

const validarCampo = function(id){
    if(!CAMPOS_PERSONALES.includes(id)){
        return '';
    }
    let mensaje = mensajeCampo(id);
    marcarError(id, mensaje);
    if(id == 'fechaNacimiento'){
        let fecha = aFecha(elemento(id).value);
        elemento('ayuda-fechaNacimiento').textContent = fecha && !mensaje ? `Edad: ${calcularEdad(fecha)} años` : '';
    }
    return mensaje;
}

const consultarDocumento = async function(){
    let input = elemento('documentoTercero');
    let valor = input.value.trim();
    if(!valor || !elemento('tipoDocumento').value || valor == registro.docConsultado || mensajeDocumento(valor)){
        return;
    }
    registro.docConsultado = valor;
    let indicador = elemento('indicadorDocumento');
    indicador.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span><span class="visually-hidden">Consultando documento…</span>';
    try{
        let datos = new FormData();
        datos.append('documento',valor);
        let res = await makeOptionsFetch(`${globalUrl}/validar-documento`,datos,'post',tokenMenus(),true);
        if(input.value.trim() != valor || !res.existe){
            return;
        }
        if(!res.tercero){
            return procesoAjeno(res, valor);
        }
        await cargarEstado(res);
        alertaDocumento(res);
        renderStepper(1);
        actualizarUrl();
    }catch(e){
        registro.docConsultado = '';
    }finally{
        indicador.innerHTML = '<i class="fas fa-id-card" aria-hidden="true"></i>';
    }
}

const procesoAjeno = function(res, documento=''){
    limpiarRegistro();
    Object.assign(registro, {precargado:true, docConsultado:documento});
    alertaDocumento(Object.assign({}, res, {paso:'finalizado'}));
}

const alertaDocumento = function(res){
    let p = res.proceso, html;
    if(res.paso == 'finalizado'){
        html = alerta('adv','fa-triangle-exclamation',`${escapeHtml(res.mensaje)} No es posible iniciar un nuevo registro hasta que ese proceso termine.`,`<a href="${contenedor().dataset.urlProcesos}" class="btn btn-sm ui-btn ui-btn-sec"><i class="fas fa-list-check" aria-hidden="true"></i><span>Ir a la bandeja de procesos</span></a>`,'status');
        bloquearPersonales(true);
    }else if(p && Number(p.EstadoProceso) !== 0){
        html = alerta('info','fa-circle-info',`Este cliente tiene un registro sin terminar (paso ${res.paso} de 3: ${PASOS[res.paso-1]}).`,`<a href="?proceso=${encodeURIComponent(p.IdProceso)}" class="btn btn-sm ui-btn ui-btn-sec"><i class="fas fa-arrow-right" aria-hidden="true"></i><span>Continuar registro</span></a>`,'status');
    }else{
        html = alerta('info','fa-circle-info',`Este cliente ya está registrado; revisa y actualiza sus datos.${res.mensaje ? ' '+escapeHtml(res.mensaje) : ''}`,'','status');
    }
    elemento('alertaPersonales').innerHTML = html;
}

const bloquearPersonales = function(bloquear){
    registro.bloqueado = bloquear;
    CAMPOS_PERSONALES.filter(c=>c != 'tipoDocumento' && c != 'documentoTercero').forEach(c=>{
        let campo = elemento(c);
        if(campo.tagName == 'SELECT'){
            campo.disabled = bloquear || (c == 'ciudadResidencia' && campo.options.length < 2);
        }else{
            campo.readOnly = bloquear;
        }
    });
    elemento('btnGuardarPersonales').disabled = bloquear;
}

const cargarEstado = async function(res){
    await registro.catalogos;
    let t = res.tercero, p = res.proceso;
    let activo = p && Number(p.EstadoProceso) !== 0;
    registro.precargado = true;
    registro.idTercero = String(t.IdTercero);
    registro.idProceso = p && (activo || res.paso == 'finalizado') ? String(p.IdProceso) : '';
    registro.estadoProceso = activo ? Number(p.EstadoProceso) : null;
    registro.tratamiento = Object.assign({}, res.tratamiento || {});
    registro.avisoReintento = res.paso == 2 && res.mensaje ? res.mensaje : '';
    registro.calculo = null;
    registro.alcanzado = res.paso == 'finalizado' ? 3 : res.paso;
    registro.docConsultado = String(t.DocumentoTercero).trim();
    let valores = {idTercero:t.IdTercero, tipoDocumento:t.IdTipoDocumento, documentoTercero:t.DocumentoTercero, nombres:t.NombresTercero, apellidos:t.ApellidosTercero, fechaExpedicion:fechaVista(t.FechaExpedicionDocumento), lugarExpedicion:t.LugarExpedicionDocumento, fechaNacimiento:fechaVista(t.FechaNacimientoTercero), telefonoTercero:t.TelefonoTercero, emailTercero:t.EmailTercero, direccionTercero:t.DireccionDomicilioTercero, departamentoResidencia:t.DepartamentoDomicilioTercero};
    Object.keys(valores).forEach(c=>{
        elemento(c).value = valores[c] == null ? '' : String(valores[c]).trim();
        marcarError(c,'');
    });
    validarCampo('fechaNacimiento');
    marcarError('fechaNacimiento','');
    bloquearPersonales(res.paso == 'finalizado');
    await cargarCiudades(t.DepartamentoDomicilioTercero, t.CiudadDomicilioTercero);
    actualizarResumen();
    elemento('pagaduriaTercero').value = (activo && p.IdPagaduria) ? p.IdPagaduria : (t.IdPagaduria || '');
    elemento('ingresosMensuales').value = Number(t.IngresosTercero) > 0 ? formatearMoneda(t.IngresosTercero,false) : '';
    elemento('valorSolicitado').value = activo && p.valorSolicitado ? formatearMoneda(p.valorSolicitado,false) : '';
    ocultarAlertaMonto();
    elemento('cupoDisponible').innerHTML = '';
    await Promise.all([mostrarCuotas(activo ? p.numeroCuotas : ''), cargarRubros(activo ? p.rubros : {})]);
    if(activo && Number(p.EstadoProceso) >= 2){
        registro.calculo = {creado:true, cupo:p.cupo, cuotaTotal:p.cuota, tasa:p.tasa, plazo:p.numeroCuotas, monto:p.valorSolicitado};
    }
    registro.sucio = false;
}

const actualizarResumen = function(){
    let resumen = elemento('resumenCliente');
    let doc = elemento('documentoTercero').value.trim();
    let select = elemento('tipoDocumento');
    let tipo = tipoDocumentoSigla() || (select.value ? select.options[select.selectedIndex].text : '');
    resumen.textContent = `Cliente: ${elemento('apellidos').value.trim()} ${elemento('nombres').value.trim()} · ${tipo} ${/^\d+$/.test(doc) ? formatearMoneda(doc,false) : doc}`;
    resumen.classList.toggle('d-none', !registro.idTercero);
}

const limpiarRegistro = function(){
    let tipo = elemento('tipoDocumento').value, doc = elemento('documentoTercero').value;
    elemento('form-datos').reset();
    elemento('tipoDocumento').value = tipo;
    elemento('documentoTercero').value = doc;
    elemento('idTercero').value = '';
    CAMPOS_PERSONALES.forEach(c=>{ marcarError(c,''); delete elemento(c).dataset.tocado; });
    elemento('ayuda-fechaNacimiento').textContent = '';
    elemento('alertaPersonales').innerHTML = '';
    bloquearPersonales(false);
    cargarCiudades('');
    Object.assign(registro, {alcanzado:1, idTercero:'', idProceso:'', estadoProceso:null, precargado:false, docConsultado:'', calculo:null, tratamiento:{}, metodo:'', archivo:null, reemplazando:false, avisoReintento:''});
    elemento('form-financiero').reset();
    elemento('inputs-config').innerHTML = '';
    elemento('cupoDisponible').innerHTML = '';
    ocultarAlertaMonto();
    mostrarCuotas();
    actualizarResumen();
    renderStepper(1);
    actualizarUrl();
}

const enviarDatosPersonales = async function(e){
    e.preventDefault();
    if(registro.bloqueado){
        return;
    }
    let primero = CAMPOS_PERSONALES.find(c=>validarCampo(c) != '');
    if(primero){
        elemento(primero).focus();
        return;
    }
    let datos = new FormData(elemento('form-datos'));
    datos.set('fechaNacimiento', fechaServidor(elemento('fechaNacimiento').value));
    datos.set('fechaExpedicion', fechaServidor(elemento('fechaExpedicion').value));
    datos.set('idTercero', registro.idTercero);
    await cargando(elemento('btnGuardarPersonales'),'Guardando…', async ()=>{
        let res;
        try{
            res = await makeOptionsFetch(`${globalUrl}/guardar-datos-personales`,datos,'post',tokenPersonales(),true);
        }catch(err){
            let errores = (err.data && err.data.errors) || {};
            Object.keys(errores).filter(c=>CAMPOS_PERSONALES.includes(c)).forEach(c=>marcarError(c,[].concat(errores[c])[0]));
            let campo = CAMPOS_PERSONALES.find(c=>errores[c]);
            let general = Object.keys(errores).find(c=>!CAMPOS_PERSONALES.includes(c));
            if(err.status != 403 && (!campo || general)){
                elemento('alertaPersonales').innerHTML = alerta('error','fa-circle-exclamation',escapeHtml(general ? [].concat(errores[general])[0] : mensajeErrorHttp(err.data)),'','alert');
            }
            if(campo){
                elemento(campo).focus();
            }
            return;
        }
        registro.idTercero = String(res.data.id);
        registro.docConsultado = elemento('documentoTercero').value.trim();
        elemento('idTercero').value = registro.idTercero;
        registro.sucio = false;
        actualizarResumen();
        notificar('Datos personales guardados');
        if(registro.estadoProceso === null || registro.estadoProceso === 1){
            await mostrarCuotas();
        }
        irAPaso(2);
    });
}

const mostrarCuotas = async function(seleccion=''){
    let select = elemento('numeroCuotas');
    let idPagaduria = elemento('pagaduriaTercero').value;
    seleccion = seleccion || select.value;
    let res = {};
    if(registro.idTercero != '' && idPagaduria != ''){
        let datos = new FormData();
        datos.append('idTercero',registro.idTercero);
        datos.append('idPagaduria',idPagaduria);
        try{
            res = await makeOptionsFetch(`${globalUrl}/verificar-edad-tercero`,datos,'post',tokenFinancieros(),true);
        }catch(e){
            res = {};
        }
    }
    let plazos = idPagaduria != '' && res.y >= 18 ? (res.plazos || []) : [];
    let html = `<option value="0">${plazos.length ? 'Selecciona el número de cuotas' : (idPagaduria != '' ? 'Sin plazos disponibles' : 'Selecciona una pagaduría')}</option>`;
    plazos.forEach(plazo=>{
        html += `<option value="${escapeHtml(plazo)}"${plazo == seleccion ? ' selected' : ''}>${escapeHtml(plazo)} ${plazo == 1 ? 'cuota' : 'cuotas'}</option>`;
    });
    select.innerHTML = html;
    select.disabled = plazos.length == 0 || registro.estadoProceso >= 2;
    elemento('ayuda-numeroCuotas').textContent = idPagaduria == '' ? '' : (plazos.length ? `Plazo máximo de ${res.plazoMaximo} meses para ${res.edad} años` : (res.message || 'Sin plazos configurados para esta pagaduría y edad'));
}

const textoRubro = function(nombre){
    let texto = nombre.replace(/([A-Z])/g,' $1').trim().toLowerCase();
    return texto.charAt(0).toUpperCase()+texto.slice(1);
}

const cargarRubros = async function(valores=null){
    let destino = elemento('inputs-config');
    let idPagaduria = elemento('pagaduriaTercero').value;
    let actuales = valores || {};
    if(!valores){
        [...document.getElementsByClassName('input-rubro')].forEach(input=>{ actuales[input.dataset.rubro] = numeroLimpio(input.value); });
    }
    let turno = ++registro.cargaRubros;
    if(!idPagaduria){
        destino.innerHTML = '';
        return;
    }
    destino.innerHTML = `<div class="ui-cargando-linea mt-4" role="status"><span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span><span>Cargando deducciones…</span></div>`;
    let datos = new FormData();
    datos.append('pagaduriaTercero',idPagaduria);
    datos.append('ingresosMensuales',numeroLimpio(elemento('ingresosMensuales').value));
    datos.append('idTercero',registro.idTercero);
    let res;
    try{
        res = await makeOptionsFetch(`${globalUrl}/mostrar-config-inputs`,datos,'post',tokenFinancieros(),true);
    }catch(e){
        if(turno == registro.cargaRubros){
            destino.innerHTML = `<div class="mt-4">${alerta('error','fa-circle-exclamation',escapeHtml(mensajeErrorHttp(e.data)),'','alert')}</div>`;
        }
        return;
    }
    if(turno != registro.cargaRubros){
        return;
    }
    let rubros = [];
    res.forEach(element=>{
        String(element.Configuracion).split('|').map(item=>item.trim()).forEach(item=>{
            if(item.length > 1 && isNaN(item) && item != 'ingresos' && item != 'salarioMinimoMensual' && !rubros.includes(item)){
                rubros.push(item);
            }
        });
    });
    let soloLectura = registro.estadoProceso >= 2 ? ' disabled' : '';
    let html = rubros.map(rubro=>{
        let id = escapeHtml(rubro);
        let valor = actuales[rubro] != null && actuales[rubro] !== '' ? formatearMoneda(numeroLimpio(actuales[rubro]),false) : '';
        return `<div class="col-12 col-md-6 col-lg-4">
                    <label for="rubro-${id}" class="form-label">${escapeHtml(textoRubro(rubro))}</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text">$</span>
                        <input type="text" inputmode="numeric" autocomplete="off" class="form-control input-rubro" id="rubro-${id}" data-rubro="${id}" value="${valor}" aria-describedby="error-rubro-${id}" oninput="formatearInputMoneda(this);marcarError(this.id,'')"${soloLectura}>
                        <span class="invalid-feedback" role="alert" id="error-rubro-${id}"></span>
                    </div>
                </div>`;
    }).join('');
    destino.innerHTML = rubros.length ? `<fieldset class="ui-seccion mt-4 mb-0"><legend class="ui-seccion-titulo">Deducciones y otros ingresos</legend><div class="row g-3">${html}</div></fieldset>` : '';
}

const ocultarAlertaMonto = function(){
    elemento('alerta-monto').innerHTML = '';
}

const mostrarAlertaMonto = function(data){
    marcarError('valorSolicitado','');
    elemento('valorSolicitado').classList.add('is-invalid');
    let monto = Number(data.montoMaximo) || 0;
    elemento('alerta-monto').innerHTML = `<div class="ui-alerta ui-alerta-adv mb-0">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <span class="ui-alerta-texto">La cuota (${formatearMoneda(data.cuota)}) supera el cupo disponible (${formatearMoneda(data.cupo)}). Monto máximo prestable: <strong>${formatearMoneda(monto)}</strong></span>
            ${monto > 0 ? `<div class="ui-alerta-accion"><button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="usarMontoMaximo(${monto})"><i class="fas fa-arrow-down" aria-hidden="true"></i><span>Usar monto máximo</span></button></div>` : ''}
        </div>`;
}

const usarMontoMaximo = function(monto){
    let input = elemento('valorSolicitado');
    input.value = formatearMoneda(monto,false);
    input.classList.remove('is-invalid');
    elemento('alerta-monto').innerHTML = `<div class="ui-alerta ui-alerta-info mb-0"><i class="fas fa-circle-info" aria-hidden="true"></i><span class="ui-alerta-texto">Se ajustó al monto máximo. Pulsa Calcular para continuar.</span></div>`;
    registro.sucio = true;
    input.focus();
}

const editarMontoSolicitado = function(input){
    formatearInputMoneda(input);
    marcarError('valorSolicitado','');
    ocultarAlertaMonto();
}

const cambiarPagaduria = function(){
    marcarError('pagaduriaTercero','');
    ocultarAlertaMonto();
    mostrarCuotas();
    cargarRubros();
}

const montoInvalido = function(campo, vacio){
    let valor = Number(numeroLimpio(elemento(campo).value));
    return valor > 2147483647 ? 'El valor no puede superar $ 2.147.483.647.' : (valor > 0 ? '' : vacio);
}

const validarFinancieros = function(){
    let errores = {
        pagaduriaTercero: elemento('pagaduriaTercero').value != '' ? '' : 'Selecciona una pagaduría.',
        ingresosMensuales: montoInvalido('ingresosMensuales','Ingresa los ingresos básicos.'),
        valorSolicitado: montoInvalido('valorSolicitado','Ingresa un valor mayor a cero.'),
        numeroCuotas: elemento('numeroCuotas').value != '0' ? '' : 'Selecciona el número de cuotas.'
    };
    [...document.getElementsByClassName('input-rubro')].forEach(input=>{
        errores[input.id] = input.value.trim() === '' ? 'Ingresa un valor; usa 0 si no aplica.' : '';
    });
    Object.keys(errores).forEach(campo=>marcarError(campo,errores[campo]));
    let primero = Object.keys(errores).find(campo=>errores[campo] != '');
    if(primero){
        elemento(primero).focus();
    }
    return !primero;
}

const datosFinancieros = function(){
    let datos = new FormData();
    let ingresos = numeroLimpio(elemento('ingresosMensuales').value);
    datos.append('idTercero',registro.idTercero);
    datos.append('idPagaduria',elemento('pagaduriaTercero').value);
    datos.append('ingresos',ingresos);
    datos.append('valorSolicitado',numeroLimpio(elemento('valorSolicitado').value));
    datos.append('numeroCuotas',elemento('numeroCuotas').value);
    datos.append('nombres[]','ingresos');
    datos.append('valores[]',ingresos);
    [...document.getElementsByClassName('input-rubro')].forEach(input=>{
        datos.append('nombres[]',input.dataset.rubro);
        datos.append('valores[]',numeroLimpio(input.value));
    });
    return datos;
}

const errorFinanciero = function(e){
    if(e.data && e.data.montoMaximo != null){
        registro.calculo = null;
        elemento('cupoDisponible').innerHTML = '';
        mostrarAlertaMonto(e.data);
        actualizarBotonesFinancieros();
        return;
    }
    let errores = (e.data && e.data.errors) || {};
    let mapa = {ingresos:'ingresosMensuales', idPagaduria:'pagaduriaTercero'};
    let campos = Object.keys(errores).map(clave=>[mapa[clave] || clave, [].concat(errores[clave])[0]]).filter(([campo])=>elemento(campo));
    campos.forEach(([campo,mensaje])=>marcarError(campo,mensaje));
    if(campos.length){
        elemento(campos[0][0]).focus();
    }else if(e.status != 403){
        mostrarErrorHttp(e.data,e.status);
    }
}

const calcularFinancieros = async function(boton){
    ocultarAlertaMonto();
    if(!validarFinancieros()){
        return;
    }
    await cargando(boton,'Calculando…', async ()=>{
        try{
            let res = await makeOptionsFetch(`${globalUrl}/calcular-datos-financieros`,datosFinancieros(),'post',tokenFinancieros(),true);
            registro.calculo = Object.assign(res,{monto:numeroLimpio(elemento('valorSolicitado').value)});
            renderVistaPrevia();
        }catch(e){
            errorFinanciero(e);
        }
    });
    actualizarBotonesFinancieros();
    let titulo = elemento('titulo-vista-previa');
    if(titulo && registro.calculo){
        titulo.focus();
    }
}

const tarjeta = (etiqueta, valor, clase='', destacada=false)=>`<div class="ui-tarjeta${destacada ? ' destacada' : ''}"><span class="ui-cifra-etiqueta">${etiqueta}</span><span class="ui-cifra ${clase}">${valor}</span></div>`;

const renderVistaPrevia = function(){
    let c = registro.calculo;
    if(!c){
        elemento('cupoDisponible').innerHTML = '';
        return;
    }
    let tarjetas, detalle = '', pie = c.creado ? 'Proceso creado; los datos financieros ya no se pueden modificar.' : 'El proceso aún no se ha creado.';
    if(c.sinCupo){
        tarjetas = `<div class="ui-tarjetas dos">${tarjeta('Cupo disponible',formatearMoneda(Math.max(0,Number(c.cupo) || 0)),'is-error')}</div>`;
    }else{
        let lista = [tarjeta('Cupo disponible',formatearMoneda(c.cupo),'is-exito')];
        if(c.cuota != null && !c.creado){
            lista.push(tarjeta('Cuota',formatearMoneda(c.cuota)), tarjeta('Seguro',formatearMoneda(c.seguro)));
        }
        lista.push(tarjeta('Cuota total',formatearMoneda(c.cuotaTotal),'',true));
        tarjetas = `<div class="ui-tarjetas${lista.length == 2 ? ' dos' : ''}">${lista.join('')}</div>`;
        detalle = `<p class="ui-vista-previa-detalle">Plazo ${escapeHtml(c.plazo)} meses · Tasa ${formatearPorcentaje(c.tasa)} mensual · Monto ${formatearMoneda(c.monto)}</p>`;
    }
    let alertaSinCupo = c.sinCupo && !c.desactualizado ? alerta('error','fa-circle-exclamation','El cliente no tiene cupo disponible con esta pagaduría. Revisa las deducciones o cambia la pagaduría.',`<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="revisarDeducciones()"><i class="fas fa-pen" aria-hidden="true"></i><span>Revisar deducciones</span></button><button type="button" class="btn btn-sm btn-outline-danger ui-btn" onclick="cerrarRechazado(this)"><i class="fas fa-ban" aria-hidden="true"></i><span>Cerrar como rechazado</span></button>`,'alert') : '';
    let badge = c.creado ? '<span class="ui-badge ui-badge-activo">Proceso creado</span>' : (c.desactualizado ? '<span class="ui-badge ui-badge-adv">Desactualizada</span>' : '<span class="ui-badge ui-badge-info">Vista previa</span>');
    elemento('cupoDisponible').innerHTML = `<section class="ui-vista-previa${c.desactualizado ? ' is-desactualizada' : ''}" aria-labelledby="titulo-vista-previa">
            <div class="ui-vista-previa-cab">
                <h3 class="ui-cifra-etiqueta mb-0" id="titulo-vista-previa" tabindex="-1">Resultado del cálculo</h3>
                ${badge}
            </div>
            ${c.desactualizado ? alerta('info','fa-rotate','Los datos cambiaron; vuelve a calcular.') : ''}
            ${tarjetas}
            ${detalle}
            ${alertaSinCupo}
            ${c.sinCupo ? '' : `<p class="ui-vista-previa-pie">${pie}</p>`}
        </section>`;
}

const actualizarBotonesFinancieros = function(){
    let c = registro.calculo;
    let calcular = elemento('btnSendValues'), confirmar = elemento('btnEditState');
    let creado = registro.estadoProceso >= 2;
    let vigente = c && !c.desactualizado && !c.sinCupo;
    calcular.classList.toggle('d-none', creado);
    let secundario = vigente || (c && c.sinCupo && !c.desactualizado);
    calcular.classList.toggle('btn-primary', !secundario);
    calcular.classList.toggle('ui-btn-sec', !!secundario);
    calcular.querySelector('span:last-child').textContent = c ? 'Recalcular' : 'Calcular';
    confirmar.classList.toggle('d-none', !creado && !(c && !c.sinCupo));
    confirmar.disabled = !creado && !vigente;
    confirmar.querySelector('span').textContent = creado ? 'Continuar' : 'Confirmar y continuar';
}

const desactualizarCalculo = function(){
    let c = registro.calculo;
    if(!c || c.desactualizado || c.creado){
        return;
    }
    c.desactualizado = true;
    renderVistaPrevia();
    actualizarBotonesFinancieros();
}

const modoFinanciero = function(){
    let creado = registro.estadoProceso >= 2;
    elemento('form-datos-financieros').querySelectorAll('#form-financiero input, #form-financiero select, .input-rubro').forEach(campo=>{
        if(creado){
            campo.disabled = true;
        }else if(campo.id != 'numeroCuotas'){
            campo.disabled = false;
        }
    });
    let aviso = creado ? alerta('info','fa-lock','El proceso ya fue creado; los datos financieros no se pueden modificar.') : (registro.avisoReintento ? alerta('info','fa-circle-info',escapeHtml(registro.avisoReintento)) : '');
    elemento('avisoFinanciero').innerHTML = aviso;
    renderVistaPrevia();
    actualizarBotonesFinancieros();
}

const revisarDeducciones = function(){
    let rubro = document.querySelector('.input-rubro');
    (rubro || elemento('ingresosMensuales')).focus();
}

const cerrarRechazado = function(boton){
    Swal.fire({
        title:'¿Cerrar el registro como rechazado?',
        text:'Se creará el proceso con estado rechazado por falta de cupo. Esta acción no se puede deshacer.',
        icon:'warning',
        showCancelButton:true,
        reverseButtons:true,
        focusCancel:true,
        confirmButtonText:'Cerrar como rechazado',
        cancelButtonText:'Cancelar',
        confirmButtonColor:'#A3391F'
    }).then(async valor=>{
        if(!valor.isConfirmed){
            return;
        }
        await cargando(boton,'Cerrando…', async ()=>{
            try{
                let res = await makeOptionsFetch(`${globalUrl}/guardar-datos-financieros`,datosFinancieros(),'post',tokenFinancieros(),true);
                registro.idProceso = String(res.idProceso);
                if(res.res == 'sinCupo'){
                    registro.sucio = false;
                    mostrarFinal('rechazado');
                }else{
                    registro.estadoProceso = 1;
                    registro.calculo = Object.assign(res,{monto:numeroLimpio(elemento('valorSolicitado').value)});
                    renderVistaPrevia();
                    actualizarBotonesFinancieros();
                    actualizarUrl();
                }
            }catch(e){
                errorFinanciero(e);
            }
        });
        actualizarBotonesFinancieros();
    });
}

const confirmarFinancieros = async function(boton){
    if(registro.estadoProceso >= 2){
        irAPaso(3);
        return;
    }
    if(!validarFinancieros()){
        return;
    }
    await cargando(boton,'Creando proceso…', async ()=>{
        try{
            let res = await makeOptionsFetch(`${globalUrl}/guardar-datos-financieros`,datosFinancieros(),'post',tokenFinancieros(),true);
            registro.idProceso = String(res.idProceso);
            if(res.res == 'sinCupo'){
                registro.sucio = false;
                mostrarFinal('rechazado');
                return;
            }
            registro.estadoProceso = 1;
            let datos = new FormData();
            datos.append('idProceso',res.idProceso);
            datos.append('estado','pendiente');
            await makeOptionsFetch(`${globalUrl}/editar-estado-proceso`,datos,'post',tokenFinancieros(),true);
            registro.estadoProceso = 2;
            registro.calculo = {creado:true, cupo:res.cupo, cuotaTotal:res.cuotaTotal, tasa:res.tasa, plazo:res.plazo, monto:numeroLimpio(elemento('valorSolicitado').value)};
            registro.tratamiento = {};
            registro.sucio = false;
            notificar('Proceso creado');
            irAPaso(3);
        }catch(e){
            actualizarUrl();
            errorFinanciero(e);
        }
    });
    actualizarBotonesFinancieros();
}

const fechaHora = function(texto){
    let m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(texto || '');
    return m ? `el ${m[3]}/${m[2]}/${m[1]} a las ${m[4]}:${m[5]}` : '';
}

const tamanoArchivo = function(bytes){
    return bytes >= 1048576 ? `${(bytes/1048576).toLocaleString('es-CO',{maximumFractionDigits:1})} MB` : `${Math.max(1,Math.round(bytes/1024)).toLocaleString('es-CO')} KB`;
}

const elegirMetodo = function(metodo){
    registro.metodo = metodo;
    renderTratamiento();
}

const renderTratamiento = function(){
    let t = registro.tratamiento;
    if(!registro.metodo){
        registro.metodo = t.documentoCargado ? 'pdf' : ((t.enviado || t.aceptado) ? 'email' : '');
    }
    elemento('opcionCorreo').checked = registro.metodo == 'email';
    elemento('opcionDocumento').checked = registro.metodo == 'pdf';
    elemento('badgeCorreo').innerHTML = t.aceptado ? '<span class="ui-badge ui-badge-activo">Aceptado por el cliente</span>' : (t.enviado ? '<span class="ui-badge ui-badge-info">Correo enviado</span>' : '');
    elemento('badgeDocumento').innerHTML = t.documentoCargado ? '<span class="ui-badge ui-badge-activo">Documento cargado</span>' : '';
    elemento('panelCorreo').classList.toggle('d-none', registro.metodo != 'email');
    elemento('panelDocumento').classList.toggle('d-none', registro.metodo != 'pdf');
    renderPanelCorreo();
    renderPanelDocumento();
    let listo = !!(t.enviado || t.documentoCargado || t.aceptado);
    elemento('btnFinalizar').disabled = !listo;
    elemento('ayudaFinalizar').classList.toggle('d-none', listo);
}

const renderPanelCorreo = function(error=''){
    let t = registro.tratamiento;
    let correo = elemento('emailTercero').value.trim();
    let html = error ? alerta('error','fa-circle-exclamation',escapeHtml(error),'','alert') : '';
    if(t.aceptado){
        html += alerta('exito','fa-circle-check','El cliente aceptó el tratamiento de datos desde el enlace enviado a su correo.');
    }else if(t.enviado){
        html += alerta('exito','fa-circle-check',`Correo enviado a <strong>${escapeHtml(t.enviadoA || correo)}</strong> ${fechaHora(t.fechaEnvio)}. El cliente debe aceptar desde el enlace.`,`<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="enviarEmail(this)"><i class="fas fa-paper-plane" aria-hidden="true"></i><span>Reenviar correo</span></button>`);
    }else if(!correo){
        html += alerta('adv','fa-triangle-exclamation','El cliente no tiene correo registrado.',`<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="irAPaso(1,'emailTercero')"><i class="fas fa-plus" aria-hidden="true"></i><span>Agregar correo</span></button>`);
    }
    let destino = !t.aceptado && !t.enviado && correo ? `<div class="ui-destinatario">
                <div class="ui-destinatario-texto"><span class="ui-cifra-etiqueta">Se enviará a</span><strong>${escapeHtml(correo)}</strong></div>
                <div class="ui-acciones">
                    <button type="button" class="btn ui-btn ui-btn-sec" onclick="irAPaso(1,'emailTercero')"><i class="fas fa-pen" aria-hidden="true"></i><span>Cambiar correo</span></button>
                    <button type="button" class="btn btn-primary ui-btn" onclick="enviarEmail(this)"><i class="fas fa-envelope" aria-hidden="true"></i><span>Enviar correo</span></button>
                </div>
            </div>` : '';
    elemento('alertasCorreo').innerHTML = html;
    elemento('destinoCorreo').innerHTML = destino;
}

const renderPanelDocumento = function(){
    let t = registro.tratamiento, a = registro.archivo;
    elemento('enlaceFormato').href = `${globalUrl}/formato-tratamiento-datos/${encodeURIComponent(registro.idProceso)}`;
    let mostrarForm = !t.documentoCargado || registro.reemplazando;
    elemento('form-file-tratamiento').classList.toggle('d-none', !mostrarForm);
    let acciones = `${a ? `<a href="${a.url}" target="_blank" rel="noopener" class="btn btn-sm ui-btn ui-btn-sec"><i class="fas fa-eye" aria-hidden="true"></i><span>Ver documento</span></a>` : ''}<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="reemplazarDocumento()"><i class="fas fa-arrows-rotate" aria-hidden="true"></i><span>Reemplazar</span></button>`;
    elemento('estadoDocumento').innerHTML = mostrarForm ? '' : alerta('exito','fa-circle-check',a ? `Documento cargado: <strong>${escapeHtml(a.nombre)}</strong> (${tamanoArchivo(a.tamano)}) el ${a.fecha}.` : 'El documento firmado ya fue cargado.',acciones);
}

const reemplazarDocumento = function(){
    registro.reemplazando = true;
    renderPanelDocumento();
    elemento('fileTratamientoDatos').focus();
}

const subirArchivoTratamientoDatos = async function(e){
    e.preventDefault();
    let input = elemento('fileTratamientoDatos');
    let archivo = input.files && input.files[0];
    let mensaje = !archivo ? 'Selecciona el archivo PDF.' : ((!/\.pdf$/i.test(archivo.name) || (archivo.type && archivo.type != 'application/pdf')) ? 'El archivo debe ser PDF.' : (archivo.size > TAMANO_MAXIMO_PDF ? `El archivo supera 2 MB (${tamanoArchivo(archivo.size)}).` : ''));
    marcarError('fileTratamientoDatos',mensaje);
    if(mensaje){
        input.focus();
        return;
    }
    await cargando(elemento('btnCargarDocumento'),'Cargando…', async ()=>{
        let datos = new FormData(elemento('form-file-tratamiento'));
        datos.append('idTercero',registro.idTercero);
        datos.append('idProceso',registro.idProceso);
        try{
            let res = await makeOptionsFetch(`${globalUrl}/subir-archivo-tratamiento-datos`,datos,'post',tokenTratamiento(),true);
            if(res.errors){
                marcarError('fileTratamientoDatos', typeof res.errors == 'string' ? res.errors : [].concat(Object.values(res.errors)[0])[0]);
                return;
            }
            let hoy = new Date();
            registro.archivo = {nombre:archivo.name, tamano:archivo.size, fecha:`${String(hoy.getDate()).padStart(2,'0')}/${String(hoy.getMonth()+1).padStart(2,'0')}/${hoy.getFullYear()}`, url:URL.createObjectURL(archivo)};
            registro.tratamiento.documentoCargado = true;
            registro.reemplazando = false;
            registro.sucio = false;
            input.value = '';
            notificar('Documento cargado');
        }catch(err){
            marcarError('fileTratamientoDatos', mensajeErrorHttp(err.data));
        }
    });
    renderTratamiento();
}

const enviarEmail = async function(boton){
    let error = '';
    await cargando(boton,'Enviando…', async ()=>{
        let datos = new FormData();
        datos.append('idProceso',registro.idProceso);
        try{
            let res = await makeOptionsFetch(`${globalUrl}/enviar-email-aprobacion-datos`,datos,'post',tokenTratamiento(),true);
            if(res.res == 'ok'){
                Object.assign(registro.tratamiento,{enviado:true, enviadoA:res.enviadoA, fechaEnvio:res.fechaEnvio});
                notificar('Correo enviado');
            }
        }catch(e){
            error = mensajeErrorHttp(e.data);
        }
    });
    renderTratamiento();
    if(error){
        renderPanelCorreo(error);
    }
}

const finalizarRegistro = function(){
    let t = registro.tratamiento;
    registro.sucio = false;
    mostrarFinal(t.documentoCargado || t.aceptado ? 'exito' : 'pendiente');
}

const mostrarFinal = function(variante, mensaje=''){
    registro.final = variante;
    let nombre = escapeHtml(`${elemento('nombres').value.trim()} ${elemento('apellidos').value.trim()}`.trim());
    let correo = escapeHtml(registro.tratamiento.enviadoA || elemento('emailTercero').value.trim());
    let datos = {
        exito:['is-exito','fa-circle-check','Registro completo', mensaje ? escapeHtml(mensaje) : `El proceso de ${nombre} quedó creado. Próximo paso: consulta en centrales de riesgo.`],
        pendiente:['is-adv','fa-clock','Registro completo: pendiente de aceptación',`El proceso de ${nombre} quedó creado. Cuando el cliente acepte desde el enlace enviado a ${correo}, continuará la consulta en centrales de riesgo.`],
        rechazado:['is-error','fa-circle-xmark','Registro cerrado sin cupo','El proceso quedó registrado como rechazado por falta de cupo.'],
        cerrado:['is-error','fa-circle-xmark','Registro cerrado como rechazado','El proceso quedó registrado como rechazado.']
    }[variante];
    let c = contenedor();
    let final = elemento('data-finished');
    final.innerHTML = `<div class="ui-resultado ${datos[0]}">
            <i class="fas ${datos[1]} ui-resultado-icono" aria-hidden="true"></i>
            <h2 class="ui-resultado-titulo" id="titulo-final" tabindex="-1">${datos[2]}</h2>
            <p class="ui-vacio-texto">${datos[3]}</p>
            <div class="ui-acciones ui-resultado-acciones">
                <a href="${c.dataset.urlProcesos}" class="btn btn-primary ui-btn"><i class="fas fa-list-check" aria-hidden="true"></i><span>Ir a la bandeja de procesos</span></a>
                <a href="${c.dataset.urlRegistro}" class="btn ui-btn ui-btn-sec"><i class="fas fa-user-plus" aria-hidden="true"></i><span>Registrar otro cliente</span></a>
            </div>
        </div>`;
    SECCIONES.forEach(id=>elemento(id).classList.add('d-none'));
    final.classList.remove('d-none');
    renderStepper(0);
    actualizarUrl();
    document.title = `${datos[2]} · Registro de cliente`;
    elemento('anuncioPaso').textContent = datos[2];
    elemento('titulo-final').focus();
}
