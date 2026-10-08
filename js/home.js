const INICIO_CONTADORES = ['Rechazados','Registro','Centrales','Documentos','Aprobación','Aprobados'];
const INICIO_ESTADOS = ['Rechazados','Registro','Centrales de riesgo','Documentos de soporte','Aprobación de crédito','Aprobado'];
const INICIO_ACCIONES = {registro:'Continuar registro', centrales:'Revisar centrales', cargarDocumentos:'Cargar documentos', aprobarDocumentos:'Revisar documentos', aprobarCredito:'Aprobar crédito'};

const inicioNumero = n => (Number(n) || 0).toLocaleString('es-CO');

const inicioAntiguedad = function(fecha){
    let dias = Math.floor((Date.now() - new Date(String(fecha).replace(' ','T')).getTime()) / 86400000);
    return isNaN(dias) ? '' : (dias < 1 ? 'hoy' : `hace ${dias} ${dias == 1 ? 'día' : 'días'}`);
}

const inicioKpi = (url, etiqueta, largo, valor, clase, unidad=valor == 1 ? 'proceso' : 'procesos') => `<a class="ui-contador${clase}" href="${escapeHtml(url)}" aria-label="${escapeHtml(largo)}, ${inicioNumero(valor)} ${unidad}"><span class="ui-contador-num">${inicioNumero(valor)}</span><span class="ui-contador-et">${escapeHtml(etiqueta)}</span></a>`;

const inicioCard = (id, titulo, cuerpo, pie='') => `<section class="ui-card" id="${id}"${id == 'inicioPendientes' ? ' tabindex="-1"' : ''} aria-labelledby="${id}Titulo"><div class="ui-card-cab"><h2 id="${id}Titulo">${titulo}</h2></div>${cuerpo}${pie}</section>`;

const inicioCargando = function(){
    let contador = '<div class="ui-contador"><span class="ui-esqueleto" style="height:1.25rem;width:60%"></span><span class="ui-esqueleto mt-2" style="height:.74rem;width:80%"></span></div>';
    let fila = '<li class="ui-doc"><div><span class="ui-esqueleto" style="width:45%"></span><span class="ui-esqueleto mt-2" style="height:.6rem;width:65%"></span></div></li>';
    let destino = document.getElementById('inicioResumen');
    destino.setAttribute('aria-busy','true');
    destino.innerHTML = `<p class="visually-hidden" role="status">Cargando tu resumen…</p><div class="ui-contadores" aria-hidden="true">${contador.repeat(4)}</div><div class="inicio-cols" aria-hidden="true">${inicioCard('inicioPendientesCarga','Pendientes de mi acción',`<ul class="ui-docs">${fila.repeat(3)}</ul>`)}</div>`;
}

const inicioPendientes = function(res){
    if(!res.pendientes.length){
        return inicioCard('inicioPendientes','Pendientes de mi acción','<div class="ui-vacio"><i class="fas fa-circle-check" aria-hidden="true"></i><p class="ui-vacio-titulo">No tienes pendientes</p><p class="ui-vacio-texto">Cuando un proceso requiera tu acción aparecerá aquí.</p></div>');
    }
    let filas = res.pendientes.map(p=>{
        let meta = [`Proceso #${Number(p.idProceso)}`, INICIO_ACCIONES[p.accion], inicioAntiguedad(p.fecha)].filter(Boolean).map(escapeHtml).join(' · ');
        return `<li class="ui-doc"><div><span class="ui-doc-nombre">${escapeHtml(p.cliente)}</span><span class="ui-doc-meta">${meta}</span></div><span class="ui-badge ui-badge-estado ui-estado-${Number(p.estado)}">${escapeHtml(INICIO_ESTADOS[p.estado] || p.estadoNombre)}</span><div class="ui-doc-acciones"><a class="btn btn-sm ui-btn ui-btn-sec" href="${escapeHtml(p.url)}">Revisar<span class="visually-hidden"> ${escapeHtml(p.cliente)}</span></a></div></li>`;
    }).join('');
    let pie = `<div class="ui-card-pie justify-content-end"><a href="${globalUrl}/lista-procesos">Ver todos en la bandeja (${inicioNumero(res.pendientesTotal)}) <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>`;
    return inicioCard('inicioPendientes','Pendientes de mi acción',`<ul class="ui-docs">${filas}</ul>`,pie);
}

