const ESTADOS = ['Rechazado','Registro','Centrales de riesgo','Documentos de soporte','Aprobación de crédito','Aprobado'];
const CONTADORES = ['Rechazados','Registro','Centrales','Documentos','Aprobación','Aprobados'];
const PASOS = ['Registro','Centrales','Documentos','Aprobación','Aprobado'];
const ACCIONES = {registro:'Continuar registro', centrales:'Revisar centrales', cargarDocumentos:'Cargar documentos', aprobarDocumentos:'Revisar documentos', aprobarCredito:'Aprobar crédito'};
const GESTORES = {1:'el asesor comercial', 2:'el área de consulta de centrales o el analista de crédito', 3:'el asesor comercial (carga) y el analista de crédito (revisión)', 4:'el comité de crédito'};
const ESTADOS_DOC = {cargado:['Cargado','info'], aprobado:['Aprobado','activo'], rechazado:['Rechazado','error']};
const AMBIENTE_CENTRAL = {pruebas:['adv','Pruebas (UAT)'], produccion:['info','Producción']};
const NIVEL_RIESGO = {alto:['activo','fa-shield-halved','Riesgo bajo'], medio:['adv','fa-circle-half-stroke','Riesgo medio'], bajo:['error','fa-triangle-exclamation','Riesgo alto']};
const ESTADOS_OBLIGACION = {al_dia:['activo','Al día'], mora:['error','En mora'], cerrada:['inactivo','Cerrada']};
const FALLOS_CONSULTA = {conexion:'error de conexión', sin_credenciales:'sin credenciales configuradas', deshabilitado:'proveedor deshabilitado', respuesta_invalida:'sin información', datos_invalidos:'tipo de documento no compatible'};
const POR_PAGINA = 25;
const MAX_PDF_MB = 10;
let bandeja = {estado:'', q:'', pagina:1, datos:null, orden:{campo:'fecha', dir:'desc'}, temporizador:null, consulta:'', peticion:0};
let detalle = {id:null, datos:null, motivos:null, centrales:null, editando:false, calculo:null, aviso:false, mensaje:''};
let rechazo = {doc:null};

const el = id => document.getElementById(id);
const contenedor = () => el('procesos');
const puede = accion => detalle.datos.acciones.includes(accion);
const fallo = e => { if(e.status != 403) mostrarErrorHttp(e.data, e.status); };

const peticion = function(ruta, datos={}){
    let fd = datos instanceof FormData ? datos : new FormData();
    if(!(datos instanceof FormData)){
        Object.entries(datos).forEach(([clave,valor])=>fd.append(clave, valor == null ? '' : valor));
    }
    return makeOptionsFetch(`${globalUrl}/${ruta}`, fd, 'post', document.querySelector('meta[name="csrf-token"]').content, true);
}

