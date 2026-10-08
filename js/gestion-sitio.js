window.onload = function(){
    let rol = document.getElementById('rol-usuario-validar');
    if(rol.value != 1){
        document.getElementById('card-roles').addEventListener('click', function(){
            Swal.fire('Oops!','Esta sección es solo editable para un administrador','info')
        });
        document.querySelector('#card-roles input').setAttribute('disabled', 'disabled');
        document.querySelector('#card-roles select').setAttribute('disabled', 'disabled');
        let buttons = document.querySelectorAll('#card-roles button');
        for(let i = 0; i < buttons.length; i++){
            buttons[i].setAttribute('disabled', 'disabled');
        }
    }
}
/**Mostrar datos de rol seleccionado en formulario */
const gestionRol = async function(accion,idRol=''){
    document.getElementById('inputNuevoRol').style.display = 'block';
    let dataToSend = new FormData();
    let htmlSubmeus = '';
    if(accion == 'nuevo'){
        dataToSend.append('IdRol','');
        let res = await makeOptionsFetch(`${globalUrl}/submenus`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'));
        document.getElementById('nombreRol').value = '';
        res.submenus.forEach(element=>{
            if(element.NombreSubmenu != 'Cerrar sesión'){
                input = `<input type="checkbox" class="checkboxRol" id="submenu-${element.IdSubmenu}" name="submenu-${element.IdSubmenu}">`;
                htmlSubmeus += `<div class="d-flex align-items-center justify-content-between my-1">
                                    <label for="">${element.NombreSubmenu}</label>
                                    ${input}
                                </div>`;
            }
        });
        document.getElementById('btnGuardarRol').innerHTML = 'Guardar';
        document.getElementById('submenusRol').innerHTML = htmlSubmeus;
    }else if(accion == 'editar'){
        dataToSend.append('peticion','roles'); dataToSend.append('idRol',idRol);
        let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-admin`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'));
        console.log(res);
        document.getElementById('nombreRol').value = res['rol'][0].NombreRol;
        res['submenusTodos'].forEach(async function(element){
            if(element.NombreSubmenu != 'Cerrar sesión'){
                let input = `<input type="checkbox" class="checkboxRol" id="submenu-${element.IdSubmenu}" name="submenu-${element.IdSubmenu}">`;
                htmlSubmeus += `<div class="d-flex align-items-center justify-content-between my-1">
                                    <label for="">${element.NombreSubmenu}</label>
                                    ${input}
                                </div>`;
            }
        });
        document.getElementById('btnGuardarRol').innerHTML = 'Actualizar';
        document.getElementById('submenusRol').innerHTML = htmlSubmeus;
        let inputs = document.getElementsByClassName('checkboxRol');
        for(let i=0; i <= inputs.length; i++){
            res['submenusRol'].forEach(function(item2){
                if(item2.IdSubmenu == inputs[i].getAttribute('id').split('-')[1]){
                    inputs[i].checked = true;
                }
            });
        }
    }
    document.getElementById('cancelRol').style.display = 'inline-block';
}
const guardarRol = async function(e){
    e.preventDefault();
    let inputs = document.getElementsByClassName('checkboxRol');
    let submenusSeleccionados = [];
    let submenusEliminados = [];
    for(let i = 0; i < inputs.length; i++){
        if(inputs[i].checked){
            let idSubmenu = inputs[i].getAttribute('id').split('-')[1];
            submenusSeleccionados.push(idSubmenu);
        }else{
            let idSubmenu = inputs[i].getAttribute('id').split('-')[1];
            submenusEliminados.push(idSubmenu);
        }
    }
    let dataToSend = new FormData();
    dataToSend.append('idRol',document.getElementById('listaRoles').value);
    dataToSend.append('submenus',JSON.stringify(submenusSeleccionados));
    dataToSend.append('submenusEliminados',JSON.stringify(submenusEliminados));
    dataToSend.append('option','roles');
    let res = await makeOptionsFetch(`${globalUrl}/guardar-datos-sitio`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'));
    if(res.res == 'ok'){
        Swal.fire({title:'Perfecto!',text:'Permisos modificados correctamente',icon:'success',confirmButtonText:'Entendido'})
        .then((value)=>{
            if(value.isConfirmed){
                cancelarGuardado('rol');
            }
        });
    }else{
        Swal.fire({title:'Oops!',text:'Surgieron problemas al intentar agregar un permiso',icon:'error',confirmButtonText:'Entendido',allowEscapeKey:false,alloOutsideClick:false})
        .then((value)=>{
            if(value.isConfirmed){
                cancelarGuardado('rol');
            }
        });
    }
}
let tipoVariable = '';
const tipoDeVariable = function(nombre){
    return {salariominimomensual:'smmlv',tasainteres:'tasa'}[String(nombre || '').trim().toLowerCase()] || '';
}
const limpiarErroresVariable = function(){
    ['nombreVariable','valorVariable'].forEach(campo=>{
        document.getElementById(campo).classList.remove('is-invalid');
        document.getElementById('error-'+campo).textContent = '';
    });
}
const mostrarErroresVariable = function(errores){
    Object.keys(errores).forEach(campo=>{
        let input = document.getElementById(campo);
        if(input){
            input.classList.add('is-invalid');
            document.getElementById('error-'+campo).textContent = [].concat(errores[campo])[0];
        }
    });
}
const ayudaValorVariable = function(){
    let valor = document.getElementById('valorVariable').value;
    let texto = '';
    if(tipoVariable == 'tasa'){
        let i = Number(valor.replace(',','.'))/100;
        texto = valor !== '' && !isNaN(i) ? 'Tasa mensual; equivale a '+formatearPorcentaje((Math.pow(1+i,12)-1)*100)+' efectivo anual' : 'Tasa mensual, entre 0,01 y 10';
    }else if(tipoVariable == 'smmlv'){
        texto = 'Valor en pesos, sin decimales';
    }
    document.getElementById('ayuda-valorVariable').textContent = texto;
}
const configurarCampoValor = function(valor){
    let input = document.getElementById('valorVariable');
    document.getElementById('prefijoValor').classList.toggle('d-none',tipoVariable != 'smmlv');
    document.getElementById('sufijoValor').classList.toggle('d-none',tipoVariable != 'tasa');
    input.inputMode = tipoVariable == 'smmlv' ? 'numeric' : (tipoVariable == 'tasa' ? 'decimal' : 'text');
    input.value = tipoVariable == 'smmlv' ? (valor === '' ? '' : formatearMoneda(valor,false)) : (tipoVariable == 'tasa' ? String(valor).replace('.',',') : valor);
    ayudaValorVariable();
}
const formatearValorVariable = function(input){
    if(tipoVariable == 'smmlv'){
        formatearInputMoneda(input);
    }else if(tipoVariable == 'tasa'){
        input.value = input.value.replace(/\./g,',').replace(/[^\d,]/g,'').replace(/,(?=.*,)/g,'');
    }
    input.classList.remove('is-invalid');
    ayudaValorVariable();
}
const marcarFilaVariable = function(idVariable){
    document.querySelectorAll('#listaVariables tbody tr').forEach(tr=>tr.classList.toggle('ui-fila-edicion',tr.dataset.variable == idVariable));
}
/**Mostrar datos de variable seleccionada en formulario */
const editarValorVariable = async function(idVariable){
    let dataToSend = new FormData();
    dataToSend.append('peticion','variables'); dataToSend.append('idVariable',idVariable);
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-admin`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'));
    limpiarErroresVariable();
    let nombre = document.getElementById('nombreVariable');
    nombre.value = String(res[0].NombreValorVariable).trim();
    tipoVariable = tipoDeVariable(nombre.value);
    nombre.readOnly = tipoVariable != '';
    document.getElementById('ayuda-nombreVariable').classList.toggle('d-none',tipoVariable == '');
    configurarCampoValor(String(res[0].ValorVariable).trim());
    document.getElementById('tituloFormVariable').textContent = 'Editar variable';
    document.getElementById('textoGuardarVariable').textContent = 'Guardar cambios';
    document.getElementById('idVariable').value = idVariable;
    marcarFilaVariable(idVariable);
    document.getElementById(tipoVariable ? 'valorVariable' : 'nombreVariable').focus();
}
/**Limpiar inputs de valores traidos */
const cancelarGuardado = function(section){
    switch (section){
        case 'rol':
            document.getElementById('nombreRol').value = '';
            document.getElementById('submenusRol').innerHTML = '';
            document.getElementById('btnGuardarRol').innerHTML = 'Guardar';
            document.getElementById('cancelRol').style.display = 'none';
            document.getElementById('inputNuevoRol').style.display = 'none';
            $('#listaRoles option[value="0"]').prop('selected', true);
        break;
        case 'variable':
            limpiarErroresVariable();
            tipoVariable = '';
            document.getElementById('nombreVariable').value = '';
            document.getElementById('nombreVariable').readOnly = false;
            document.getElementById('ayuda-nombreVariable').classList.add('d-none');
            configurarCampoValor('');
            document.getElementById('tituloFormVariable').textContent = 'Nueva variable';
            document.getElementById('textoGuardarVariable').textContent = 'Crear variable';
            document.getElementById('idVariable').value = '';
            marcarFilaVariable('');
        break;
    }
}
/**Guardar datos */
const guardarVariable = async function(e){
    e.preventDefault();
    limpiarErroresVariable();
    let nombre = document.getElementById('nombreVariable').value.trim();
    let valor = document.getElementById('valorVariable').value.trim();
    let errores = {};
    if(tipoVariable == 'smmlv'){
        valor = numeroLimpio(valor);
        if(!(Number(valor) > 0)){
            errores.valorVariable = 'Ingresa un valor entero mayor a cero.';
        }
    }else if(tipoVariable == 'tasa'){
        valor = valor.replace(',','.');
        if(valor === '' || isNaN(valor) || Number(valor) < 0.01 || Number(valor) > 10){
            errores.valorVariable = 'Ingresa una tasa mensual entre 0,01 y 10 %.';
        }
    }else if(valor === ''){
        errores.valorVariable = 'Ingresa el valor de la variable.';
    }
    if(nombre === ''){
        errores.nombreVariable = 'Ingresa el nombre de la variable.';
    }
    if(Object.keys(errores).length){
        mostrarErroresVariable(errores);
        return;
    }
    let dataToSend = new FormData();
    dataToSend.append('nombreVariable',nombre); dataToSend.append('valorVariable',valor);
    dataToSend.append('idVariable', document.getElementById('idVariable').value);
    dataToSend.append('option','variables');
    try{
        let res = await makeOptionsFetch(`${globalUrl}/guardar-datos-sitio`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'),true);
        if(res == 'ok'){
            notificar('Variable guardada');
            setTimeout(()=>location.reload(),900);
        }else if(res.errors){
            mostrarErroresVariable(res.errors);
        }else{
            mostrarErrorHttp({message:'No fue posible guardar la variable. Intenta de nuevo.'},422);
        }
    }catch(error){
        if(error.data && error.data.errors){
            mostrarErroresVariable(error.data.errors);
        }else if(error.status && error.status != 403){
            mostrarErrorHttp(error.data,error.status);
        }
    }
}
let centralesConfig = null;
const AMBIENTES_CENTRAL = {pruebas:['adv','Pruebas (UAT)'], produccion:['info','Producción'], simulado:['adv','Pruebas']};
const peticionCentrales = function(ruta, datos={}){
    let fd = new FormData();
    Object.entries(datos).forEach(([clave,valor])=>fd.append(clave, valor == null ? '' : valor));
    return makeOptionsFetch(`${globalUrl}/${ruta}`, fd, 'post', document.querySelector('meta[name="csrf-token-admin-management"]').content, true);
}
const alertaCentrales = (tipo, icono, texto, accion='') => `<div class="ui-alerta ui-alerta-${tipo}"><i class="fas ${icono}" aria-hidden="true"></i><span class="ui-alerta-texto">${texto}</span>${accion ? `<div class="ui-alerta-accion">${accion}</div>` : ''}</div>`;
const fechaCentrales = function(texto){
    let m = String(texto || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    return m ? `<time datetime="${escapeHtml(String(texto).replace(' ','T'))}"${m[4] ? ` title="${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}"` : ''}>${m[3]}/${m[2]}/${m[1]}</time>` : 'Sin consultas';
}
const limpiarErrorCentrales = function(campo){
    let input = document.getElementById(campo), error = document.getElementById('error-'+campo);
    if(input){
        input.classList.remove('is-invalid');
    }
    error.textContent = '';
    error.classList.remove('d-block');
}
const errorCentrales = function(campo, mensaje){
    let input = document.getElementById(campo), error = document.getElementById('error-'+campo);
    if(input){
        input.classList.add('is-invalid');
    }
    error.textContent = mensaje;
    error.classList.add('d-block');
}
const cargarConfigCentrales = async function(){
    let cuerpo = document.getElementById('filasCentrales');
    cuerpo.innerHTML = '<tr><td colspan="7"><div class="ui-vacio" aria-busy="true"><span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span><p class="ui-vacio-titulo mt-2">Cargando proveedores…</p></div></td></tr>';
    try{
        pintarConfigCentrales(await peticionCentrales('centrales-config-listar'));
    }catch(e){
        cuerpo.innerHTML = `<tr><td colspan="7">${alertaCentrales('error','fa-circle-exclamation','No fue posible cargar los proveedores de centrales.','<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="cargarConfigCentrales()"><i class="fas fa-rotate-right" aria-hidden="true"></i><span>Reintentar</span></button>')}</td></tr>`;
    }
}
const filaCentral = function(p){
    let k = escapeHtml(p.clave), simulado = p.clave == 'simulado', sinCredenciales = !simulado && !p.configurado;
    let partes = String(p.nombre).match(/^(.*?)\s*\((.+)\)$/) || [null, p.nombre, ''];
    let nombre = escapeHtml(simulado ? partes[1] : p.nombre), principal = escapeHtml(partes[1]);
    let ayuda = sinCredenciales ? ` aria-describedby="ayuda-central-${k}"` : '';
    let describe = ` aria-describedby="error-predeterminadoCentral${sinCredenciales ? ` ayuda-central-${k}` : ''}"`;
    let amb = AMBIENTES_CENTRAL[p.ambiente];
    let credenciales = simulado ? '<span class="ui-badge ui-badge-inactivo">No requiere</span>' : (p.configurado ? '<span class="ui-badge ui-badge-activo"><i class="fas fa-check" aria-hidden="true"></i> Configuradas</span>' : '<span class="ui-badge ui-badge-adv"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Sin configurar</span>');
    let habilitado = p.editable ? `<div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="habilitado-${k}"${p.habilitado ? ' checked' : ''} onchange="cambioHabilitadoCentral('${k}')"><label class="form-check-label visually-hidden" for="habilitado-${k}">Habilitar ${nombre}</label></div>` : '<span class="ui-badge ui-badge-activo">Activo en el servidor</span>';
    return `<tr>
        <td class="ui-celda-principal">${simulado ? `${nombre} <span class="ui-badge ui-badge-adv">Solo pruebas</span>` : principal}${!simulado && partes[2] ? `<span class="ui-campo-ayuda m-0">${escapeHtml(partes[2])}</span>` : ''}${p.pendienteValidar ? `<span class="ui-campo-ayuda">${escapeHtml(p.pendienteValidar)}</span>` : ''}${sinCredenciales ? `<span class="ui-campo-ayuda" id="ayuda-central-${k}">Configura las credenciales en el servidor (.env) para usarlo como predeterminado.</span>` : ''}</td>
        <td data-label="Habilitado">${habilitado}</td>
        <td data-label="Credenciales">${credenciales}</td>
        <td data-label="Ambiente">${amb ? `<span class="ui-badge ui-badge-${amb[0]}">${amb[1]}</span>` : '—'}</td>
        <td data-label="Última consulta">${fechaCentrales(p.ultimaConsulta && p.ultimaConsulta.fecha)}${p.ultimaConsulta && p.ultimaConsulta.usuario ? `<span class="ui-campo-ayuda m-0">${escapeHtml(p.ultimaConsulta.usuario)}</span>` : ''}</td>
        <td data-label="Predeterminado"><div class="form-check ui-radio-tactil m-0"><input class="form-check-input" type="radio" name="proveedorDefecto" id="predet-${k}" value="${k}"${p.predeterminado ? ' checked' : ''}${!p.configurado || !p.habilitado ? ' disabled' : ''}${describe} onchange="limpiarErrorCentrales('predeterminadoCentral')"><label class="form-check-label" for="predet-${k}"><span class="visually-hidden">Usar ${nombre} como predeterminado</span><span class="ui-radio-tactil-texto" aria-hidden="true">Usar como predeterminado</span></label></div></td>
        <td class="ui-celda-accion text-end"><button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="probarConexionCentral('${k}', this)"${sinCredenciales ? ' disabled' : ''}${ayuda}><i class="fas fa-plug" aria-hidden="true"></i><span>Probar conexión</span><span class="visually-hidden"> con ${nombre}</span></button></td>
    </tr>
    <tr class="ui-fila-prueba" id="fila-prueba-${k}" hidden><td colspan="7"><p class="ui-prueba m-0" id="prueba-${k}" aria-live="polite"></p></td></tr>`;
}
const pintarConfigCentrales = function(res){
    centralesConfig = res;
    let visibles = res.proveedores.filter(p=>p.clave != 'simulado' || res.simuladoHabilitado);
    let actual = res.proveedores.find(p=>p.predeterminado);
    let avisos = '';
    if(!visibles.some(p=>p.editable && p.configurado)){
        avisos += alertaCentrales('adv','fa-triangle-exclamation','Ningún proveedor tiene credenciales configuradas. Configúralas en el servidor (.env) para consultar centrales de riesgo.');
    }
    if(actual && !res.predeterminadoDisponible){
        avisos += alertaCentrales('adv','fa-triangle-exclamation',`El proveedor predeterminado (${escapeHtml(actual.nombre)}) no está disponible. Elige otro o deja ninguno.`);
    }
    document.getElementById('avisoCentrales').innerHTML = avisos;
    document.getElementById('filasCentrales').innerHTML = visibles.map(filaCentral).join('');
    document.getElementById('predet-ninguno').checked = !actual;
    document.getElementById('vigenciaCentrales').value = res.vigenciaDias;
    ['predeterminadoCentral','vigenciaCentrales'].forEach(limpiarErrorCentrales);
}
const cambioHabilitadoCentral = function(clave){
    let p = centralesConfig.proveedores.find(x=>x.clave == clave);
    document.getElementById('predet-'+clave).disabled = !p.configurado || !document.getElementById('habilitado-'+clave).checked;
    limpiarErrorCentrales('predeterminadoCentral');
}
const cancelarCentrales = function(){
    if(centralesConfig){
        pintarConfigCentrales(centralesConfig);
    }
}
const cargandoCentrales = async function(boton, texto, accion){
    let original = boton.innerHTML;
    boton.disabled = true;
    boton.classList.add('is-cargando');
    boton.innerHTML = `<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>${texto}</span>`;
    try{
        return await accion();
    }finally{
        boton.innerHTML = original;
        boton.disabled = false;
        boton.classList.remove('is-cargando');
    }
}
const guardarCentrales = async function(e){
    e.preventDefault();
    if(!centralesConfig){
        return;
    }
    let vigencia = document.getElementById('vigenciaCentrales').value.trim();
    let radio = document.querySelector('input[name="proveedorDefecto"]:checked'), predeterminado = radio ? radio.value : '';
    let interruptor = predeterminado ? document.getElementById('habilitado-'+predeterminado) : null;
    let errores = {};
    if(interruptor && !interruptor.checked){
        errores.predeterminadoCentral = 'El proveedor predeterminado debe estar habilitado.';
    }
    if(!/^\d+$/.test(vigencia) || Number(vigencia) < 1 || Number(vigencia) > 365){
        errores.vigenciaCentrales = 'Ingresa un número de días entre 1 y 365.';
    }
    Object.entries(errores).forEach(([campo,mensaje])=>errorCentrales(campo, mensaje));
    if(Object.keys(errores).length){
        return document.getElementById(errores.predeterminadoCentral ? 'habilitado-'+predeterminado : 'vigenciaCentrales').focus();
    }
    let datos = {predeterminado:predeterminado, vigenciaDias:Number(vigencia)};
    centralesConfig.proveedores.filter(p=>p.editable).forEach(p=>{
        let control = document.getElementById('habilitado-'+p.clave);
        datos[`habilitados[${p.clave}]`] = (control ? control.checked : p.habilitado) ? 1 : 0;
    });
    await cargandoCentrales(document.getElementById('btnGuardarCentrales'), 'Guardando…', async ()=>{
        try{
            pintarConfigCentrales(await peticionCentrales('centrales-config-guardar', datos));
            notificar('Configuración de centrales guardada');
        }catch(error){
            let errores = (error.data && error.data.errors) || {};
            if(errores.predeterminado){
                errorCentrales('predeterminadoCentral', [].concat(errores.predeterminado)[0]);
            }else if(errores.vigenciaDias){
                errorCentrales('vigenciaCentrales', [].concat(errores.vigenciaDias)[0]);
            }else if(error.status != 403){
                mostrarErrorHttp(error.data, error.status);
            }
        }
    });
}
const probarConexionCentral = async function(clave, boton){
    let fila = document.getElementById('fila-prueba-'+clave), salida = document.getElementById('prueba-'+clave);
    fila.hidden = false;
    salida.className = 'ui-prueba m-0';
    salida.textContent = 'Probando conexión…';
    let inicio = performance.now();
    let res = await cargandoCentrales(boton, 'Probando…', async ()=>{
        try{
            return await peticionCentrales('centrales-probar-conexion', {proveedor:clave});
        }catch(e){
            return e.status == 403 ? null : {ok:false, mensaje:mensajeErrorHttp(e.data)};
        }
    });
    if(!res){
        fila.hidden = true;
        return;
    }
    let segundos = ((performance.now() - inicio) / 1000).toLocaleString('es-CO', {maximumFractionDigits:1});
    let ahora = new Date(), dos = n => String(n).padStart(2,'0');
    salida.classList.add(res.ok ? 'is-ok' : 'is-error');
    salida.innerHTML = res.ok
        ? `<i class="fas fa-circle-check" aria-hidden="true"></i><span>Conexión exitosa · ${segundos} s · ${dos(ahora.getDate())}/${dos(ahora.getMonth() + 1)} ${dos(ahora.getHours())}:${dos(ahora.getMinutes())}</span>`
        : `<i class="fas fa-circle-xmark" aria-hidden="true"></i><span>No fue posible conectar: ${escapeHtml(res.mensaje || 'error desconocido')}</span>`;
}
if(document.getElementById('form-centrales')){
    cargarConfigCentrales();
}