const inicioPintar = function(res){
    let procesos = res.kpis.filter(k=>k.estado != null);
    let otros = res.kpis.filter(k=>k.estado == null);
    let orden = procesos.filter(k=>k.estado != 0).concat(procesos.filter(k=>k.estado == 0));
    let tieneProcesos = procesos.length > 0 || res.pendientes.length > 0;
    let kpis = (tieneProcesos ? inicioKpi('#inicioPendientes','Pendientes de mi acción','Pendientes de mi acción',res.pendientesTotal,' is-accion') : '')
        + orden.map(k=>inicioKpi(k.url, INICIO_CONTADORES[k.estado] || k.titulo, INICIO_ESTADOS[k.estado] || k.titulo, k.valor, ` ui-estado-${Number(k.estado)}`)).join('')
        + otros.map(k=>inicioKpi(k.url, k.titulo, k.titulo, k.valor, '', k.valor == 1 ? 'registro' : 'registros')).join('');
    let accesos = res.accesos.slice(0,6).map(a=>`<a class="ui-acceso" href="${escapeHtml(a.url)}"><i class="${escapeHtml(a.icono || 'fas fa-arrow-right')} ui-acceso-icono" aria-hidden="true"></i><span>${escapeHtml(a.titulo)}</span></a>`).join('');
    let destino = document.getElementById('inicioResumen');
    destino.innerHTML = (kpis ? `<section aria-labelledby="inicioKpisTitulo"><h2 class="ui-seccion-titulo" id="inicioKpisTitulo">Resumen de procesos</h2><div class="ui-contadores">${kpis}</div></section>` : '')
        + `<div class="inicio-cols">${tieneProcesos ? inicioPendientes(res) : ''}${accesos ? inicioCard('inicioAccesos','Accesos rápidos',`<div class="ui-accesos">${accesos}</div>`) : ''}</div>`;
    if(!kpis && !accesos){
        destino.innerHTML = '<div class="ui-card"><div class="ui-vacio"><i class="fas fa-folder-open" aria-hidden="true"></i><p class="ui-vacio-titulo">Aún no tienes módulos asignados.</p><p class="ui-vacio-texto">Si necesitas acceso, comunícate con el administrador.</p></div></div>';
    }
    destino.removeAttribute('aria-busy');
}

const cargarInicio = async function(){
    inicioCargando();
    try{
        let res = await makeOptionsFetch(`${globalUrl}/inicio-resumen`,new FormData(),'post',$('meta[name="csrf-token"]').attr('content'),true);
        inicioPintar(res);
    }catch(e){
        let destino = document.getElementById('inicioResumen');
        destino.innerHTML = e.status == 403
            ? '<div class="ui-alerta ui-alerta-adv" role="alert"><i class="fas fa-lock" aria-hidden="true"></i><span class="ui-alerta-texto">No tienes permiso para ver esta información.</span></div>'
            : '<div class="ui-alerta ui-alerta-error" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i><span class="ui-alerta-texto">No fue posible cargar tu resumen. Intenta de nuevo.</span><span class="ui-alerta-accion"><button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="cargarInicio()"><i class="fas fa-rotate-right" aria-hidden="true"></i> Reintentar</button></span></div>';
        destino.removeAttribute('aria-busy');
    }
}

document.addEventListener('DOMContentLoaded',function(){
    document.getElementById('inicioFecha').textContent = new Date().toLocaleDateString('es-CO',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
    document.getElementById('inicioResumen').addEventListener('click',function(e){
        if(e.target.closest('a[href="#inicioPendientes"]')){
            e.preventDefault();
            document.getElementById('inicioPendientes').focus();
        }
    });
    cargarInicio();
});