const cargando = async function(boton, texto, accion){
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

const marcarError = function(campo, mensaje){
    let input = el(campo), error = el('error-'+campo);
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

const cargador = texto => `<div class="ui-vacio" aria-busy="true"><span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span><p class="ui-vacio-titulo mt-2">${texto}</p></div>`;
const badgeEstado = estado => `<span class="ui-badge ui-badge-estado ui-estado-${estado}">${ESTADOS[estado] || ''}</span>`;
const contar = id => { el('contador-'+id).textContent = `${el(id).value.length} / ${el(id).maxLength}`; };

const partesFecha = function(texto){
    let m = String(texto || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    return m ? {fecha:`${m[3]}/${m[2]}/${m[1]}`, hora: m[4] ? `${m[4]}:${m[5]}` : ''} : null;
}
const fechaTexto = texto => (partesFecha(texto) || {}).fecha || '';
const fechaHtml = function(texto, clase='', conHora=false){
    let p = partesFecha(texto);
    if(!p){
        return '—';
    }
    let completo = p.hora ? `${p.fecha} ${p.hora}` : p.fecha;
    return `<time${clase ? ` class="${clase}"` : ''} datetime="${escapeHtml(String(texto).replace(' ','T'))}"${p.hora ? ` title="${completo}"` : ''}>${conHora ? completo : p.fecha}</time>`;
}

const abrirModal = function(id, origen, foco){
    let modal = el(id);
    modal.origen = origen;
    modal.foco = foco;
    bootstrap.Modal.getOrCreateInstance(modal).show();
}
const cerrarModal = id => bootstrap.Modal.getOrCreateInstance(el(id)).hide();

const urlBandeja = () => contenedor().dataset.urlProcesos + bandeja.consulta;

const mostrarVista = function(vista){
    el('vistaBandeja').classList.toggle('d-none', vista != 'bandeja');
    el('vistaDetalle').classList.toggle('d-none', vista != 'detalle');
    window.scrollTo(0,0);
}

const enrutar = function(){
    let params = new URLSearchParams(location.search);
    if(params.get('proceso')){
        return abrirDetalle(params.get('proceso'));
    }
    bandeja.estado = /^[0-5]$/.test(params.get('estado') || '') ? params.get('estado') : '';
    bandeja.q = (params.get('q') || '').slice(0,100);
    bandeja.pagina = Math.max(1, parseInt(params.get('pagina')) || 1);
    bandeja.orden = {campo:['cliente','monto','estado','fecha'].includes(params.get('orden')) ? params.get('orden') : 'fecha', dir:params.get('direccion') == 'asc' ? 'asc' : 'desc'};
    el('busquedaProceso').value = bandeja.q;
    alternarLimpiar();
    let volviendo = !el('vistaDetalle').classList.contains('d-none');
    mostrarVista('bandeja');
    cargarBandeja();
    if(volviendo){
        el('tituloBandeja').focus();
    }
}

const irDetalle = function(e, id){
    if(e.ctrlKey || e.metaKey || e.shiftKey || e.button){
        return true;
    }
    e.preventDefault();
    history.pushState(null, '', `${location.pathname}?proceso=${id}`);
    abrirDetalle(id);
    return false;
}

const volverBandeja = function(e){
    if(e.ctrlKey || e.metaKey || e.shiftKey || e.button){
        return true;
    }
    e.preventDefault();
    history.pushState(null, '', urlBandeja());
    enrutar();
    return false;
}

const cargarBandeja = async function(){
    let consulta = new URLSearchParams();
    if(bandeja.estado !== ''){
        consulta.set('estado', bandeja.estado);
    }
    if(bandeja.q){
        consulta.set('q', bandeja.q);
    }
    if(bandeja.pagina > 1){
        consulta.set('pagina', bandeja.pagina);
    }
    if(bandeja.orden.campo != 'fecha' || bandeja.orden.dir != 'desc'){
        consulta.set('orden', bandeja.orden.campo);
        consulta.set('direccion', bandeja.orden.dir);
    }
    bandeja.consulta = consulta.toString() ? '?'+consulta : '';
    history.replaceState(null, '', location.pathname + bandeja.consulta);
    let destino = el('bandejaContenido'), n = ++bandeja.peticion;
    destino.innerHTML = cargador('Cargando procesos…');
    let datos = {page:bandeja.pagina, perPage:POR_PAGINA, busqueda:bandeja.q, orden:bandeja.orden.campo, direccion:bandeja.orden.dir};
    if(bandeja.estado !== ''){
        datos.estado = bandeja.estado;
    }
    try{
        let res = await peticion('lista-procesos-filtro', datos);
        if(n != bandeja.peticion){
            return;
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
        el('resumenBandeja').textContent = '';
        destino.innerHTML = alerta('error','fa-circle-exclamation','No fue posible cargar los procesos.',`<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="cargarBandeja()"><i class="fas fa-rotate-right" aria-hidden="true"></i><span>Reintentar</span></button>`,'alert');
    }
}

const pintarBandeja = function(){
    let res = bandeja.datos;
    pintarContadores(res.contadores);
    el('resumenBandeja').textContent = `Mostrando ${res.total} ${res.total == 1 ? 'proceso' : 'procesos'}${bandeja.estado !== '' ? ' en '+ESTADOS[bandeja.estado] : ''}`;
    el('bandejaContenido').innerHTML = res.registros.length ? tablaBandeja(res.registros) + paginador(res) : vacioBandeja();
}

const pintarContadores = function(lista){
    let orden = lista.filter(c=>c.estado != 0).concat(lista.filter(c=>c.estado == 0));
    let boton = (valor, corto, largo, total) => `<button type="button" class="ui-contador${valor !== '' ? ' ui-estado-'+valor : ''}" aria-pressed="${String(bandeja.estado) === String(valor)}" aria-label="${largo}, ${total} ${total == 1 ? 'proceso' : 'procesos'}" onclick="filtrarEstado('${valor}')"><span class="ui-contador-num">${total}</span><span class="ui-contador-et">${corto}</span></button>`;
    let todos = lista.filter(c=>c.estado != 0).reduce((suma,c)=>suma + c.total, 0);
    el('contadores').innerHTML = boton('','Todos','Todos',todos) + orden.map(c=>boton(c.estado, CONTADORES[c.estado], c.estado == 0 ? 'Rechazados' : ESTADOS[c.estado], c.total)).join('');
}

const filtrarEstado = function(valor){
    bandeja.estado = String(valor);
    bandeja.pagina = 1;
    cargarBandeja();
}

const thOrden = function(campo, texto){
    let activo = bandeja.orden.campo == campo, asc = bandeja.orden.dir == 'asc';
    return `<th scope="col" aria-sort="${activo ? (asc ? 'ascending' : 'descending') : 'none'}"><button type="button" class="proc-orden" data-orden="${campo}" onclick="ordenar('${campo}')">${texto}<i class="fas ${activo ? (asc ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-sort'}" aria-hidden="true"></i></button></th>`;
}

const tablaBandeja = function(registros){
    let asesor = contenedor().dataset.verAsesor == '1';
    return `<div class="ui-scroll"><table class="ui-tabla ui-tabla-tarjetas">
        <caption class="visually-hidden">Procesos de crédito${bandeja.estado !== '' ? ' en '+ESTADOS[bandeja.estado] : ''}</caption>
        <thead><tr>${thOrden('cliente','Cliente')}<th scope="col">Pagaduría</th>${thOrden('monto','Monto')}${thOrden('estado','Estado')}${asesor ? '<th scope="col">Asesor</th>' : ''}${thOrden('fecha','Último cambio')}<th scope="col"><span class="visually-hidden">Acción</span></th></tr></thead>
        <tbody>${registros.map(r=>filaBandeja(r, asesor)).join('')}</tbody>
    </table></div>`;
}

const documentoTexto = (tipo, numero) => escapeHtml([tipo, String(numero ?? '').replace(/^\d+$/, d=>d.replace(/\B(?=(\d{3})+$)/g,'.'))].filter(Boolean).join(' '));

const filaBandeja = function(r, asesor){
    let texto = ACCIONES[r.accionPrincipal] || 'Ver detalle';
    let registro = r.accionPrincipal == 'registro';
    let href = registro ? `${contenedor().dataset.urlRegistro}?proceso=${r.IdProceso}` : `?proceso=${r.IdProceso}`;
    return `<tr>
        <td class="ui-celda-principal">${escapeHtml(r.nombre)}<span class="proc-sub">${documentoTexto(r.tipoDocumento, r.documento)}</span></td>
        <td data-label="Pagaduría">${escapeHtml(r.pagaduria || '—')}</td>
        <td data-label="Monto" class="num">${moneda(r.monto)}</td>
        <td data-label="Estado">${badgeEstado(r.estado)}</td>
        ${asesor ? `<td data-label="Asesor">${escapeHtml(r.asesor || '—')}</td>` : ''}
        <td data-label="Último cambio">${fechaHtml(r.fechaActualizacion)}</td>
        <td class="ui-celda-accion"><a href="${href}" class="btn btn-sm ui-btn ${r.accionPrincipal ? 'btn-primary' : 'ui-btn-sec'}" aria-label="${escapeHtml(texto+' de '+r.nombre)}"${registro ? '' : ` onclick="return irDetalle(event, ${Number(r.IdProceso)})"`}>${texto}</a></td>
    </tr>`;
}

const ordenar = async function(campo){
    let o = bandeja.orden;
    o.dir = o.campo == campo ? (o.dir == 'asc' ? 'desc' : 'asc') : (['fecha','monto'].includes(campo) ? 'desc' : 'asc');
    o.campo = campo;
    bandeja.pagina = 1;
    await cargarBandeja();
    let encabezado = document.querySelector(`[data-orden="${campo}"]`);
    if(encabezado){
        encabezado.focus();
    }
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
    return `<nav class="proc-paginador" aria-label="Paginación de procesos"><ul class="pagination pagination-sm mb-0">${item(actual-1,'&laquo;','Página anterior',actual == 1)}${items.join('')}${item(actual+1,'&raquo;','Página siguiente',actual == paginas)}</ul></nav>`;
}

const irPagina = function(pagina){
    bandeja.pagina = pagina;
    cargarBandeja();
    el('tituloBandeja').focus();
}

const vacioBandeja = function(){
    let [icono, titulo, accion] = bandeja.q
        ? ['fa-magnifying-glass', `No encontramos procesos para «${escapeHtml(bandeja.q)}».`, `<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="limpiarBusqueda()"><i class="fas fa-xmark" aria-hidden="true"></i><span>Limpiar búsqueda</span></button>`]
        : (bandeja.estado !== ''
            ? ['fa-filter', `No hay procesos en «${ESTADOS[bandeja.estado]}».`, `<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="filtrarEstado('')"><span>Ver todos</span></button>`]
            : ['fa-folder-open', 'Aún no hay procesos de crédito.', `<a href="${contenedor().dataset.urlRegistro}" class="btn btn-sm btn-primary ui-btn"><i class="fas fa-user-plus" aria-hidden="true"></i><span>Registrar cliente</span></a>`]);
    return `<div class="ui-vacio"><i class="fas ${icono}" aria-hidden="true"></i><p class="ui-vacio-titulo">${titulo}</p><div class="mt-3">${accion}</div></div>`;
}

const alternarLimpiar = () => el('limpiarBusqueda').classList.toggle('d-none', !el('busquedaProceso').value);

const buscar = function(){
    alternarLimpiar();
    clearTimeout(bandeja.temporizador);
    let q = el('busquedaProceso').value.trim();
    if(q.length && q.length < 3){
        return;
    }
    bandeja.temporizador = setTimeout(()=>{
        if(q !== bandeja.q){
            bandeja.q = q;
            bandeja.pagina = 1;
            cargarBandeja();
        }
    }, 300);
}

const limpiarBusqueda = function(){
    el('busquedaProceso').value = '';
    alternarLimpiar();
    bandeja.q = '';
    bandeja.pagina = 1;
    cargarBandeja();
    el('busquedaProceso').focus();
}

const migas = id => `<nav class="ui-migas" aria-label="Ruta de navegación"><a href="${urlBandeja()}" onclick="return volverBandeja(event)">Procesos</a><span aria-hidden="true">›</span><span class="actual" aria-current="page">Proceso #${escapeHtml(id)}</span></nav>`;

const abrirDetalle = async function(id){
    detalle = Object.assign(detalle, {id:id, datos:null, centrales:null, editando:false, aviso:false, mensaje:''});
    mostrarVista('detalle');
    el('vistaDetalle').innerHTML = migas(id) + cargador('Cargando proceso…');
    try{
        let datos = await peticion('detalle-proceso', {idProceso:id});
        if(detalle.id != id){
            return;
        }
        detalle.datos = datos;
        pintarDetalle();
        el('tituloDetalle').focus();
    }catch(e){
        let texto = e.status == 403 ? 'No tienes acceso a este proceso.' : (e.status == 422 ? 'No encontramos el proceso solicitado.' : 'No fue posible cargar el proceso.');
        el('vistaDetalle').innerHTML = migas(id) + alerta('error','fa-circle-exclamation',texto,`<a href="${urlBandeja()}" class="btn btn-sm ui-btn ui-btn-sec" onclick="return volverBandeja(event)"><i class="fas fa-arrow-left" aria-hidden="true"></i><span>Volver a la bandeja</span></a>`,'alert');
    }
}

const recargarDetalle = async function(mensaje){
    try{
        detalle.datos = await peticion('detalle-proceso', {idProceso:detalle.id});
    }catch(e){
        return fallo(e);
    }
    detalle.editando = false;
    detalle.calculo = null;
    pintarDetalle();
    if(mensaje){
        notificar(mensaje);
    }
    el('tituloPanel').focus();
}

const ultimoCambio = estado => [...detalle.datos.historial].reverse().find(h=>h.estadoNuevo == estado);

const pasoRechazo = function(){
    let h = ultimoCambio(0);
    return h && h.estadoAnterior ? h.estadoAnterior : 1;
}

const pintarStepper = function(estado){
    let rechazadoEn = estado == 0 ? pasoRechazo() : 0;
    return PASOS.map((nombre,i)=>{
        let n = i + 1;
        let tipo = rechazadoEn ? (n < rechazadoEn ? 'completo' : (n == rechazadoEn ? 'rechazado' : 'pendiente')) : (n < estado || estado == 5 ? 'completo' : (n == estado ? 'actual' : 'pendiente'));
        let marca = tipo == 'completo' ? '<i class="fas fa-check"></i>' : (tipo == 'rechazado' ? '<i class="fas fa-xmark"></i>' : n);
        let texto = {completo:'Completado', actual:'Etapa actual', pendiente:'Pendiente', rechazado:'Rechazado'}[tipo];
        return `<li class="ui-paso is-${tipo}"${tipo == 'actual' ? ' aria-current="step"' : ''}><span class="ui-paso-cont"><span class="ui-paso-marca" aria-hidden="true">${marca}</span><span><span class="ui-paso-texto">${nombre}</span><span class="ui-paso-estado">${texto}</span></span></span></li>`;
    }).join('');
}

const tarjeta = (etiqueta, valor, destacada=false) => `<div class="ui-tarjeta${destacada ? ' destacada' : ''}"><span class="ui-cifra-etiqueta">${etiqueta}</span><span class="ui-cifra">${valor}</span></div>`;
const moneda = valor => valor == null ? '—' : formatearMoneda(valor);
const datosLista = filas => `<dl class="ui-datos">${filas.map(([dt,dd])=>`<dt>${dt}</dt><dd>${dd || '—'}</dd>`).join('')}</dl>`;

const pintarDetalle = function(){
    let d = detalle.datos, p = d.proceso, t = d.tercero, f = d.financiero;
    detalle.nombre = `${t.nombres || ''} ${t.apellidos || ''}`.trim();
    let meta = [`${escapeHtml(t.tipoDocumento || 'Documento')} ${escapeHtml(t.documento)}`, escapeHtml(t.pagaduria || 'Sin pagaduría'), `Asesor: ${escapeHtml(p.asesor || '—')}`, `Creado el ${fechaHtml(p.fechaCreacion)}`];
    let resumen = p.estado == 0 ? `Rechazado en ${ESTADOS[pasoRechazo()]}` : (p.estado == 5 ? 'Crédito aprobado' : `Etapa ${p.estado} de 5 · ${ESTADOS[p.estado]}`);
    let linea = [f.tasa != null ? `Tasa ${formatearPorcentaje(f.tasa)} mensual` : '', f.seguro != null ? `Seguro ${formatearMoneda(f.seguro)}` : '', t.ingresos != null ? `Ingresos ${formatearMoneda(t.ingresos)}` : ''].filter(Boolean).join(' · ');
    el('vistaDetalle').innerHTML = `${migas(p.IdProceso)}
        <header class="ui-encabezado">
            <div>
                <h1 class="ui-titulo proc-titulo" id="tituloDetalle" tabindex="-1">${escapeHtml(detalle.nombre)} ${badgeEstado(p.estado)}</h1>
                <p class="ui-descripcion">${meta.join(' · ')}</p>
            </div>
            <div class="ui-acciones">
                ${f.cupo != null ? `<a href="${globalUrl}/descargar-info-credito/${Number(p.IdProceso)}" class="btn btn-sm ui-btn ui-btn-sec"><i class="fas fa-download" aria-hidden="true"></i><span>Estudio de crédito</span></a>` : ''}
                <a href="${urlBandeja()}" class="btn btn-sm ui-btn ui-btn-sec" onclick="return volverBandeja(event)"><i class="fas fa-arrow-left" aria-hidden="true"></i><span>Bandeja</span></a>
            </div>
        </header>
        <nav aria-label="Etapas del proceso">
            <ol class="ui-stepper">${pintarStepper(p.estado)}</ol>
            <p class="ui-stepper-resumen d-sm-none">${resumen}</p>
        </nav>
        <div class="ui-tarjetas">${tarjeta('Cupo',moneda(f.cupo))}${tarjeta('Monto solicitado',moneda(f.monto))}${tarjeta('Cuota total',moneda(f.cuotaTotal),true)}${tarjeta('Plazo',f.plazo != null ? `${f.plazo} meses` : '—')}</div>
        ${linea ? `<p class="proc-linea">${linea}</p>` : ''}
        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <section class="ui-card" id="panelEtapa" aria-labelledby="tituloPanel">${pintarPanel()}</section>
            </div>
            <div class="col-12 col-lg-4">
                <section class="ui-card" aria-labelledby="tituloCliente">
                    <div class="ui-card-cab"><h2 id="tituloCliente">Datos del cliente</h2></div>
                    ${datosLista([
                        ['Documento', `${escapeHtml(t.tipoDocumento || '')} ${escapeHtml(t.documento)}`],
                        ['Expedición', [fechaTexto(t.fechaExpedicion), escapeHtml(t.lugarExpedicion || '')].filter(Boolean).join(' · ')],
                        ['Nacimiento', fechaTexto(t.fechaNacimiento)],
                        ['Teléfono', escapeHtml(t.telefono || '')],
                        ['Correo', escapeHtml(t.email || '')],
                        ['Dirección', escapeHtml(t.direccion || '')],
                        ['Ciudad', [t.ciudad, t.departamento].filter(Boolean).map(escapeHtml).join(', ')],
                        ['Pagaduría', escapeHtml(t.pagaduria || '')],
                        ['Ingresos', t.ingresos != null ? formatearMoneda(t.ingresos) : '']
                    ])}
                </section>
                <section class="ui-card" aria-labelledby="tituloHistorial">
                    <div class="ui-card-cab"><h2 id="tituloHistorial">Historial</h2></div>
                    <div id="historialProceso">${pintarHistorial()}</div>
                </section>
            </div>
        </div>`;
    despuesDePanel();
}

const pintarHistorial = function(){
    let lista = [...detalle.datos.historial].reverse();
    if(!lista.length){
        return '<p class="ui-campo-ayuda m-0">Sin movimientos registrados.</p>';
    }
    return `<ol class="ui-timeline">${lista.map(h=>{
        let eventos = eventosConsulta(h);
        if(eventos){
            return `<li class="ui-timeline-item is-consulta">${fechaHtml(h.fecha,'ui-timeline-fecha',true)}${eventos.map(e=>`<p class="ui-timeline-titulo">${tituloConsulta(e)}</p>`).join('')}${autorHistorial(h)}</li>`;
        }
        let titulo = h.estadoAnterior == null ? ESTADOS[h.estadoNuevo] : `${ESTADOS[h.estadoAnterior]} → ${ESTADOS[h.estadoNuevo]}`;
        let nota = h.motivo || h.observacion ? `<p class="ui-timeline-nota${h.estadoNuevo == 0 ? ' is-rechazo' : ''}">${h.motivo ? `<strong>Motivo: ${escapeHtml(h.motivo)}</strong>${h.observacion ? '<br>' : ''}` : ''}${escapeHtml(h.observacion || '')}</p>` : '';
        return `<li class="ui-timeline-item ui-estado-${Number(h.estadoNuevo)}">${fechaHtml(h.fecha,'ui-timeline-fecha',true)}<p class="ui-timeline-titulo">${titulo}</p>${autorHistorial(h)}${nota}</li>`;
    }).join('')}</ol>`;
}

const autorHistorial = h => `<p class="ui-timeline-autor">${escapeHtml(h.usuario || 'Sistema')}${h.rol ? ` (${escapeHtml(h.rol)})` : ''}</p>`;

const eventosConsulta = function(h){
    let m = h.estadoAnterior != null && h.estadoAnterior == h.estadoNuevo && String(h.observacion || '').match(/^Consulta de centrales: (.+)$/);
    return m ? m[1].split('; ').map(parte=>{ let p = parte.match(/^(.*) \(([^()]*)\)$/) || [null, parte, '']; return {nombre:p[1], tipo:p[2]}; }) : null;
}

const tituloConsulta = function(e){
    let n = escapeHtml(e.nombre), fallo = e.tipo.match(/^error: (\w+)$/);
    let texto = fallo ? `Consulta ${n} fallida: ${escapeHtml(FALLOS_CONSULTA[fallo[1]] || fallo[1])}` : (e.tipo == 'reutilizada' ? `Consulta ${n} reutilizada` : (/forzada/.test(e.tipo) ? `Consulta ${n} forzada` : `Consulta ${n} realizada`));
    return texto + (/^simulado/i.test(e.nombre) ? ' <span class="ui-badge ui-badge-adv">Simulada</span>' : '');
}

const pintarPanel = function(){
    let estado = detalle.datos.proceso.estado;
    let titulo = estado == 0 ? 'Proceso rechazado' : (estado == 5 ? 'Crédito aprobado' : `Etapa actual: ${ESTADOS[estado]}`);
    let cuerpo = [panelCerrado, panelRegistro, panelCentrales, panelDocumentos, panelAprobacion, panelCerrado][estado]();
    let aviso = detalle.aviso ? alerta('adv','fa-triangle-exclamation',`${estado == 5 ? 'Crédito aprobado' : 'Proceso rechazado'}, pero no fue posible enviar el correo.`,botonReenviar(),'alert') : '';
    return `<div class="ui-card-cab"><h2 id="tituloPanel" tabindex="-1">${titulo}</h2></div>${aviso}${cuerpo}`;
}

const repintarPanel = function(){
    el('panelEtapa').innerHTML = pintarPanel();
    despuesDePanel();
}

const despuesDePanel = function(){
    let estado = detalle.datos.proceso.estado;
    anchoProgreso();
    if(estado == 2 && puede('centrales')){
        cargarCentrales();
    }
    if(estado == 4 && detalle.editando){
        cargarPlazos();
    }
}

const avisoGestion = estado => alerta('info','fa-circle-info',`Esta etapa la gestiona ${GESTORES[estado]}. Puedes consultar la información.`);
const botonReenviar = () => `<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="reenviarCorreo(this)"><i class="fas fa-envelope" aria-hidden="true"></i><span>Reenviar correo</span></button>`;

const pie = function(principal, textoRechazo='Rechazar'){
    let rechazar = puede('rechazar') ? `<button type="button" class="btn btn-outline-danger ui-btn me-auto" onclick="abrirRechazo(this)"><i class="fas fa-ban" aria-hidden="true"></i><span>${textoRechazo}</span></button>` : '';
    return rechazar || principal ? `<div class="ui-card-pie">${rechazar}${principal}</div>` : '';
}

const panelRegistro = function(){
    if(!puede('registro')){
        return avisoGestion(1) + pie('');
    }
    return alerta('info','fa-circle-info','El registro del cliente aún no termina. Complétalo para enviar el proceso a centrales de riesgo.') + pie(`<a href="${contenedor().dataset.urlRegistro}?proceso=${Number(detalle.id)}" class="btn btn-primary ui-btn"><span>Continuar registro</span><i class="fas fa-arrow-right" aria-hidden="true"></i></a>`);
}

const panelCentrales = function(){
    if(!puede('centrales')){
        return avisoGestion(2) + pie('');
    }
    return `<div id="informeCentrales">${pintarCentrales()}</div>`;
}

const gestionaCentrales = () => detalle.datos.proceso.estado == 2 && puede('centrales');
const proveedorCentral = clave => ((detalle.centrales.estado || {}).proveedores || []).find(p=>p.clave == clave);
const nombreCentral = clave => (proveedorCentral(clave) || {}).nombre || clave.charAt(0).toUpperCase() + clave.slice(1);
const numero = valor => valor == null ? '—' : Number(valor).toLocaleString('es-CO');
const textoTipo = valor => valor ? String(valor).replace(/_/g,' ').replace(/^./, m=>m.toUpperCase()) : '—';
const nivelScore = s => !s ? ['inactivo','fa-circle-question','Sin score'] : (NIVEL_RIESGO[String(s.nivel || '').toLowerCase()] || ['inactivo','fa-circle-question', s.nivel ? `Nivel ${s.nivel}` : 'Sin nivel']);
const marcarScore = () => document.querySelectorAll('.ui-score-marca[data-posicion]').forEach(m=>m.style.setProperty('--pos', m.dataset.posicion + '%'));
const vacio = (icono, texto, rol='') => `<div class="ui-vacio"${rol ? ` role="${rol}"` : ''}><i class="fas ${icono}" aria-hidden="true"></i><p class="ui-vacio-titulo">${texto}</p></div>`;

const cargarCentrales = async function(){
    if(!el('informeCentrales')){
        return;
    }
    if(detalle.centrales && !detalle.centrales.error){
        return pintarInforme();
    }
    detalle.centrales = null;
    pintarInforme();
    let id = detalle.id, datos;
    try{
        let [estado, res] = await Promise.all([peticion('centrales-estado', {idProceso:id}), peticion('centrales-resultados', {idProceso:id})]);
        let resultados = {};
        Object.entries(res.resultados || {}).forEach(([clave,consulta])=>{ resultados[clave] = {ok:true, simulado:consulta.simulado, consulta:consulta}; });
        let claves = Object.keys(resultados);
        datos = {estado:estado, resultados:resultados, vista:claves.length ? 'resultados' : 'seleccion', activa:claves[0] || null};
    }catch(e){
        datos = {error:true};
    }
    if(detalle.id != id){
        return;
    }
    detalle.centrales = datos;
    pintarInforme();
}

const pintarInforme = function(){
    let destino = el('informeCentrales');
    if(!destino){
        return;
    }
    destino.innerHTML = pintarCentrales();
    marcarScore();
    if(el('formCentrales')){
        actualizarSeleccion();
    }
}

const pintarCentrales = function(){
    let c = detalle.centrales;
    if(!c){
        return cargador('Cargando centrales de riesgo…');
    }
    if(c.error){
        return alerta('error','fa-circle-exclamation','No fue posible cargar las centrales de riesgo.',`<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="cargarCentrales()"><i class="fas fa-rotate-right" aria-hidden="true"></i><span>Reintentar</span></button>`,'alert');
    }
    let gestion = gestionaCentrales();
    return gestion && c.vista == 'seleccion' ? seleccionCentrales() : resultadosCentrales(gestion);
}

const seleccionCentrales = function(){
    let c = detalle.centrales, e = c.estado, lista = e.proveedores || [];
    if(!lista.length){
        return alerta('adv','fa-triangle-exclamation','No hay proveedores de centrales de riesgo disponibles. Contacta al administrador del sistema.') + pie('');
    }
    let bloqueo = !e.tratamientoCompleto;
    let opciones = lista.map(p=>{
        let k = escapeHtml(p.clave), v = p.vigente;
        let estado = v ? `<i class="fas fa-clock-rotate-left" aria-hidden="true"></i><span>Vigente hasta ${fechaTexto(v.vigenteHasta)}: se reutilizará</span>` : '<i class="fas fa-coins" aria-hidden="true"></i><span>Sin consulta vigente: se realizará una nueva</span>';
        return `<div><input type="checkbox" class="btn-check" id="central-${k}" value="${k}" aria-describedby="estado-central-${k}"${p.predeterminado ? ' checked' : ''} onchange="actualizarSeleccion()">
            <label class="ui-opcion" for="central-${k}"><i class="fas fa-building-columns ui-opcion-icono" aria-hidden="true"></i><span>
                <span class="ui-opcion-titulo">${escapeHtml(p.nombre)}</span>
                <span class="ui-opcion-estado${v ? ' is-vigente' : ''}" id="estado-central-${k}">${estado}</span>
                ${p.simulado ? '<span class="ui-opcion-desc">Solo para pruebas. No usar para decidir el crédito.</span>' : ''}
                ${p.pendienteValidar ? `<span class="ui-opcion-desc">${escapeHtml(p.pendienteValidar)}</span>` : ''}
                ${p.predeterminado ? '<span class="ui-badge ui-badge-info">Predeterminado</span> ' : ''}${p.simulado ? '<span class="ui-badge ui-badge-adv">Datos simulados</span>' : ''}
            </span><i class="fas fa-circle-check ui-opcion-check" aria-hidden="true"></i></label></div>`;
    }).join('');
    let forzar = e.puedeForzar ? `<div class="form-check form-switch mt-3" id="grupoForzar" hidden><input class="form-check-input" type="checkbox" role="switch" id="forzarCentrales" aria-describedby="ayudaForzar" onchange="actualizarSeleccion()"><label class="form-check-label" for="forzarCentrales">Forzar nueva consulta</label><span class="ui-campo-ayuda" id="ayudaForzar">Ignora la consulta vigente y genera un nuevo cobro.</span></div>` : '';
    let volver = Object.keys(c.resultados).length ? `<button type="button" class="btn ui-btn ui-btn-sec" onclick="verVistaCentrales('resultados')"><i class="fas fa-arrow-left" aria-hidden="true"></i><span>Volver a resultados</span></button>` : '';
    return `${bloqueo ? `<div id="avisoTratamiento">${alerta('adv','fa-triangle-exclamation','La autorización de tratamiento de datos no está completa. No es posible consultar centrales.')}</div>` : ''}
        ${lista.some(p=>p.predeterminado) ? '' : alerta('info','fa-circle-info','No hay un proveedor predeterminado disponible. Selecciona en cuál consultar.')}
        <form id="formCentrales" novalidate onsubmit="consultarCentrales(event)">
            <fieldset class="ui-seccion"${bloqueo ? ' disabled aria-describedby="avisoTratamiento"' : ''}>
                <legend class="ui-seccion-titulo">Consultar en</legend>
                <div class="ui-opciones">${opciones}</div>
                <span class="invalid-feedback" role="alert" id="error-proveedoresCentrales"></span>
                ${forzar}
                <p class="ui-campo-ayuda mb-0" id="resumenCentrales" aria-live="polite"></p>
            </fieldset>
            <div id="errorConsulta"></div>
            <ul class="ui-docs d-none" id="progresoCentrales" aria-live="polite"></ul>
            ${pie(`${volver}<button type="submit" class="btn btn-primary ui-btn" id="btnConsultarCentrales"${bloqueo ? ' disabled aria-describedby="avisoTratamiento"' : ''}><i class="fas fa-magnifying-glass" aria-hidden="true"></i><span>Consultar</span></button>`)}
        </form>`;
}

const seleccionadas = () => [...document.querySelectorAll('#formCentrales .btn-check:checked')].map(i=>i.value);

const actualizarSeleccion = function(){
    let claves = seleccionadas(), forzar = el('forzarCentrales');
    let vigentes = claves.filter(k=>(proveedorCentral(k) || {}).vigente);
    if(forzar){
        el('grupoForzar').hidden = !vigentes.length;
        forzar.checked = forzar.checked && vigentes.length > 0;
    }
    let nuevas = claves.filter(k=>(forzar && forzar.checked) || !vigentes.includes(k)), reutilizadas = claves.filter(k=>!nuevas.includes(k));
    let partes = [];
    if(nuevas.length){
        partes.push(`${nuevas.length} ${nuevas.length == 1 ? 'consulta nueva' : 'consultas nuevas'} (${nuevas.map(nombreCentral).join(', ')})`);
    }
    if(reutilizadas.length){
        partes.push(`${reutilizadas.length} ${reutilizadas.length == 1 ? 'reutilizada' : 'reutilizadas'} (${reutilizadas.map(nombreCentral).join(', ')})`);
    }
    el('resumenCentrales').textContent = partes.length ? 'Resumen: ' + partes.join(' · ') : '';
    marcarError('proveedoresCentrales', '');
    el('btnConsultarCentrales').innerHTML = claves.length && !nuevas.length ? '<i class="fas fa-eye" aria-hidden="true"></i><span>Ver resultados</span>' : '<i class="fas fa-magnifying-glass" aria-hidden="true"></i><span>Consultar</span>';
}

const confirmarForzar = async claves => (await Swal.fire({title:'¿Forzar nueva consulta?', text:`Se generará una consulta nueva con costo en ${claves.map(nombreCentral).join(' y ')}, aunque la actual siga vigente.`, icon:'warning', showCancelButton:true, confirmButtonText:'Sí, consultar', cancelButtonText:'Cancelar', confirmButtonColor:'rgb(65,110,195)', reverseButtons:true})).isConfirmed;

const consultarCentrales = async function(e){
    e.preventDefault();
    let claves = seleccionadas(), forzar = !!(el('forzarCentrales') && el('forzarCentrales').checked);
    let error = !claves.length ? 'Selecciona al menos un proveedor.' : (claves.length > 2 ? 'Selecciona como máximo dos proveedores.' : '');
    marcarError('proveedoresCentrales', error);
    if(error){
        return document.querySelector('#formCentrales .btn-check').focus();
    }
    let vigentes = claves.filter(k=>proveedorCentral(k).vigente);
    if(!forzar && vigentes.length == claves.length){
        return verGuardadas(claves, el('btnConsultarCentrales'));
    }
    if(forzar && !await confirmarForzar(vigentes)){
        return;
    }
    await ejecutarConsulta(claves, forzar, el('btnConsultarCentrales'));
}

const mostrarResultados = function(activa){
    detalle.centrales.activa = activa;
    detalle.centrales.vista = 'resultados';
    pintarInforme();
    enfocarCentrales();
}

const verGuardadas = async function(claves, boton){
    let id = detalle.id;
    let res = await cargando(boton, 'Cargando…', async ()=>{
        try{
            return await peticion('centrales-resultados', {idProceso:id});
        }catch(err){
            if(err.status != 403 && el('errorConsulta')){
                el('errorConsulta').innerHTML = alerta('error','fa-circle-exclamation',escapeHtml(mensajeErrorHttp(err.data)),'','alert');
            }
        }
    });
    if(!res || detalle.id != id){
        return;
    }
    Object.entries(res.resultados || {}).forEach(([clave,consulta])=>{ detalle.centrales.resultados[clave] = {ok:true, simulado:consulta.simulado, consulta:consulta}; });
    mostrarResultados(claves[0]);
}

const reconsultarCentral = async function(clave, forzar, boton){
    if(forzar && !await confirmarForzar([clave])){
        return;
    }
    await ejecutarConsulta([clave], forzar, boton);
}

const filaProgreso = (clave, estado) => `<li class="ui-doc"><span class="ui-doc-nombre">${escapeHtml(nombreCentral(clave))}</span><span class="ui-doc-meta">${{cargando:'<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Consultando…', listo:'<i class="fas fa-circle-check" aria-hidden="true"></i> Listo', error:'<i class="fas fa-circle-xmark" aria-hidden="true"></i> Error'}[estado]}</span></li>`;

const ejecutarConsulta = async function(claves, forzar, boton){
    let progreso = el('progresoCentrales'), destino = el('errorConsulta'), id = detalle.id;
    if(destino){
        destino.innerHTML = '';
    }
    if(progreso){
        progreso.innerHTML = claves.map(k=>filaProgreso(k,'cargando')).join('');
        progreso.classList.remove('d-none');
    }
    let datos = new FormData();
    datos.append('idProceso', id);
    claves.forEach(k=>datos.append('proveedores[]', k));
    datos.append('forzar', forzar ? 1 : 0);
    let res = await cargando(boton, 'Consultando…', async ()=>{
        try{
            return await peticion('centrales-consultar', datos);
        }catch(err){
            if(progreso){
                progreso.classList.add('d-none');
            }
            if(err.status != 403){
                let texto = err.data && err.data.errors && err.data.errors.tratamiento ? 'El cliente no ha completado la autorización de tratamiento de datos. No es posible consultar.' : escapeHtml(mensajeErrorHttp(err.data));
                destino ? destino.innerHTML = alerta('error','fa-circle-exclamation',texto,'','alert') : mostrarErrorHttp(err.data, err.status);
            }
        }
    });
    if(!res || detalle.id != id){
        return;
    }
    let c = detalle.centrales;
    if(progreso){
        progreso.innerHTML = claves.map(k=>filaProgreso(k, (res.resultados[k] || {}).ok ? 'listo' : 'error')).join('');
    }
    Object.entries(res.resultados).forEach(([clave,r])=>{
        c.resultados[clave] = Object.assign({reciente:true}, r);
        let p = proveedorCentral(clave);
        if(r.ok && p){
            p.vigente = r.consulta.vigente ? {fechaConsulta:r.consulta.fechaConsulta, vigenteHasta:r.consulta.vigenteHasta} : null;
        }
    });
    mostrarResultados(claves[0]);
    peticion('detalle-proceso', {idProceso:id}).then(d=>{
        if(detalle.id == id && el('historialProceso')){
            detalle.datos.historial = d.historial;
            el('historialProceso').innerHTML = pintarHistorial();
        }
    }).catch(()=>{});
}

const enfocarCentrales = () => (document.querySelector('#informeCentrales [role="tab"][aria-selected="true"]') || el('tituloPanel')).focus();

const verVistaCentrales = function(vista){
    detalle.centrales.vista = vista;
    pintarInforme();
    vista == 'seleccion' ? (document.querySelector('#formCentrales .btn-check:checked') || document.querySelector('#formCentrales .btn-check') || el('tituloPanel')).focus() : enfocarCentrales();
}

const elegirPestana = function(id){
    detalle.centrales.activa = id;
    document.querySelectorAll('#informeCentrales [role="tab"]').forEach(t=>{
        let activa = t.id == 'tab-central-' + id;
        t.setAttribute('aria-selected', activa);
        t.tabIndex = activa ? 0 : -1;
        el(t.getAttribute('aria-controls')).hidden = !activa;
        if(activa){
            t.focus();
        }
    });
}

const teclaPestana = function(e){
    let tabs = [...e.currentTarget.querySelectorAll('[role="tab"]')], i = tabs.indexOf(document.activeElement);
    let destino = {ArrowRight:i + 1, ArrowLeft:i - 1, Home:0, End:tabs.length - 1}[e.key];
    if(i < 0 || destino == null){
        return;
    }
    e.preventDefault();
    elegirPestana(tabs[(destino + tabs.length) % tabs.length].id.replace('tab-central-',''));
}

const resultadosCentrales = function(gestion){
    let c = detalle.centrales, claves = Object.keys(c.resultados);
    if(!claves.length){
        return vacio('fa-file-circle-question','No hay consultas de centrales registradas para este proceso.');
    }
    let exitosas = claves.filter(k=>c.resultados[k].ok);
    let pestanas = claves.map(k=>({id:k, nombre:nombreCentral(k), r:c.resultados[k], html:reporteCentral(k, gestion)}));
    if(exitosas.length >= 2){
        pestanas.push({id:'comparar', nombre:'Comparar', html:comparativa(claves)});
    }
    if(!pestanas.some(p=>p.id == c.activa)){
        c.activa = pestanas[0].id;
    }
    claves.forEach(k=>{ c.resultados[k].reciente = false; });
    let cuerpo = pestanas.length == 1 ? pestanas[0].html : `<div class="ui-pestanas" role="tablist" aria-label="Resultados por proveedor" onkeydown="teclaPestana(event)">${pestanas.map(p=>{
        let activa = p.id == c.activa, id = escapeHtml(p.id);
        return `<button type="button" role="tab" class="ui-pestana" id="tab-central-${id}" aria-selected="${activa}" aria-controls="panel-central-${id}" tabindex="${activa ? 0 : -1}" onclick="elegirPestana('${id}')"><span>${escapeHtml(p.nombre)}</span>${p.r && p.r.ok && p.r.simulado ? '<span class="ui-badge ui-badge-adv">Simulado</span>' : ''}${noDisponible(p.r) ? '<span class="ui-badge ui-badge-inactivo">No disponible</span>' : ''}${p.r && !p.r.ok ? '<i class="fas fa-circle-exclamation" aria-hidden="true"></i><span class="visually-hidden"> (con error)</span>' : ''}</button>`;
    }).join('')}</div>${pestanas.map(p=>`<div class="ui-pestana-panel" role="tabpanel" id="panel-central-${escapeHtml(p.id)}" aria-labelledby="tab-central-${escapeHtml(p.id)}" tabindex="0"${p.id == c.activa ? '' : ' hidden'}>${p.html}</div>`).join('')}`;
    if(!gestion){
        return cuerpo;
    }
    let ayuda = !c.estado.tratamientoCompleto ? 'La autorización de tratamiento de datos no está completa.' : (!exitosas.length ? 'Consulta al menos una central de riesgo para aprobar.' : (exitosas.some(k=>c.resultados[k].consulta.vigente && c.resultados[k].disponible !== false && !noDisponible(c.resultados[k])) ? '' : 'No hay una consulta vigente de un proveedor disponible. Consulta de nuevo para aprobar.'));
    return cuerpo + pie(`${ayuda ? `<span class="ui-campo-ayuda m-0" id="ayudaAprobarCentrales">${ayuda}</span>` : ''}<button type="button" class="btn btn-primary ui-btn" onclick="abrirCentrales(this)"${ayuda ? ' disabled aria-describedby="ayudaAprobarCentrales"' : ''}><i class="fas fa-check" aria-hidden="true"></i><span>Aprobar centrales</span><i class="fas fa-arrow-right" aria-hidden="true"></i></button>`);
}

const errorCentral = function(clave, r, gestion){
    let n = escapeHtml(nombreCentral(clave)), rol = r.reciente ? 'alert' : '';
    let reintentar = gestion && proveedorCentral(clave) ? `<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="reconsultarCentral('${escapeHtml(clave)}', false, this)"><i class="fas fa-rotate-right" aria-hidden="true"></i><span>Reintentar</span></button>` : '';
    let casos = {
        sin_credenciales: ()=>alerta('adv','fa-triangle-exclamation',`${n} no tiene credenciales configuradas. Contacta al administrador del sistema.`,'',rol),
        conexion: ()=>alerta('error','fa-circle-exclamation',`No fue posible conectar con ${n}. Intenta de nuevo en unos minutos.`,reintentar,rol),
        deshabilitado: ()=>alerta('info','fa-circle-info',`${n} está deshabilitado en la administración del sitio.`,'',rol),
        datos_invalidos: ()=>alerta('adv','fa-triangle-exclamation',`El tipo de documento del cliente no es compatible con ${n}.`,'',rol),
        respuesta_invalida: ()=>vacio('fa-file-circle-question',`${n} no encontró información para este documento.`,rol)
    };
    return (casos[r.codigo] || (()=>alerta('error','fa-circle-exclamation',escapeHtml(r.error || `No fue posible consultar ${nombreCentral(clave)}.`),reintentar,rol)))();
}

const scoreCentral = function(s){
    let nivel = nivelScore(s), valor = s && s.valor != null ? Number(s.valor) : null;
    let rango = s && s.rangoMin != null && s.rangoMax != null && Number(s.rangoMax) > Number(s.rangoMin);
    let medidor = rango && valor != null ? `<div class="ui-score-medidor" role="meter" aria-label="Score" aria-valuemin="${Number(s.rangoMin)}" aria-valuemax="${Number(s.rangoMax)}" aria-valuenow="${valor}" aria-valuetext="${valor} de ${Number(s.rangoMax)}, ${escapeHtml(nivel[2].toLowerCase())}"><span class="ui-score-marca" data-posicion="${Math.min(100, Math.max(0, (valor - s.rangoMin) * 100 / (s.rangoMax - s.rangoMin)))}"></span></div><div class="ui-score-escala" aria-hidden="true"><span>${Number(s.rangoMin)}</span><span>${Number(s.rangoMax)}</span></div>` : '';
    return `<div class="ui-score"><span class="ui-cifra-etiqueta">Score</span><span class="ui-score-valor">${valor != null ? valor : '—'}</span><span class="ui-score-info"><span class="ui-badge ui-badge-${nivel[0]} ui-score-nivel"><i class="fas ${nivel[1]}" aria-hidden="true"></i>${escapeHtml(nivel[2])}</span>${rango ? `<span class="ui-score-rango">de ${Number(s.rangoMin)} a ${Number(s.rangoMax)}</span>` : ''}</span>${medidor}</div>`;
}

const cifraCentral = (etiqueta, valor, enAlerta=false) => `<div class="ui-tarjeta"><span class="ui-cifra-etiqueta">${etiqueta}</span><span class="ui-cifra${enAlerta ? ' is-alerta' : ''}">${enAlerta ? '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i><span class="visually-hidden">Alerta: </span>' : ''}${valor}</span></div>`;

const noDisponible = r => !!(r && r.ok && r.consulta && r.consulta.disponible === false);
const etiquetaHuella = c => Number(c.huellaMeses) > 0 ? `Consultas últimos ${Number(c.huellaMeses)} meses` : 'Consultas recientes';

const reporteCentral = function(clave, gestion){
    let r = detalle.centrales.resultados[clave], nombre = escapeHtml(nombreCentral(clave)), k = escapeHtml(clave), p = proveedorCentral(clave);
    let repetir = gestion && r.ok && p && !noDisponible(r) ? (r.consulta.vigente ? (detalle.centrales.estado.puedeForzar ? `<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="reconsultarCentral('${k}', true, this)"><i class="fas fa-rotate" aria-hidden="true"></i><span>Forzar nueva consulta</span></button>` : '') : `<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="reconsultarCentral('${k}', false, this)"><i class="fas fa-rotate" aria-hidden="true"></i><span>Consultar de nuevo</span></button>`) : '';
    let acciones = gestion ? `<div class="ui-acciones">${repetir}<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="verVistaCentrales('seleccion')"><i class="fas fa-right-left" aria-hidden="true"></i><span>Cambiar proveedores</span></button></div>` : '';
    if(!r.ok){
        return `<div class="ui-reporte-cab"><div><h3>${nombre}</h3></div>${acciones}</div>${errorCentral(clave, r, gestion)}`;
    }
    let c = r.consulta, d = c.datosBasicos || {}, t = c.totales || {}, amb = AMBIENTE_CENTRAL[c.ambiente];
    let badges = (r.reutilizada === true ? '<span class="ui-badge ui-badge-inactivo">Reutilizada</span>' : (r.reutilizada === false ? '<span class="ui-badge ui-badge-info">Nueva</span>' : (c.vigente ? '<span class="ui-badge ui-badge-activo">Vigente</span>' : '<span class="ui-badge ui-badge-inactivo">Vencida</span>'))) + (noDisponible(r) ? '<span class="ui-badge ui-badge-inactivo">No disponible</span>' : '')
        + (amb ? `<span class="ui-badge ui-badge-${amb[0]}">${amb[1]}</span>` : '') + (c.simulado ? '<span class="ui-badge ui-badge-adv">Datos simulados</span>' : '');
    let datos = datosLista([['Fecha de consulta', fechaHtml(c.fechaConsulta,'',true)], ['Vigente hasta', fechaHtml(c.vigenteHasta)], ['Consultada por', escapeHtml(c.usuario || '')]].concat(d.numeroInforme ? [['Nº de informe', escapeHtml(d.numeroInforme)]] : []));
    let obligaciones = [...(c.obligaciones || [])].sort((a,b)=>(b.estado == 'mora') - (a.estado == 'mora') || (Number(b.saldo) || 0) - (Number(a.saldo) || 0));
    let filas = obligaciones.map(o=>{
        let [clase, texto] = ESTADOS_OBLIGACION[o.estado] || ['inactivo', escapeHtml(o.estadoReportado || '—')];
        return `<tr><td class="ui-celda-principal">${escapeHtml(o.entidad || '—')}${o.sector ? `<span class="proc-sub">Sector ${escapeHtml(o.sector)}</span>` : ''}</td><td data-label="Tipo">${escapeHtml(textoTipo(o.tipo))}</td><td data-label="Estado"><span class="ui-badge ui-badge-${clase}">${o.estado == 'mora' ? '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i> ' : ''}${texto}</span></td><td data-label="Saldo" class="num">${moneda(o.saldo)}</td><td data-label="Cuota" class="num">${moneda(o.cuota)}</td><td data-label="Mora" class="num">${Number(o.diasMora) > 0 ? `${Number(o.diasMora)} días` : '—'}</td><td data-label="Corte">${fechaHtml(o.fechaCorte)}</td></tr>`;
    }).join('');
    let total = `<tr class="is-total"><td class="ui-celda-principal">Total${obligaciones.some(o=>o.estado == 'cerrada') ? ' sin cerradas' : ''}</td><td></td><td></td><td data-label="Saldo" class="num">${moneda(t.saldoTotal)}</td><td data-label="Cuota" class="num">${moneda(t.cuotaMensual)}</td><td></td><td></td></tr>`;
    let tabla = obligaciones.length ? `<div class="ui-scroll"><table class="ui-tabla ui-tabla-tarjetas"><caption class="visually-hidden">Obligaciones reportadas por ${nombre}</caption><thead><tr><th scope="col">Entidad</th><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col" class="num">Saldo</th><th scope="col" class="num">Cuota</th><th scope="col" class="num">Mora</th><th scope="col">Corte</th></tr></thead><tbody>${filas}${total}</tbody></table></div>` : vacio('fa-file-circle-check','El cliente no tiene obligaciones reportadas.');
    return `${c.simulado ? alerta('adv','fa-flask','Datos simulados: esta consulta no proviene de una central de riesgo real. No la uses para decidir el crédito.','','note') : ''}
        ${p && p.pendienteValidar ? alerta('info','fa-circle-info',escapeHtml(p.pendienteValidar)) : ''}
        <div class="ui-reporte-cab"><div><h3>${nombre}${badges}</h3>${datos}</div>${acciones}</div>
        <div class="ui-tarjetas">${scoreCentral(c.score)}${cifraCentral('Al día', numero(t.obligacionesAlDia))}${cifraCentral('En mora', numero(t.obligacionesMora), t.obligacionesMora > 0)}${cifraCentral('Saldo total', moneda(t.saldoTotal))}${cifraCentral('Valor en mora', moneda(t.valorMora), t.valorMora > 0)}${cifraCentral('Cuota mensual total', moneda(t.cuotaMensual))}${cifraCentral(etiquetaHuella(c), numero((c.huella || []).length))}</div>
        ${(c.alertas || []).length ? `<section class="ui-seccion"><h4 class="ui-seccion-titulo">Alertas</h4>${c.alertas.map(a=>alerta('adv','fa-triangle-exclamation',escapeHtml(a))).join('')}</section>` : ''}
        <section class="ui-seccion"><h4 class="ui-seccion-titulo">Obligaciones (${obligaciones.length})</h4>${tabla}
            <p class="ui-campo-ayuda">Montos en pesos colombianos.${c.unidadOrigen == 'miles_de_pesos' ? ` ${nombre} reporta en miles de pesos; aquí se muestran convertidos a pesos.` : ''}</p>
        </section>`;
}

const comparativa = function(claves){
    let rs = claves.map(k=>detalle.centrales.resultados[k]);
    let filas = [
        ['Score', c=>[c.score ? c.score.valor : null, c.score ? `${c.score.valor != null ? escapeHtml(c.score.valor) : '—'} · ${escapeHtml(nivelScore(c.score)[2])}` : 'Sin score']],
        ['Rango', c=>{ let s = c.score, texto = s && s.rangoMin != null && s.rangoMax != null ? `${s.rangoMin} a ${s.rangoMax}` : '—'; return [texto, escapeHtml(texto)]; }],
        ['Al día', c=>[(c.totales || {}).obligacionesAlDia, numero((c.totales || {}).obligacionesAlDia)]],
        ['En mora', c=>[(c.totales || {}).obligacionesMora, numero((c.totales || {}).obligacionesMora)]],
        ['Saldo total', c=>[(c.totales || {}).saldoTotal, moneda((c.totales || {}).saldoTotal)]],
        ['Valor en mora', c=>[(c.totales || {}).valorMora, moneda((c.totales || {}).valorMora)]],
        ['Cuota mensual', c=>[(c.totales || {}).cuotaMensual, moneda((c.totales || {}).cuotaMensual)]],
        ['Consultas recientes', c=>[(c.huella || []).length, numero((c.huella || []).length)]],
        ['Alertas', c=>[(c.alertas || []).length, numero((c.alertas || []).length)]],
        ['Fecha de consulta', c=>[null, fechaHtml(c.fechaConsulta)], true]
    ];
    let cuerpo = filas.map(([titulo, f, sinComparar])=>{
        let celdas = rs.map(r=>r.ok ? f(r.consulta) : [null, 'Sin datos']);
        let difiere = !sinComparar && new Set(celdas.filter((x,i)=>rs[i].ok).map(x=>String(x[0]))).size > 1;
        return `<tr${difiere ? ' class="is-diferente"' : ''}><th scope="row">${titulo}</th>${celdas.map(x=>`<td class="num">${x[1]}</td>`).join('')}<td>${difiere ? '<span class="ui-badge ui-badge-adv">Difiere</span>' : ''}</td></tr>`;
    }).join('');
    return `<div class="ui-scroll"><table class="ui-tabla ui-comparativa"><caption>Comparación de resultados</caption><thead><tr><th scope="col"><span class="visually-hidden">Indicador</span></th>${claves.map(k=>`<th scope="col" class="num">${escapeHtml(nombreCentral(k))}</th>`).join('')}<th scope="col"><span class="visually-hidden">Diferencia</span></th></tr></thead><tbody>${cuerpo}</tbody></table></div>
        <p class="ui-campo-ayuda">Las diferencias pueden deberse a fechas de corte distintas entre centrales.</p>`;
}

const abrirCentrales = function(boton){
    el('observacionCentrales').value = '';
    contar('observacionCentrales');
    el('errorCentrales').innerHTML = '';
    abrirModal('modalCentrales', boton, 'observacionCentrales');
}

const transicion = async function(datos){
    let res = await peticion('cambiar-estado-proceso', Object.assign({idProceso:detalle.id}, datos));
    detalle.aviso = res.correo === false;
    return res;
}

const errorModal = (destino, e) => { if(e.status != 403){ el(destino).innerHTML = alerta('error','fa-circle-exclamation',escapeHtml(mensajeErrorHttp(e.data)),'','alert'); } };

const confirmarCentrales = async function(e){
    e.preventDefault();
    el('errorCentrales').innerHTML = '';
    await cargando(el('btnConfirmarCentrales'), 'Aprobando…', async ()=>{
        try{
            await transicion({estadoNuevo:3, observacion:el('observacionCentrales').value.trim()});
        }catch(err){
            return errorModal('errorCentrales', err);
        }
        cerrarModal('modalCentrales');
        await recargarDetalle('Centrales aprobadas');
    });
}

const resumenDocs = function(){
    let requeridos = detalle.datos.documentos.filter(d=>d.requerido);
    let aprobados = requeridos.filter(d=>d.estado == 'aprobado').length;
    return {total:requeridos.length, aprobados:aprobados, faltan:requeridos.length - aprobados};
}

const htmlProgreso = function(){
    let r = resumenDocs(), porcentaje = r.total ? Math.round(r.aprobados * 100 / r.total) : 100;
    return `<div class="ui-progreso mb-2" role="progressbar" aria-label="Documentos requeridos aprobados" aria-valuenow="${r.aprobados}" aria-valuemin="0" aria-valuemax="${r.total}" aria-valuetext="${r.aprobados} de ${r.total} aprobados"><span class="pista"><span class="relleno" data-porcentaje="${porcentaje}"></span></span><span class="cifra">${r.aprobados} de ${r.total} aprobados</span></div>`;
}

const anchoProgreso = () => document.querySelectorAll('.ui-progreso .relleno[data-porcentaje]').forEach(r=>{ r.style.width = r.dataset.porcentaje + '%'; });

const htmlPieDocs = function(){
    let r = resumenDocs(), enviar = puede('aprobarDocumentos');
    let ayuda = enviar && r.faltan ? `<span class="ui-campo-ayuda m-0" id="ayudaEnviar">Faltan ${r.faltan} ${r.faltan == 1 ? 'documento' : 'documentos'} por aprobar.</span>` : '';
    let boton = enviar ? `${ayuda}<button type="button" class="btn btn-primary ui-btn" onclick="enviarAprobacion(this)"${r.faltan ? ' disabled aria-describedby="ayudaEnviar"' : ''}><span>Enviar a aprobación</span><i class="fas fa-arrow-right" aria-hidden="true"></i></button>` : '';
    return (enviar && !r.faltan ? alerta('exito','fa-circle-check','Todos los documentos están aprobados.') : '') + pie(boton, 'Rechazar proceso');
}

const filaDoc = function(doc, soloLectura=false){
    let [etiqueta, clase] = ESTADOS_DOC[doc.estado] || ['Pendiente','inactivo'];
    let id = Number(doc.IdDocumento), nombre = escapeHtml(doc.nombre);
    let gestion = !soloLectura && detalle.datos.proceso.estado == 3;
    let meta = [doc.requerido ? '' : 'Opcional', doc.origen == 'digital' ? 'Autorización digital' : (doc.nombreArchivo ? escapeHtml(doc.nombreArchivo) : 'Sin archivo'), doc.nombreArchivo ? fechaHtml(doc.fecha) : ''].filter(Boolean).join(' · ');
    let acciones = [];
    let ver = doc.nombreArchivo ? `<a class="btn btn-sm ui-btn ui-btn-sec" href="${globalUrl}/ver-documento-soporte/${id}/${Number(detalle.id)}" target="_blank" rel="noopener"><i class="fas fa-eye" aria-hidden="true"></i><span>Ver</span><span class="visually-hidden"> ${nombre} (se abre en una pestaña nueva)</span></a>` : '';
    let cargar = gestion && puede('cargarDocumentos') && doc.estado != 'aprobado';
    if(!cargar && ver){
        acciones.push(ver);
    }
    if(cargar){
        acciones.push(`<label class="visually-hidden" for="archivo-${id}">Archivo PDF para ${nombre}</label><input type="file" class="form-control form-control-sm" id="archivo-${id}" accept="application/pdf" aria-describedby="error-doc-${id}" onchange="marcarErrorDoc(${id},'')"><button type="button" class="btn btn-sm btn-primary ui-btn" onclick="cargarDocumento(${id}, this)"><i class="fas fa-upload" aria-hidden="true"></i><span>${doc.estado ? 'Cargar de nuevo' : 'Cargar'}</span><span class="visually-hidden"> ${nombre}</span></button>`);
        if(ver){
            acciones.push(ver);
        }
    }
    if(gestion && puede('aprobarDocumentos') && doc.origen == 'archivo'){
        if(doc.estado == 'cargado'){
            acciones.push(`<button type="button" class="btn btn-sm btn-outline-success ui-btn" onclick="aprobarDocumento(${id}, this)"><i class="fas fa-check" aria-hidden="true"></i><span>Aprobar</span><span class="visually-hidden"> ${nombre}</span></button>`);
        }
        if(['cargado','aprobado'].includes(doc.estado)){
            acciones.push(`<button type="button" class="btn btn-sm btn-outline-danger ui-btn" onclick="abrirRechazo(this, ${id})"><i class="fas fa-xmark" aria-hidden="true"></i><span>Rechazar</span><span class="visually-hidden"> ${nombre}</span></button>`);
        }
    }
    let motivo = doc.estado == 'rechazado' && (doc.motivo || doc.observacion) ? `<p class="ui-doc-motivo">${doc.motivo ? `<strong>Motivo: ${escapeHtml(doc.motivo)}.</strong> ` : ''}${escapeHtml(doc.observacion || '')}</p>` : '';
    return `<li class="ui-doc" id="doc-${id}"><div><span class="ui-doc-nombre">${nombre}</span><span class="ui-doc-meta">${meta}</span></div><span class="ui-badge ui-badge-${clase}">${etiqueta}</span><div class="ui-doc-acciones">${acciones.join('')}</div>${motivo}<span class="invalid-feedback ui-doc-error" role="alert" id="error-doc-${id}"></span></li>`;
}

const panelDocumentos = function(){
    let docs = detalle.datos.documentos;
    let gestiona = puede('cargarDocumentos') || puede('aprobarDocumentos');
    return `${gestiona ? '' : avisoGestion(3)}
        <div id="progresoDocs">${htmlProgreso()}</div>
        ${docs.length ? `<ul class="ui-docs">${docs.map(d=>filaDoc(d)).join('')}</ul>` : '<p class="ui-campo-ayuda">La pagaduría no tiene documentos de soporte configurados.</p>'}
        <div class="mt-3" id="pieDocs">${htmlPieDocs()}</div>`;
}

const marcarErrorDoc = function(id, mensaje){
    let error = el('error-doc-'+id), input = el('archivo-'+id);
    error.textContent = mensaje;
    error.classList.toggle('d-block', !!mensaje);
    if(input){
        input.classList.toggle('is-invalid', !!mensaje);
    }
}

const actualizarFila = function(id){
    let doc = detalle.datos.documentos.find(d=>d.IdDocumento == id);
    el('doc-'+id).outerHTML = filaDoc(doc);
    el('progresoDocs').innerHTML = htmlProgreso();
    el('pieDocs').innerHTML = htmlPieDocs();
    anchoProgreso();
    return el('doc-'+id).querySelector('a, button, input');
}

const cargarDocumento = async function(id, boton){
    let input = el('archivo-'+id), archivo = input.files[0];
    let error = !archivo ? 'Selecciona el archivo PDF.' : (!/\.pdf$/i.test(archivo.name) || (archivo.type && archivo.type != 'application/pdf') ? 'El archivo debe ser PDF.' : (archivo.size > MAX_PDF_MB * 1048576 ? `El archivo supera ${MAX_PDF_MB} MB (${(archivo.size / 1048576).toLocaleString('es-CO',{maximumFractionDigits:1})} MB).` : ''));
    marcarErrorDoc(id, error);
    if(error){
        return input.focus();
    }
    let datos = new FormData();
    datos.append('idProceso', detalle.id);
    datos.append('idDocumento', id);
    datos.append('archivo', archivo);
    let foco = await cargando(boton, 'Cargando…', async ()=>{
        try{
            let res = await peticion('subir-documentos-soporte', datos);
            detalle.datos.documentos = detalle.datos.documentos.map(d=>d.IdDocumento == id ? res.documento : d);
            notificar('Documento cargado');
            return actualizarFila(id);
        }catch(e){
            marcarErrorDoc(id, e.status == 403 ? '' : mensajeErrorHttp(e.data));
        }
    });
    if(foco){
        foco.focus();
    }
}

const aprobarDocumento = async function(id, boton){
    let foco = await cargando(boton, 'Aprobando…', async ()=>{
        try{
            let res = await peticion('gestion-documentos-proceso', {idProceso:detalle.id, idDocumento:id, action:'aprobar'});
            detalle.datos.documentos = res.documentos;
            notificar('Documento aprobado');
            return actualizarFila(id);
        }catch(e){
            fallo(e);
        }
    });
    if(foco){
        foco.focus();
    }
}

const enviarAprobacion = async function(boton){
    await cargando(boton, 'Enviando…', async ()=>{
        try{
            await transicion({estadoNuevo:4});
        }catch(e){
            return fallo(e);
        }
        await recargarDetalle('Proceso enviado a aprobación');
    });
}

const condiciones = function(){
    let f = detalle.datos.financiero;
    return datosLista([['Valor',moneda(f.monto)],['Plazo',f.plazo != null ? `${f.plazo} meses` : ''],['Cuota total',moneda(f.cuotaTotal)],['Seguro',moneda(f.seguro)],['Cupo',moneda(f.cupo)],['Tasa',f.tasa != null ? `${formatearPorcentaje(f.tasa)} mensual` : '']]);
}

const listaAprobados = function(){
    let docs = detalle.datos.documentos.filter(d=>d.estado == 'aprobado');
    return docs.length ? `<ul class="ui-docs">${docs.map(d=>filaDoc(d,true)).join('')}</ul>` : '<p class="ui-campo-ayuda m-0">No hay documentos aprobados.</p>';
}

const panelAprobacion = function(){
    let aprobar = puede('aprobarCredito'), editar = puede('editarCredito');
    let botonAprobar = aprobar ? `${detalle.editando ? '<span class="ui-campo-ayuda m-0" id="ayudaAprobar">Guarda o cancela los cambios de condiciones antes de aprobar.</span>' : ''}<button type="button" class="btn btn-primary ui-btn" onclick="abrirAprobacion(this)"${detalle.editando ? ' disabled aria-describedby="ayudaAprobar"' : ''}><i class="fas fa-check" aria-hidden="true"></i><span>Aprobar crédito</span></button>` : '';
    return `${aprobar || editar ? '' : avisoGestion(4)}
        <section class="ui-seccion" aria-labelledby="tituloCondiciones">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h3 class="ui-seccion-titulo m-0" id="tituloCondiciones">Condiciones del crédito</h3>
                ${editar && !detalle.editando ? `<button type="button" class="btn btn-sm ui-btn ui-btn-sec" id="btnEditarCondiciones" onclick="editarCondiciones()"><i class="fas fa-pen" aria-hidden="true"></i><span>Editar condiciones</span></button>` : ''}
            </div>
            ${detalle.editando ? formCondiciones() : condiciones()}
        </section>
        <section class="ui-seccion" aria-labelledby="tituloAprobados">
            <h3 class="ui-seccion-titulo" id="tituloAprobados">Documentos aprobados</h3>
            ${listaAprobados()}
        </section>
        ${aprobar ? `<details class="proc-detalles" ontoggle="if(this.open) cargarCentrales()">
            <summary>Consulta de centrales de riesgo</summary>
            <div class="mt-3" id="informeCentrales"></div>
        </details>` : ''}
        ${pie(botonAprobar)}`;
}

const formCondiciones = function(){
    let f = detalle.datos.financiero;
    return `<form id="formCondiciones" novalidate onsubmit="guardarCondiciones(event)">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="valorCondicion" class="form-label">Valor del crédito</label>
                <div class="input-group has-validation">
                    <span class="input-group-text">$</span>
                    <input type="text" inputmode="numeric" autocomplete="off" id="valorCondicion" class="form-control" value="${f.monto != null ? formatearMoneda(f.monto,false) : ''}" aria-describedby="error-valorCondicion alertaMonto" oninput="formatearInputMoneda(this);cambioCondiciones(this.id)">
                    <span class="invalid-feedback" role="alert" id="error-valorCondicion"></span>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <label for="plazoCondicion" class="form-label">Plazo (número de cuotas)</label>
                <select id="plazoCondicion" class="form-select" aria-describedby="error-plazoCondicion ayuda-plazoCondicion" disabled onchange="cambioCondiciones(this.id)"><option value="">Cargando plazos…</option></select>
                <span class="invalid-feedback" role="alert" id="error-plazoCondicion"></span>
                <span class="ui-campo-ayuda" id="ayuda-plazoCondicion"></span>
            </div>
            <div class="col-12"><span class="ui-campo-ayuda m-0">Cupo disponible: ${moneda(f.cupo)}. El cupo lo calcula el sistema y no se edita.</span></div>
            <div class="col-12" id="alertaMonto" aria-live="polite"></div>
            <div class="col-12">
                <button type="button" class="btn ui-btn ui-btn-sec" id="btnRecalcular" onclick="recalcularCondiciones()"><i class="fas fa-calculator" aria-hidden="true"></i><span>Recalcular</span></button>
            </div>
            <div class="col-12" id="vistaPrevia" aria-live="polite"></div>
        </div>
        <div class="ui-acciones justify-content-end mt-3">
            <span class="ui-campo-ayuda m-0 me-auto" id="ayudaGuardar">Recalcula para revisar las nuevas condiciones antes de guardar.</span>
            <button type="button" class="btn ui-btn ui-btn-sec" onclick="cancelarCondiciones()">Cancelar</button>
            <button type="submit" class="btn btn-primary ui-btn" id="btnGuardarCondiciones" disabled aria-describedby="ayudaGuardar"><i class="fas fa-floppy-disk" aria-hidden="true"></i><span>Guardar condiciones</span></button>
        </div>
    </form>`;
}

const pintarVistaPrevia = function(){
    let c = detalle.calculo.datos;
    el('vistaPrevia').innerHTML = `<div class="ui-vista-previa">
        <h4 class="ui-seccion-titulo">Vista previa del recálculo</h4>
        <div class="ui-tarjetas">${tarjeta('Cuota',moneda(c.cuota))}${tarjeta('Seguro',moneda(c.seguro))}${tarjeta('Cuota total',moneda(c.cuotaTotal),true)}${tarjeta('Plazo',`${c.plazo} meses`)}</div>
        <p class="ui-campo-ayuda m-0" id="textoPrevia">Tasa ${formatearPorcentaje(c.tasa)} mensual · Cupo ${moneda(c.cupo)}.</p>
    </div>`;
    el('ayudaGuardar').textContent = 'Recálculo vigente. Ya puedes guardar las condiciones.';
    el('btnGuardarCondiciones').disabled = false;
}

const datosCondiciones = function(){
    let valor = numeroLimpio(el('valorCondicion').value), plazo = el('plazoCondicion').value;
    let errores = {valorCondicion: Number(valor) > 0 ? '' : 'Ingresa un valor mayor a cero.', plazoCondicion: plazo ? '' : 'Selecciona el plazo.'};
    Object.entries(errores).forEach(([campo,mensaje])=>marcarError(campo, mensaje));
    let primero = Object.keys(errores).find(campo=>errores[campo]);
    if(primero){
        el(primero).focus();
        return null;
    }
    return {idProceso:detalle.id, valorCredito:valor, periodoCredito:plazo};
}

const errorCondiciones = function(err){
    if(err.data && err.data.montoMaximo != null){
        detalle.calculo = null;
        return mostrarAlertaMonto(err.data);
    }
    if(err.status != 403){
        el('alertaMonto').innerHTML = alerta('error mb-0','fa-circle-exclamation',escapeHtml(mensajeErrorHttp(err.data)),'','alert');
    }
}

const recalcularCondiciones = async function(){
    let datos = datosCondiciones();
    if(!datos){
        return;
    }
    el('alertaMonto').innerHTML = '';
    await cargando(el('btnRecalcular'), 'Calculando…', async ()=>{
        try{
            detalle.calculo = {envio:datos, datos:await peticion('calcular-condiciones-proceso', datos)};
        }catch(err){
            return errorCondiciones(err);
        }
        pintarVistaPrevia();
    });
}

const editarCondiciones = function(){
    detalle.editando = true;
    detalle.calculo = null;
    repintarPanel();
    el('valorCondicion').focus();
}

const cancelarCondiciones = function(){
    detalle.editando = false;
    detalle.calculo = null;
    repintarPanel();
    el('btnEditarCondiciones').focus();
}

const cambioCondiciones = function(campo){
    marcarError(campo, '');
    el('valorCondicion').classList.remove('is-invalid');
    el('alertaMonto').innerHTML = '';
    el('btnGuardarCondiciones').disabled = true;
    el('ayudaGuardar').textContent = 'Recalcula para revisar las nuevas condiciones antes de guardar.';
    if(detalle.calculo){
        detalle.calculo = null;
        el('vistaPrevia').firstElementChild.classList.add('is-desactualizada');
        el('textoPrevia').textContent = 'Los datos cambiaron. Pulsa Recalcular para actualizar la vista previa.';
    }
}

const cargarPlazos = async function(){
    let t = detalle.datos.tercero, plazo = detalle.datos.financiero.plazo, res;
    try{
        res = await peticion('verificar-edad-tercero', {idTercero:t.IdTercero, idPagaduria:t.IdPagaduria || ''});
    }catch(e){
        res = {plazos:[], message:'No fue posible cargar los plazos'};
    }
    let select = el('plazoCondicion'), plazos = res.plazos || [];
    if(!select){
        return;
    }
    select.innerHTML = `<option value="">${escapeHtml(plazos.length ? 'Selecciona el plazo' : (res.message || 'Sin plazos configurados'))}</option>` + plazos.map(p=>`<option value="${Number(p)}"${p == plazo ? ' selected' : ''}>${Number(p)} meses</option>`).join('');
    select.disabled = !plazos.length;
    el('ayuda-plazoCondicion').textContent = res.plazoMaximo ? `Plazo máximo según la edad del cliente (${res.edad} años): ${res.plazoMaximo} meses.` : '';
}

const mostrarAlertaMonto = function(data){
    let monto = Number(data.montoMaximo) || 0;
    el('valorCondicion').classList.add('is-invalid');
    el('alertaMonto').innerHTML = alerta('adv mb-0','fa-triangle-exclamation',`La cuota (${formatearMoneda(data.cuota)}) supera el cupo disponible (${formatearMoneda(data.cupo)}). Monto máximo prestable: <strong>${formatearMoneda(monto)}</strong>`, monto > 0 ? `<button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="usarMontoMaximo(${monto})"><i class="fas fa-arrow-down" aria-hidden="true"></i><span>Usar monto máximo</span></button>` : '');
}

const usarMontoMaximo = function(monto){
    let input = el('valorCondicion');
    input.value = formatearMoneda(monto,false);
    cambioCondiciones('valorCondicion');
    el('alertaMonto').innerHTML = alerta('info mb-0','fa-circle-info','Se ajustó al monto máximo. Pulsa Recalcular para continuar.');
    input.focus();
}

const guardarCondiciones = async function(e){
    e.preventDefault();
    if(!detalle.calculo){
        return recalcularCondiciones();
    }
    let boton = el('btnGuardarCondiciones');
    let guardado = await cargando(boton, 'Guardando…', async ()=>{
        try{
            let res = await peticion('editar-proceso', detalle.calculo.envio);
            if(res != 'ok'){
                throw {data:{message:'No fue posible guardar las condiciones. Intenta de nuevo.'}};
            }
            return true;
        }catch(err){
            errorCondiciones(err);
        }
    });
    if(guardado){
        await recargarDetalle('Condiciones actualizadas');
    }else if(el('btnGuardarCondiciones')){
        boton.disabled = !detalle.calculo;
    }
}

const abrirAprobacion = function(boton){
    let t = detalle.datos.tercero;
    el('correoPara').value = `${detalle.datos.proceso.asesor || 'Asesor del proceso'} (asesor del proceso)`;
    el('correoAsunto').value = `Crédito aprobado - ${detalle.nombre} (${t.documento})`;
    el('correoMensaje').value = `Se aprobó la libranza${t.pagaduria ? ' '+t.pagaduria : ''} a nombre de ${detalle.nombre}, identificado(a) con ${t.tipoDocumento || 'documento'} ${t.documento}, con las condiciones indicadas en este correo.`.slice(0,2000);
    el('observacionAprobar').value = '';
    contar('correoMensaje');
    contar('observacionAprobar');
    marcarError('correoMensaje', '');
    el('resumenAprobar').innerHTML = condiciones();
    el('errorAprobar').innerHTML = '';
    abrirModal('modalAprobar', boton, 'correoMensaje');
}

const confirmarAprobacion = async function(e){
    e.preventDefault();
    let mensaje = el('correoMensaje').value.trim();
    marcarError('correoMensaje', mensaje ? '' : 'Escribe el mensaje del correo.');
    if(!mensaje){
        return el('correoMensaje').focus();
    }
    el('errorAprobar').innerHTML = '';
    await cargando(el('btnConfirmarAprobar'), 'Aprobando…', async ()=>{
        try{
            await transicion({estadoNuevo:5, texto:mensaje, observacion:el('observacionAprobar').value.trim()});
        }catch(err){
            return errorModal('errorAprobar', err);
        }
        detalle.mensaje = mensaje;
        cerrarModal('modalAprobar');
        await recargarDetalle('Crédito aprobado');
    });
}

const reenviarCorreo = async function(boton){
    await cargando(boton, 'Reenviando…', async ()=>{
        try{
            await peticion('enviar-email-credito', detalle.mensaje ? {idProceso:detalle.id, texto:detalle.mensaje} : {idProceso:detalle.id});
        }catch(e){
            return fallo(e);
        }
        detalle.aviso = false;
        repintarPanel();
        notificar('Correo enviado');
        el('tituloPanel').focus();
    });
}

const panelCerrado = function(){
    let estado = detalle.datos.proceso.estado, h = ultimoCambio(estado);
    let quien = h ? ` el ${fechaTexto(h.fecha)}${h.usuario ? ' por '+escapeHtml(h.usuario) : ''}` : '';
    let mensaje = estado == 0
        ? alerta('error','fa-circle-xmark',`Proceso rechazado${quien}.${h && h.motivo ? ` Motivo: <strong>${escapeHtml(h.motivo)}</strong>.` : ''}${h && h.observacion ? ' '+escapeHtml(h.observacion) : ''}`)
        : alerta('exito','fa-circle-check',`Crédito aprobado${quien}.`);
    let docs = detalle.datos.documentos.filter(d=>d.estado);
    let cuerpo = estado == 5 ? `<section class="ui-seccion"><h3 class="ui-seccion-titulo">Condiciones del crédito</h3>${condiciones()}</section>` : '';
    cuerpo += docs.length ? `<section class="ui-seccion"><h3 class="ui-seccion-titulo">Documentos</h3><ul class="ui-docs">${docs.map(d=>filaDoc(d,true)).join('')}</ul></section>` : '';
    return mensaje + cuerpo + (puede('reenviarCorreo') && !detalle.aviso && h && h.estadoAnterior == 4 ?`<div class="ui-card-pie">${botonReenviar()}</div>` : '');
}

const cargarMotivos = async function(){
    if(!detalle.motivos){
        detalle.motivos = (await peticion('motivos-rechazo')).motivos;
    }
    el('motivoRechazo').innerHTML = '<option value="">Selecciona un motivo</option>' + detalle.motivos.map(m=>`<option value="${Number(m.IdMotivo)}">${escapeHtml(m.NombreMotivo)}</option>`).join('');
}

const observacionObligatoria = function(){
    let select = el('motivoRechazo'), opcion = select.options[select.selectedIndex];
    return !!rechazo.doc || (!!select.value && /^otr[oa]s?\b/i.test(opcion.text.trim()));
}

const actualizarObligatoria = () => { el('obligatoriaRechazo').textContent = observacionObligatoria() ? '(obligatoria)' : '(opcional)'; };

const abrirRechazo = async function(boton, idDocumento=null){
    try{
        await cargarMotivos();
    }catch(e){
        return fallo(e);
    }
    let doc = idDocumento ? detalle.datos.documentos.find(d=>d.IdDocumento == idDocumento) : null;
    rechazo = {doc:doc};
    el('tituloRechazo').textContent = doc ? `Rechazar documento: ${doc.nombre}` : 'Rechazar proceso';
    el('contextoRechazo').textContent = `${detalle.nombre} · Etapa: ${ESTADOS[detalle.datos.proceso.estado]}`;
    el('textoBtnRechazar').textContent = doc ? 'Rechazar documento' : 'Rechazar proceso';
    el('alertaRechazo').classList.toggle('d-none', !!doc);
    el('ayudaRechazo').textContent = doc ? 'El asesor deberá cargarlo de nuevo.' : '';
    el('observacionRechazo').value = '';
    contar('observacionRechazo');
    ['motivoRechazo','observacionRechazo'].forEach(campo=>marcarError(campo, ''));
    el('errorRechazo').innerHTML = '';
    actualizarObligatoria();
    abrirModal('modalRechazo', boton, 'motivoRechazo');
}

const confirmarRechazo = async function(e){
    e.preventDefault();
    let motivo = el('motivoRechazo').value, observacion = el('observacionRechazo').value.trim();
    let errores = {motivoRechazo: motivo ? '' : 'Selecciona un motivo.', observacionRechazo: observacionObligatoria() && !observacion ? 'Describe el motivo del rechazo.' : ''};
    Object.entries(errores).forEach(([campo,mensaje])=>marcarError(campo, mensaje));
    let primero = Object.keys(errores).find(campo=>errores[campo]);
    if(primero){
        return el(primero).focus();
    }
    el('errorRechazo').innerHTML = '';
    await cargando(el('btnRechazar'), 'Rechazando…', async ()=>{
        if(rechazo.doc){
            let id = rechazo.doc.IdDocumento, res;
            try{
                res = await peticion('gestion-documentos-proceso', {idProceso:detalle.id, idDocumento:id, action:'rechazar', idMotivo:motivo, observacion:observacion});
            }catch(err){
                return errorModal('errorRechazo', err);
            }
            detalle.datos.documentos = res.documentos;
            el('modalRechazo').origen = actualizarFila(id);
            cerrarModal('modalRechazo');
            return notificar('Documento rechazado');
        }
        try{
            await transicion({estadoNuevo:0, idMotivo:motivo, observacion:observacion});
        }catch(err){
            return errorModal('errorRechazo', err);
        }
        cerrarModal('modalRechazo');
        await recargarDetalle('Proceso rechazado');
    });
}

document.querySelectorAll('.proc-modal').forEach(modal=>{
    modal.addEventListener('shown.bs.modal', ()=>{ if(el(modal.foco)){ el(modal.foco).focus(); } });
    modal.addEventListener('hidden.bs.modal', ()=>{
        let destino = modal.origen && modal.origen.isConnected ? modal.origen : el('tituloPanel');
        if(destino){
            destino.focus();
        }
    });
});
el('busquedaProceso').addEventListener('input', buscar);
window.addEventListener('popstate', enrutar);
enrutar();
