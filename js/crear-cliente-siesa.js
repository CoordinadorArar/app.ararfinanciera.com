const ESTADOS_CLIENTE = {pendiente:['Pendiente',2], parcial:['Parcial',3], completo:['Completo',5]};
const COMPONENTES = {tercero:'Tercero', cliente:'Cliente', proveedor:'Proveedor'};
const MARCAS = {pendiente:[null,'inactivo','Pendiente'], curso:['fa-spinner fa-spin','info','En curso'], ok:['fa-check','activo','Completado'], error:['fa-xmark','error','Error'], omitido:['fa-minus','adv','Omitido'], bloqueado:['fa-ban','error','Bloqueado']};
const DEPENDENCIA = /^Requiere que (.+) exista en SIESA\.$/;
const POR_PAGINA = 15;
let bandeja = {estado:'pendiente', q:'', pagina:1, datos:null, envio:false, inicial:true, temporizador:null, peticion:0};
let siesa = {};

const el = id => document.getElementById(id);
const esAdmin = () => el('clientesSiesa').dataset.admin == '1';

const peticion = function(ruta, datos){
    let fd = new FormData();
    Object.entries(datos).forEach(([clave,valor])=>fd.append(clave, valor == null ? '' : valor));
    return makeOptionsFetch(`${globalUrl}/${ruta}`, fd, 'post', document.querySelector('meta[name="csrf-token"]').content, true);
}

const alerta = function(tipo, icono, texto, accion='', rol=''){
    return `<div class="ui-alerta ui-alerta-${tipo}"${rol ? ` role="${rol}"` : ''}><i class="fas ${icono}" aria-hidden="true"></i><span class="ui-alerta-texto">${texto}</span>${accion ? `<div class="ui-alerta-accion ui-acciones">${accion}</div>` : ''}</div>`;
}

const cargador = texto => `<div class="ui-vacio" aria-busy="true"><span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span><p class="ui-vacio-titulo mt-2">${texto}</p></div>`;
const botonSec = (accion, icono, texto) => `<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="${accion}">${icono ? `<i class="fas ${icono}" aria-hidden="true"></i>` : ''}<span>${texto}</span></button>`;
const badgeDeshabilitado = '<span class="ui-badge ui-badge-adv"><i class="fas fa-eye" aria-hidden="true"></i> Envío deshabilitado: solo vista previa</span>';
const nitPlano = r => String(r.nit || '').replace(/^\d+$/, d=>d.replace(/\B(?=(\d{3})+$)/g,'.')) + (r.dv != null && r.dv !== '' ? '-'+r.dv : '');
const fechaPlano = texto => { let m = String(texto || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : ''; };
const fechaHtml = texto => fechaPlano(texto) ? `<time datetime="${escapeHtml(texto)}">${fechaPlano(texto)}</time>` : '—';
const badgeCliente = estado => `<span class="ui-badge ui-badge-estado ui-estado-${(ESTADOS_CLIENTE[estado] || [])[1]}">${(ESTADOS_CLIENTE[estado] || [''])[0]}</span>`;
const componentes = s => `<span class="ui-componentes">${Object.entries(COMPONENTES).map(([k,n])=>`<span class="ui-badge ${s[k] ? 'ui-badge-activo' : 'ui-badge-inactivo'}"><i class="fas ${s[k] ? 'fa-check' : 'fa-minus'}" aria-hidden="true"></i>${n}<span class="visually-hidden"> ${s[k] ? 'creado' : 'pendiente'}</span></span>`).join('')}</span>`;

const cargarBandeja = async function(){
    let destino = el('bandejaClientes'), n = ++bandeja.peticion;
    destino.innerHTML = cargador('Cargando clientes…');
    let datos = {page:bandeja.pagina, perPage:POR_PAGINA, busqueda:bandeja.q};
    if(bandeja.estado){
        datos.estado = bandeja.estado;
    }
    try{
        let res = await peticion('siesa-clientes', datos);
        if(n != bandeja.peticion){
            return;
        }
        if(bandeja.inicial){
            bandeja.inicial = false;
            bandeja.envio = !!res.envioHabilitado;
            el('badgeEnvio').innerHTML = bandeja.envio ? '' : badgeDeshabilitado;
            el('alertaEnvio').innerHTML = bandeja.envio ? '' : alerta('adv','fa-triangle-exclamation','El envío a SIESA está deshabilitado en la configuración. Puede validar los datos y ver los registros que se enviarían, pero no se creará nada en SIESA.','','status');
            if(!res.contadores.pendiente){
                bandeja.estado = '';
                return cargarBandeja();
            }
        }
        if(!res.registros.length && bandeja.pagina > 1){
            bandeja.pagina = 1;
            return cargarBandeja();
        }
        bandeja.datos = res;
        pintarBandeja();
    }catch(e){
        if(n != bandeja.peticion){
            return;
        }
        el('resumenClientes').textContent = '';
        destino.innerHTML = alerta('error','fa-circle-exclamation','No fue posible cargar los clientes.',botonSec('cargarBandeja()','fa-rotate-right','Reintentar'),'alert');
    }
}

const pintarBandeja = function(){
    let res = bandeja.datos, desde = (res.page - 1) * res.perPage + 1;
    pintarContadores(res.contadores);
    el('resumenClientes').textContent = res.total ? `Mostrando ${desde}–${desde + res.registros.length - 1} de ${res.total} ${res.total == 1 ? 'cliente' : 'clientes'}` : 'Sin clientes para mostrar';
    el('bandejaClientes').innerHTML = res.registros.length ? tablaClientes(res.registros) + paginador(res) : vacioBandeja();
}

const pintarContadores = function(c){
    let boton = (valor, texto, total) => `<button type="button" class="ui-contador${valor ? ' ui-estado-'+ESTADOS_CLIENTE[valor][1] : ''}" aria-pressed="${bandeja.estado === valor}" aria-label="${texto}, ${total} ${total == 1 ? 'cliente' : 'clientes'}" onclick="filtrarEstado('${valor}')"><span class="ui-contador-num">${total}</span><span class="ui-contador-et">${texto}</span></button>`;
    el('contadores').innerHTML = boton('','Todos',(c.pendiente || 0) + (c.parcial || 0) + (c.completo || 0)) + Object.keys(ESTADOS_CLIENTE).map(k=>boton(k, ESTADOS_CLIENTE[k][0], c[k] || 0)).join('');
}

const filtrarEstado = function(valor){
    bandeja.estado = valor;
    bandeja.pagina = 1;
    cargarBandeja();
}

const tablaClientes = registros => `<div class="ui-scroll"><table class="ui-tabla ui-tabla-tarjetas">
    <caption class="visually-hidden">Clientes en SIESA${bandeja.estado ? ' en estado '+ESTADOS_CLIENTE[bandeja.estado][0] : ''}</caption>
    <thead><tr><th scope="col">Cliente</th><th scope="col">NIT</th><th scope="col">Fecha</th><th scope="col">Estado en SIESA</th><th scope="col"><span class="visually-hidden">Acción</span></th></tr></thead>
    <tbody>${registros.map(filaCliente).join('')}</tbody>
</table></div>`;

const filaCliente = function(r){
    let primario = bandeja.envio && r.estado != 'completo';
    let texto = !bandeja.envio ? 'Revisar' : (r.estado == 'completo' ? 'Ver detalle' : (r.estado == 'parcial' ? 'Completar en SIESA' : 'Crear en SIESA'));
    return `<tr>
        <td class="ui-celda-principal"><span class="fw-semibold">${escapeHtml(r.nombre)}</span><span class="d-block mt-1">${badgeCliente(r.estado)}</span></td>
        <td data-label="NIT" class="text-nowrap">${escapeHtml(nitPlano(r))}</td>
        <td data-label="Fecha" class="num">${fechaHtml(r.fecha)}</td>
        <td data-label="Estado en SIESA">${componentes(r.siesa)}</td>
        <td class="ui-celda-accion text-end text-nowrap"><button type="button" class="btn btn-sm ui-btn ${primario ? 'btn-primary' : 'ui-btn-sec'}" data-id="${escapeHtml(r.idCliente)}" aria-label="${escapeHtml(texto+': '+r.nombre)}" onclick="abrirSiesa(this)"><i class="fas ${primario ? 'fa-cloud-arrow-up' : 'fa-eye'}" aria-hidden="true"></i><span>${texto}</span></button></td>
    </tr>`;
}

const paginador = function(res){
    let paginas = Math.ceil(res.total / res.perPage), actual = Number(res.page);
    if(paginas <= 1){
        return '';
    }
    let item = (pagina, texto, etiqueta, deshabilitado, activo=false) => `<li class="page-item${deshabilitado ? ' disabled' : ''}${activo ? ' active' : ''}"><button type="button" class="page-link" aria-label="${etiqueta}"${activo ? ' aria-current="page"' : ''}${deshabilitado ? ' disabled' : ''} onclick="irPagina(${pagina})">${texto}</button></li>`;
    let hasta = Math.min(paginas, Math.max(actual + 2, 5)), desde = Math.max(1, hasta - 4), items = [];
    for(let p = desde; p <= hasta; p++){
        items.push(item(p, p, `Página ${p}`, false, p == actual));
    }
    return `<nav class="ui-paginador" aria-label="Paginación de clientes"><ul class="pagination pagination-sm mb-0">${item(actual-1,'&laquo;','Página anterior',actual == 1)}${items.join('')}${item(actual+1,'&raquo;','Página siguiente',actual == paginas)}</ul></nav>`;
}

const irPagina = function(pagina){
    bandeja.pagina = pagina;
    cargarBandeja();
    el('tituloClientes').focus();
}

const vacioBandeja = function(){
    let [icono, titulo, accion] = bandeja.q
        ? ['fa-magnifying-glass', `Sin resultados para "${escapeHtml(bandeja.q)}"`, botonSec('limpiarBusqueda()','fa-xmark','Limpiar búsqueda')]
        : (bandeja.estado ? ['fa-filter', `No hay clientes en estado «${ESTADOS_CLIENTE[bandeja.estado][0]}».`, botonSec("filtrarEstado('')",'','Ver todos')] : ['fa-users', 'No hay clientes para crear en SIESA', '']);
    return `<div class="ui-vacio"><i class="fas ${icono}" aria-hidden="true"></i><p class="ui-vacio-titulo">${titulo}</p>${accion ? `<div class="mt-3">${accion}</div>` : ''}</div>`;
}

const alternarLimpiar = () => el('limpiarBusqueda').classList.toggle('d-none', !el('busquedaCliente').value);

const buscar = function(){
    alternarLimpiar();
    clearTimeout(bandeja.temporizador);
    let q = el('busquedaCliente').value.trim();
    bandeja.temporizador = setTimeout(()=>{
        if(q !== bandeja.q){
            bandeja.q = q;
            bandeja.pagina = 1;
            cargarBandeja();
        }
    }, 300);
}

const limpiarBusqueda = function(){
    el('busquedaCliente').value = '';
    alternarLimpiar();
    bandeja.q = '';
    bandeja.pagina = 1;
    cargarBandeja();
    el('busquedaCliente').focus();
}

const planPasos = function(pasos){
    let estados = {};
    return pasos.map(p=>{
        let datos = p.motivos.filter(m=>!DEPENDENCIA.test(m));
        let plan = p.estado == 'bloqueado' && !datos.length && p.motivos.every(m=>estados[m.match(DEPENDENCIA)[1]] == 'listo') ? 'listo' : p.estado;
        estados[p.nombre] = plan;
        return Object.assign({}, p, {plan:plan, datos:datos});
    });
}

const abrirSiesa = function(boton){
    let r = bandeja.datos.registros.find(x=>String(x.idCliente) === boton.dataset.id);
    siesa = {id:r.idCliente, plan:null, vista:null, ejec:null, ejecutando:false, terminado:false, cambio:false, error:null, resumen:'', activa:'validacion', envio:bandeja.envio};
    el('tituloSiesa').textContent = `${!bandeja.envio ? 'Revisar' : (r.estado == 'completo' ? 'Detalle' : (r.estado == 'parcial' ? 'Completar' : 'Crear'))} en SIESA: ${r.nombre}`;
    el('subSiesa').textContent = [`NIT ${nitPlano(r)}`, fechaPlano(r.fecha) ? `Registrado ${fechaPlano(r.fecha)}` : ''].filter(Boolean).join(' · ');
    pintarCabecera(r.siesa);
    pintarPestanas();
    elegirPestana('validacion', false);
    bootstrap.Modal.getOrCreateInstance(el('modalSiesa')).show();
    validar();
}

const pintarCabecera = s => { el('estadoSiesa').innerHTML = componentes(s) + (siesa.envio ? '' : badgeDeshabilitado); };

const pintarPestanas = function(){
    let pestanas = [['validacion','Validación']].concat(esAdmin() ? [['vista','Vista previa']] : [], [['ejecucion','Ejecución']]);
    let bloqueos = siesa.plan && siesa.plan.some(p=>p.estado == 'bloqueado' && p.datos.length);
    el('pestanasSiesa').innerHTML = pestanas.map(([id,nombre])=>`<button type="button" role="tab" class="ui-pestana" id="tab-${id}" aria-controls="panel-${id}" aria-selected="${siesa.activa == id}" tabindex="${siesa.activa == id ? 0 : -1}" onclick="elegirPestana('${id}')"><span>${nombre}</span>${id == 'validacion' && bloqueos ? '<i class="fas fa-circle-exclamation" aria-hidden="true"></i><span class="visually-hidden"> (con datos por corregir)</span>' : ''}</button>`).join('');
}

const elegirPestana = function(id, foco=true){
    siesa.activa = id;
    document.querySelectorAll('#pestanasSiesa [role="tab"]').forEach(t=>{
        let activa = t.id == 'tab-' + id;
        t.setAttribute('aria-selected', activa);
        t.tabIndex = activa ? 0 : -1;
        if(activa && foco){
            t.focus();
        }
    });
    ['validacion','vista','ejecucion'].forEach(p=>{ el('panel-'+p).hidden = p != id; });
    if(id == 'vista' && siesa.vista == null){
        cargarVista();
    }
}

const teclaPestana = function(e){
    let tabs = [...e.currentTarget.querySelectorAll('[role="tab"]')], i = tabs.indexOf(document.activeElement);
    let destino = {ArrowRight:i + 1, ArrowLeft:i - 1, Home:0, End:tabs.length - 1}[e.key];
    if(i < 0 || destino == null){
        return;
    }
    e.preventDefault();
    elegirPestana(tabs[(destino + tabs.length) % tabs.length].id.replace('tab-',''));
}

const validar = async function(){
    let id = siesa.id;
    siesa.plan = null;
    el('panel-validacion').innerHTML = cargador('Validando datos…');
    if(!siesa.ejec){
        pintarEjecucion();
    }
    pintarPie();
    try{
        let res = await peticion('siesa-validar', {idCliente:id});
        if(siesa.id !== id){
            return;
        }
        siesa.plan = planPasos(res.pasos);
        siesa.envio = !!res.envioHabilitado;
        siesa.vista = null;
        pintarCabecera(Object.fromEntries(Object.keys(COMPONENTES).map(k=>[k, (siesa.plan.find(p=>p.clave == k) || {}).estado == 'hecho'])));
        pintarPestanas();
        pintarValidacion();
        if(!siesa.ejec){
            pintarEjecucion();
        }
        if(siesa.activa == 'vista'){
            cargarVista();
        }
    }catch(e){
        if(siesa.id !== id){
            return;
        }
        el('panel-validacion').innerHTML = alerta('error','fa-circle-exclamation',escapeHtml(mensajeErrorHttp(e.data)),botonSec('validar()','fa-rotate-right','Reintentar'),'alert');
        if(!siesa.ejec){
            el('panel-ejecucion').innerHTML = '';
        }
    }
    pintarPie();
}

const itemPaso = function(p, i, estado, meta, badge='', detalle=''){
    let [icono, color, texto] = MARCAS[estado];
    texto = badge || texto;
    return `<li class="ui-pasos-item is-${estado}"><span class="ui-pasos-marca" aria-hidden="true">${icono ? `<i class="fas ${icono}"></i>` : i + 1}</span><span><span class="ui-pasos-nombre">${escapeHtml(p.nombre)}</span><span class="ui-pasos-meta">${escapeHtml(meta)}</span></span><span class="ui-pasos-estado"><span class="ui-badge ui-badge-${color}" aria-hidden="true">${texto}</span><span class="visually-hidden">Estado: ${texto}</span></span>${detalle}</li>`;
}

const itemPlan = function(p, i){
    if(p.plan == 'hecho'){
        return itemPaso(p, i, 'ok', 'Ya existe en SIESA, no se reenvía', 'Ya existía');
    }
    return p.plan == 'listo' ? itemPaso(p, i, 'pendiente', 'Se enviará') : itemPaso(p, i, p.plan, p.motivos.join(' '));
}

const datosPorCorregir = function(){
    let items = new Map();
    siesa.plan.forEach(p=>{
        let tipo = p.plan == 'omitido' ? 'aviso' : (p.plan == 'bloqueado' ? 'bloqueante' : '');
        (tipo == 'aviso' ? p.motivos : (tipo ? p.datos : [])).forEach(m=>{
            if(!items.has(tipo + m)){
                items.set(tipo + m, {tipo:tipo, texto:m, pasos:[]});
            }
            items.get(tipo + m).pasos.push(p.nombre);
        });
    });
    return [...items.values()].sort((a,b)=>(a.tipo == 'aviso') - (b.tipo == 'aviso'));
}

const pintarValidacion = function(){
    let plan = siesa.plan, total = plan.length, items = datosPorCorregir();
    let bloqueantes = items.filter(x=>x.tipo == 'bloqueante').length, listos = plan.filter(p=>p.plan == 'listo').length, omitidos = plan.filter(p=>p.plan == 'omitido').length;
    let resumen = bloqueantes
        ? alerta('error','fa-circle-exclamation', bloqueantes == 1 ? 'Hay 1 dato que impide enviar a SIESA.' : `Hay ${bloqueantes} datos que impiden enviar a SIESA.`)
        : (omitidos ? alerta('adv','fa-triangle-exclamation',`Se pueden enviar ${listos} de ${total} pasos. ${omitidos} se ${omitidos == 1 ? 'omitirá' : 'omitirán'}.`)
        : alerta('exito','fa-circle-check', listos == total ? `Datos completos. Los ${total} pasos se pueden enviar.` : (listos ? `Datos completos. Se pueden enviar ${listos} de ${total} pasos; los demás ya existen en SIESA.` : 'El cliente ya está completo en SIESA.')));
    let lista = items.map(x=>`<li class="ui-validacion-item is-${x.tipo}"><i class="fas ${x.tipo == 'bloqueante' ? 'fa-circle-xmark' : 'fa-triangle-exclamation'}" aria-hidden="true"></i><span><span class="visually-hidden">${x.tipo == 'bloqueante' ? 'Impide enviar: ' : 'Aviso: '}</span>${escapeHtml(x.texto)}<span class="ui-validacion-afecta">Afecta: ${x.pasos.length == total ? 'todos los pasos' : escapeHtml(x.pasos.join(', '))}</span></span></li>`).join('');
    el('panel-validacion').innerHTML = resumen + (lista ? `<h3 class="ui-seccion-titulo">Datos por corregir</h3><ul class="ui-validacion">${lista}</ul>` : '') + `<h3 class="ui-seccion-titulo">Pasos que se ejecutarán</h3><ol class="ui-pasos">${plan.map(itemPlan).join('')}</ol>`;
}

const cargarVista = async function(){
    let id = siesa.id, destino = el('panel-vista');
    siesa.vista = false;
    destino.innerHTML = cargador('Cargando vista previa…');
    try{
        let res = await peticion('siesa-vista-previa', {idCliente:id});
        if(siesa.id !== id){
            return;
        }
        siesa.vista = planPasos(res.pasos);
        destino.innerHTML = '<p class="ui-campo-ayuda mt-0 mb-3">Registros planos que se enviarían a SIESA. Solo lectura.</p>' + siesa.vista.map(bloqueCodigo).join('');
    }catch(e){
        if(siesa.id !== id){
            return;
        }
        siesa.vista = null;
        destino.innerHTML = alerta('error','fa-circle-exclamation',escapeHtml(mensajeErrorHttp(e.data)),botonSec('cargarVista()','fa-rotate-right','Reintentar'),'alert');
    }
}

const botonCopiar = etiqueta => `<button type="button" class="btn btn-sm ui-btn ui-btn-sec" aria-label="${escapeHtml(etiqueta)}" onclick="copiar(this)"><i class="fas fa-copy" aria-hidden="true"></i><span>Copiar</span></button>`;

const bloqueCodigo = function(p, i){
    let titulo = `${i + 1} · ${escapeHtml(p.nombre)}`;
    if(p.plan != 'listo'){
        return `<div class="ui-codigo is-omitido"><div class="ui-codigo-cab"><span>${titulo}</span></div><p class="ui-campo-ayuda">${p.plan == 'hecho' ? 'Ya existe en SIESA; no se reenvía.' : `No se enviará: ${escapeHtml(p.motivos.join(' '))}`}</p></div>`;
    }
    return `<div class="ui-codigo"><div class="ui-codigo-cab"><span>${titulo}</span>${botonCopiar('Copiar registro de '+p.nombre)}</div><pre class="ui-codigo-pre" tabindex="0" aria-label="Registro ${escapeHtml(p.nombre)}">${escapeHtml((p.registros || []).join('\n'))}</pre></div>`;
}

const copiarTexto = function(texto){
    if(navigator.clipboard && window.isSecureContext){
        return navigator.clipboard.writeText(texto);
    }
    let area = document.createElement('textarea');
    area.value = texto;
    el('modalSiesa').appendChild(area);
    area.select();
    let ok = document.execCommand('copy');
    area.remove();
    return ok ? Promise.resolve() : Promise.reject();
}

const copiar = async function(boton){
    try{
        await copiarTexto(boton.closest('.ui-codigo').querySelector('pre').textContent);
    }catch(e){
        return notificar('No fue posible copiar el registro');
    }
    if(!boton.dataset.original){
        boton.dataset.original = boton.innerHTML;
    }
    boton.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i><span>Copiado</span>';
    notificar('Registro copiado');
    clearTimeout(boton.temporizador);
    boton.temporizador = setTimeout(()=>{ boton.innerHTML = boton.dataset.original; }, 2000);
}

const pintarEjecucion = function(){
    let destino = el('panel-ejecucion'), plan = siesa.plan;
    if(!siesa.ejec){
        if(!plan){
            destino.innerHTML = cargador('Validando datos…');
            return;
        }
        let ayuda = siesa.envio ? '<p class="ui-resumen mb-2">Pulse «Ejecutar en SIESA» para crear los pasos pendientes en orden.</p>' : alerta('adv','fa-triangle-exclamation','El envío a SIESA está deshabilitado. Aquí se mostrará el avance cuando se habilite.');
        destino.innerHTML = ayuda + `<ol class="ui-pasos" id="pasosSiesa" aria-label="Pasos de creación en SIESA">${plan.map(itemPlan).join('')}</ol>`;
        return;
    }
    if(!el('resumenEjecucion')){
        destino.innerHTML = '<div id="alertaEjecucion"></div><div class="ui-progreso mb-2" id="progresoSiesa" role="progressbar" aria-label="Progreso de creación en SIESA" aria-valuemin="0"><span class="pista"><span class="relleno" id="rellenoSiesa"></span></span><span class="cifra" id="cifraSiesa"></span></div><p class="ui-resumen mb-2" id="resumenEjecucion" aria-live="polite" aria-atomic="true" tabindex="-1"></p><ol class="ui-pasos" id="pasosSiesa" aria-label="Pasos de creación en SIESA"></ol>';
    }
    let filas = siesa.ejec, total = filas.length, hechos = filas.filter(f=>['ok','omitido','bloqueado'].includes(f.estado)).length;
    let progreso = el('progresoSiesa');
    progreso.setAttribute('aria-valuemax', total);
    progreso.setAttribute('aria-valuenow', hechos);
    progreso.setAttribute('aria-valuetext', `${hechos} de ${total} pasos procesados`);
    el('rellenoSiesa').style.width = (hechos / total * 100) + '%';
    el('cifraSiesa').textContent = `${hechos} / ${total}`;
    el('resumenEjecucion').textContent = siesa.resumen;
    el('pasosSiesa').setAttribute('aria-busy', siesa.ejecutando);
    el('pasosSiesa').innerHTML = filas.map((f,i)=>itemPaso(siesa.plan[i], i, f.estado, f.meta, f.badge, f.detalle)).join('');
    let previos = estado => filas.slice(0, siesa.error).filter(f=>f.estado == estado).length;
    el('alertaEjecucion').innerHTML = siesa.error != null ? alerta('error','fa-circle-exclamation',`Se detuvo en el paso ${siesa.error + 1}.${previos('ok') ? ` Antes del error: ${previos('ok')} completados, ${previos('omitido')} omitidos.` : ''}`) : (siesa.terminado ? resultadoFinal(filas) : '');
}

const resultadoFinal = function(filas){
    let cuenta = estado => filas.filter(f=>f.estado == estado).length, ok = cuenta('ok'), omitidos = cuenta('omitido'), bloqueados = cuenta('bloqueado');
    let texto = `${bloqueados ? 'Proceso terminado' : 'Cliente creado en SIESA'}. ${ok} ${ok == 1 ? 'paso completado' : 'pasos completados'}, ${omitidos} ${omitidos == 1 ? 'omitido' : 'omitidos'}${bloqueados ? `, ${bloqueados} ${bloqueados == 1 ? 'bloqueado' : 'bloqueados'}` : ''}.`;
    let extra = [bloqueados ? 'Corrija los datos marcados en Validación y vuelva a ejecutar.' : '', omitidos ? 'Registre la cuenta bancaria y vuelva a ejecutar para completar el pago electrónico.' : ''].filter(Boolean).map(t=>`<p class="m-0 mt-1">${t}</p>`).join('');
    return `<div class="ui-alerta ui-alerta-${bloqueados ? 'adv' : 'exito'}"><i class="fas ${bloqueados ? 'fa-triangle-exclamation' : 'fa-circle-check'}" aria-hidden="true"></i><div class="ui-alerta-texto"><h3 class="h6 m-0 fw-semibold" id="finalSiesa" tabindex="-1">${texto}</h3>${extra}</div></div>`;
}

const detalleError = function(e, res){
    let titulo, cabecera = '', texto = '';
    if(!e){
        titulo = 'SIESA rechazó el registro.';
        cabecera = 'Respuesta de SIESA';
        texto = [res.detalle].concat((res.erroresSiesa || []).map(x=>`Línea ${x.nroLinea} · ${x.valor}: ${x.detalle}`)).filter(Boolean).join('\n');
    }else if(e.status == 422){
        titulo = 'El servidor no permitió enviar el paso.';
        cabecera = 'Respuesta del servidor';
        texto = [...new Set([(e.data || {}).message].concat(...Object.values((e.data || {}).errors || {})).filter(Boolean))].join('\n');
    }else{
        titulo = `No se pudo conectar con el servidor${e.status ? ` (código ${e.status})` : ''}. Intente de nuevo.`;
    }
    let codigo = cabecera ? `<div class="ui-codigo is-error"><div class="ui-codigo-cab"><span>${cabecera}</span>${botonCopiar(`Copiar r${cabecera.slice(1)}`)}</div><pre class="ui-codigo-pre" tabindex="0" aria-label="${cabecera}">${escapeHtml(texto || 'Sin detalle')}</pre></div>` : '';
    return `<div class="ui-pasos-detalle" role="alert"><p class="ui-pasos-detalle-titulo">${titulo}</p>${codigo}<button type="button" class="btn btn-primary btn-sm ui-btn" id="reintentarPaso" onclick="ejecutar()"><i class="fas fa-rotate-right" aria-hidden="true"></i><span>Reintentar desde este paso</span></button></div>`;
}

const marcarPaso = function(i, fila, resumen){
    siesa.ejec[i] = fila;
    siesa.resumen = resumen;
    pintarEjecucion();
}

const ejecutar = async function(){
    if(siesa.ejecutando || !siesa.plan){
        return;
    }
    let id = siesa.id, total = siesa.plan.length, desde = siesa.error != null ? siesa.error : 0;
    siesa.ejec = siesa.ejec || [];
    for(let i = desde; i < total; i++){
        siesa.ejec[i] = {estado:'pendiente', meta:'En espera'};
    }
    Object.assign(siesa, {error:null, terminado:false, ejecutando:true, cambio:true, resumen:''});
    elegirPestana('ejecucion', false);
    pintarEjecucion();
    pintarPie();
    el('resumenEjecucion').focus();
    for(let i = desde; i < total; i++){
        let nombre = siesa.plan[i].nombre, fallo = null, res = null;
        marcarPaso(i, {estado:'curso', meta:'Enviando a SIESA…'}, `Paso ${i + 1} de ${total} en curso: ${nombre}.`);
        try{
            let paso = (await peticion('siesa-validar', {idCliente:id})).pasos[i];
            if(paso.estado == 'hecho'){
                marcarPaso(i, {estado:'ok', meta:'Ya existe en SIESA, no se reenvía', badge:'Ya existía'}, `${nombre} ya existía.`);
                continue;
            }
            if(paso.estado != 'listo'){
                marcarPaso(i, {estado:paso.estado, meta:paso.motivos.join(' ')}, `${nombre} ${paso.estado == 'omitido' ? 'omitido' : 'bloqueado'}.`);
                continue;
            }
            res = await peticion('siesa-ejecutar-paso', {idCliente:id, paso:paso.clave});
        }catch(e){
            fallo = e;
        }
        if(!fallo && res.ok){
            marcarPaso(i, {estado:'ok', meta:'Creado en SIESA'}, `${nombre} completado.`);
            continue;
        }
        for(let j = i + 1; j < total; j++){
            siesa.ejec[j] = {estado:'pendiente', meta:'No ejecutado'};
        }
        Object.assign(siesa, {error:i, ejecutando:false});
        marcarPaso(i, {estado:'error', meta:'No se pudo crear', detalle:detalleError(fallo, res)}, `Error en ${nombre}. Se detuvo la ejecución.`);
        pintarPie();
        el('reintentarPaso').focus();
        return;
    }
    let cuenta = estado => siesa.ejec.filter(f=>f.estado == estado).length;
    Object.assign(siesa, {ejecutando:false, terminado:true});
    siesa.resumen = `Proceso terminado: ${cuenta('ok')} completados, ${cuenta('omitido')} omitidos${cuenta('bloqueado') ? `, ${cuenta('bloqueado')} bloqueados` : ''}.`;
    pintarEjecucion();
    pintarPie();
    el('finalSiesa').focus();
    notificar('Proceso en SIESA terminado');
    validar();
}

const pintarPie = function(){
    let boton = el('btnEjecutar'), ayuda = el('ayudaEjecutar'), plan = siesa.plan, reintento = siesa.error != null;
    let listos = plan ? plan.filter(p=>p.plan == 'listo').length : 0;
    let texto = siesa.ejecutando ? 'No cierre esta ventana mientras se ejecutan los pasos.'
        : (!plan || reintento || siesa.terminado ? ''
        : (!siesa.envio ? 'El envío a SIESA está deshabilitado. Solo puede validar y previsualizar.'
        : (listos ? '' : (plan.some(p=>p.plan == 'bloqueado') ? 'Corrija los datos marcados en Validación para poder enviar.' : 'No hay pasos pendientes por enviar.'))));
    ayuda.textContent = texto;
    ayuda.hidden = !texto;
    boton.innerHTML = reintento ? `<i class="fas fa-rotate-right" aria-hidden="true"></i><span>Reintentar desde el paso ${siesa.error + 1}</span>` : '<i class="fas fa-play" aria-hidden="true"></i><span>Ejecutar en SIESA</span>';
    boton.disabled = siesa.ejecutando || !plan || (!reintento && (!siesa.envio || !listos));
    boton.disabled && texto ? boton.setAttribute('aria-describedby','ayudaEjecutar') : boton.removeAttribute('aria-describedby');
    boton.hidden = siesa.terminado;
    el('btnCerrarSiesa').className = `btn ui-btn me-sm-auto ${siesa.terminado ? 'btn-primary' : 'ui-btn-sec'}`;
    el('btnCerrarSiesa').disabled = el('btnValidar').disabled = siesa.ejecutando;
    el('cerrarSiesa').classList.toggle('d-none', siesa.ejecutando);
}

document.addEventListener('DOMContentLoaded', ()=>{
    let modal = el('modalSiesa');
    el('busquedaCliente').addEventListener('input', buscar);
    modal.addEventListener('shown.bs.modal', ()=>el('tituloSiesa').focus());
    modal.addEventListener('hide.bs.modal', e=>{ if(siesa.ejecutando) e.preventDefault(); });
    modal.addEventListener('hidden.bs.modal', async ()=>{
        let id = String(siesa.id);
        if(siesa.cambio){
            await cargarBandeja();
        }
        siesa.id = null;
        (document.querySelector(`#bandejaClientes [data-id="${CSS.escape(id)}"]`) || el('tituloClientes')).focus();
    });
    cargarBandeja();
});
