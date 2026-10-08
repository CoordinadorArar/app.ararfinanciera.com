let pagadurias = [];
let idSeleccionada = '';
let info = null;
let bloques = [];
let bloqueActivo = 0;
let reglas = [];
let reglaEdicion = null;
let salarioMinimo = 0;
let temporizadorPrueba = null;
let bloquePrueba = null;
const OPERADORES = {'+':['+','sumar'],'-':['−','restar'],'*':['×','multiplicar'],'/':['÷','dividir']};

window.addEventListener('load',function(){
    cargarPagadurias();
    peticionPagaduria('probar-formula',{configuracion:'salarioMinimoMensual',valores:'{}'}).then(res=>{
        salarioMinimo = Number(res.resultado) || 0;
        ayudaUmbral();
    }).catch(()=>{});
    let panel = document.getElementById('panelPagaduria');
    panel.addEventListener('click',clicPanel);
    panel.addEventListener('keydown',teclaLienzo);
    panel.addEventListener('focusin',function(e){
        let lienzo = e.target.closest('.ui-formula');
        if(lienzo && Number(lienzo.dataset.bloque) !== bloqueActivo){
            bloqueActivo = Number(lienzo.dataset.bloque);
            document.querySelectorAll('#panelPagaduria .ui-formula').forEach(el=>el.classList.toggle('is-activo',Number(el.dataset.bloque) === bloqueActivo));
        }
    });
});
const peticionPagaduria = function(ruta,datos){
    let dataToSend = new FormData();
    Object.keys(datos).forEach(clave=>dataToSend.append(clave,datos[clave]));
    return makeOptionsFetch(`${globalUrl}/${ruta}`,dataToSend,'post',$('meta[name="csrf-token-pagadurias"]').attr('content'),true);
}
const errorPeticion = function(error){
    if(error && error.data && error.status != 403){
        mostrarErrorHttp(error.data,error.status);
    }
}
const confirmar = function(titulo,texto,boton,peligro=false){
    return Swal.fire({title:titulo,text:texto,icon:peligro ? 'warning' : 'question',showCancelButton:true,confirmButtonText:boton,cancelButtonText:'Cancelar',confirmButtonColor:peligro ? '#A3391F' : 'rgb(65,110,195)',reverseButtons:true}).then(r=>r.isConfirmed);
}
const abrirModal = function(id){
    let origen = document.activeElement;
    let modal = document.getElementById(id);
    modal.addEventListener('hidden.bs.modal',()=>origen && origen.focus && origen.focus(),{once:true});
    bootstrap.Modal.getOrCreateInstance(modal).show();
}
const nombreLimpio = function(nombre){
    return String(nombre == null ? '' : nombre).trim();
}
const tokenRubro = function(nombre){
    let palabras = nombreLimpio(nombre).split(' ').filter(p=>p !== '');
    return palabras.length ? palabras[0]+palabras.slice(1).map(p=>p.charAt(0).toUpperCase()+p.slice(1)).join('') : '';
}
const fechaCorta = function(fecha){
    let partes = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(fecha || ''));
    return partes ? `${partes[3]}/${partes[2]}/${partes[1]}` : '';
}
const numeroCorto = function(valor,decimales=2){
    return (Number(valor) || 0).toLocaleString('es-CO',{maximumFractionDigits:decimales});
}
const vacio = function(icono,titulo,texto='',accion=''){
    return `<div class="ui-vacio"><i class="fas ${icono}" aria-hidden="true"></i><p class="ui-vacio-titulo">${titulo}</p>${texto ? `<p class="ui-vacio-texto">${texto}</p>` : ''}${accion}</div>`;
}
const badgeEstado = function(pagaduria){
    return Number(pagaduria.EstadoPagaduria) ? '<span class="ui-badge ui-badge-activo">Activa</span>' : '<span class="ui-badge ui-badge-inactivo">Inactiva</span>';
}

const cargarPagadurias = async function(){
    let res = await peticionPagaduria('listar-pagadurias-admin',{}).catch(errorPeticion);
    if(!res){
        return;
    }
    pagadurias = res.pagadurias || [];
    document.getElementById('totalPagadurias').textContent = pagadurias.length;
    document.getElementById('buscadorPagadurias').classList.toggle('d-none',pagadurias.length <= 8);
    let opciones = '<option value="">Selecciona una pagaduría</option>';
    pagadurias.forEach(p=>{
        opciones += `<option value="${escapeHtml(p.IdPagaduria)}"${p.IdPagaduria == idSeleccionada ? ' selected' : ''}>${escapeHtml(nombreLimpio(p.NombrePagaduria))}${Number(p.EstadoPagaduria) ? '' : ' (Inactiva)'}</option>`;
    });
    document.getElementById('selectPagaduria').innerHTML = opciones;
    pintarListaPagadurias();
    if(!idSeleccionada){
        pintarPanelVacio();
    }
}
const pintarListaPagadurias = function(){
    let filtro = document.getElementById('buscarPagaduria').value.trim().toLowerCase();
    let lista = pagadurias.filter(p=>nombreLimpio(p.NombrePagaduria).toLowerCase().includes(filtro));
    document.getElementById('listaPagadurias').innerHTML = lista.length ? lista.map(p=>{
        let activa = p.IdPagaduria == idSeleccionada;
        return `<button type="button" class="list-group-item list-group-item-action${activa ? ' is-actual' : ''}${Number(p.EstadoPagaduria) ? '' : ' is-inactiva'}"${activa ? ' aria-current="true"' : ''} onclick="seleccionarPagaduria(${Number(p.IdPagaduria)})">
                    <span class="text-truncate">${escapeHtml(nombreLimpio(p.NombrePagaduria))}</span>${badgeEstado(p)}
                </button>`;
    }).join('') : `<p class="ui-campo-ayuda px-1">${pagadurias.length ? 'Sin coincidencias' : 'Sin pagadurías registradas'}</p>`;
}
const pintarPanelVacio = function(){
    document.getElementById('panelPagaduria').innerHTML = pagadurias.length
        ? vacio('fa-building-columns','Selecciona una pagaduría para ver su configuración')
        : vacio('fa-building-columns','Aún no hay pagadurías','Crea la primera para configurar su fórmula de cupo.','<button type="button" class="btn btn-primary btn-sm ui-btn" onclick="abrirModalPagaduria(false)"><i class="fas fa-plus" aria-hidden="true"></i><span>Crear pagaduría</span></button>');
}
const seleccionarPagaduria = async function(id){
    idSeleccionada = id ? String(id) : '';
    document.getElementById('selectPagaduria').value = idSeleccionada;
    pintarListaPagadurias();
    if(!idSeleccionada){
        pintarPanelVacio();
        return;
    }
    reglaEdicion = null;
    await recargarInfo(['estructura']);
}
const recargarInfo = async function(partes){
    let res = await peticionPagaduria('mostrar-info-pagaduria',{idPagaduria:idSeleccionada}).catch(errorPeticion);
    if(!res || String(res.parametros.IdPagaduria) !== idSeleccionada){
        return;
    }
    info = res;
    if(partes.includes('estructura')){
        pintarEstructura();
        partes = ['cabecera','formula','parametros','reglas','rubros'];
    }
    if(partes.includes('cabecera')) pintarCabecera();
    if(partes.includes('formula')) construirBloques();
    if(partes.includes('paleta')) pintarPaleta();
    if(partes.includes('parametros')) pintarParametros();
    if(partes.includes('reglas')){
        reglas = (info.reglasEdad || []).map(r=>({id:Number(r.IdReglaEdad),min:Number(r.EdadMin),max:Number(r.EdadMax),plazo:Number(r.PlazoMaximo),seguro:Number((Number(r.PorcentajeSeguro)*100).toFixed(4))}));
        pintarReglas();
    }
    if(partes.includes('rubros')) pintarRubros();
}
const pintarEstructura = function(){
    let pestanas = [['formula','Fórmula de cupo'],['parametros','Parámetros'],['reglas','Reglas por edad'],['rubros','Rubros']];
    document.getElementById('panelPagaduria').innerHTML = `
        <div id="cabeceraPagaduria"></div>
        <ul class="nav nav-tabs ui-pestanas" role="tablist">
            ${pestanas.map((p,i)=>`<li class="nav-item" role="presentation"><button type="button" class="nav-link${i == 0 ? ' active' : ''}" id="pestana-${p[0]}" data-bs-toggle="tab" data-bs-target="#tab-${p[0]}" role="tab" aria-controls="tab-${p[0]}" aria-selected="${i == 0}">${p[1]}</button></li>`).join('')}
        </ul>
        <div class="tab-content pt-3">
            ${pestanas.map((p,i)=>`<div class="tab-pane fade${i == 0 ? ' show active' : ''}" id="tab-${p[0]}" role="tabpanel" aria-labelledby="pestana-${p[0]}" tabindex="0"></div>`).join('')}
        </div>`;
    document.getElementById('tab-formula').innerHTML = '<div id="bloquesFormula"></div><div id="paletaFormula"></div>';
}
const pintarCabecera = function(){
    let p = info.parametros;
    let activa = Number(p.EstadoPagaduria);
    let auditoria = info.ultimaAuditoria && fechaCorta(info.ultimaAuditoria.fecha) ? `<span class="ui-auditoria"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i>Última modificación${info.ultimaAuditoria.usuario ? ' por '+escapeHtml(info.ultimaAuditoria.usuario) : ''} el ${fechaCorta(info.ultimaAuditoria.fecha)}</span>` : '';
    document.getElementById('cabeceraPagaduria').innerHTML = `
        <div class="ui-panel-cab">
            <div>
                <h2 class="ui-panel-titulo"><span>${escapeHtml(nombreLimpio(p.NombrePagaduria))}</span>${badgeEstado(p)}</h2>
                ${auditoria}
            </div>
            <div class="ui-acciones">
                <button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="abrirModalPagaduria(true)"><i class="fas fa-pen" aria-hidden="true"></i><span>Editar nombre</span></button>
                <button type="button" class="btn btn-sm ui-btn ${activa ? 'btn-outline-danger' : 'btn-outline-success'}" onclick="cambiarEstadoPagaduria(${activa ? 0 : 1})"><i class="fas ${activa ? 'fa-ban' : 'fa-check'}" aria-hidden="true"></i><span>${activa ? 'Inactivar' : 'Activar'}</span></button>
            </div>
        </div>`;
}

const abrirModalPagaduria = function(editar){
    let input = document.getElementById('nombrePagaduria');
    document.getElementById('tituloModalPagaduria').textContent = editar ? 'Editar nombre' : 'Nueva pagaduría';
    document.getElementById('modalPagaduria').dataset.editar = editar ? '1' : '';
    input.value = editar && info ? nombreLimpio(info.parametros.NombrePagaduria) : '';
    input.classList.remove('is-invalid');
    document.getElementById('modalPagaduria').addEventListener('shown.bs.modal',()=>input.focus(),{once:true});
    abrirModal('modalPagaduria');
}
const guardarNombrePagaduria = async function(e){
    e.preventDefault();
    let input = document.getElementById('nombrePagaduria');
    let nombre = input.value.trim();
    let editar = document.getElementById('modalPagaduria').dataset.editar == '1';
    let errorNombre = function(mensaje){
        input.classList.add('is-invalid');
        document.getElementById('error-nombrePagaduria').textContent = mensaje;
        input.focus();
    };
    if(nombre === ''){
        return errorNombre('Escribe el nombre de la pagaduría.');
    }
    let datos = {nombrePagaduria:nombre};
    if(editar){
        datos.idPagaduria = idSeleccionada;
    }
    let boton = document.getElementById('btnGuardarPagaduria');
    boton.disabled = true;
    try{
        let res = await peticionPagaduria('guardar-pagaduria',datos);
        bootstrap.Modal.getInstance(document.getElementById('modalPagaduria')).hide();
        notificar(editar ? 'Nombre actualizado' : 'Pagaduría creada');
        idSeleccionada = String(res.idPagaduria || idSeleccionada);
        await cargarPagadurias();
        editar ? recargarInfo(['cabecera']) : seleccionarPagaduria(idSeleccionada);
    }catch(error){
        if(error.data && error.data.errors && error.data.errors.nombrePagaduria){
            errorNombre([].concat(error.data.errors.nombrePagaduria)[0]);
        }else{
            errorPeticion(error);
        }
    }finally{
        boton.disabled = false;
    }
}
const cambiarEstadoPagaduria = async function(estado){
    let ok = await confirmar(estado ? '¿Activar la pagaduría?' : '¿Inactivar la pagaduría?',estado ? 'La pagaduría volverá a aparecer en el registro y el simulador.' : 'La pagaduría dejará de aparecer en el registro y el simulador.',estado ? 'Activar' : 'Inactivar',!estado);
    if(!ok){
        return;
    }
    try{
        await peticionPagaduria('cambiar-estado-pagaduria',{idPagaduria:idSeleccionada,estado:estado});
        notificar(estado ? 'Pagaduría activada' : 'Pagaduría inactivada');
        await cargarPagadurias();
        recargarInfo(['cabecera']);
    }catch(error){
        errorPeticion(error);
    }
}

const parsearFormula = function(configuracion){
    let tokens = [];
    String(configuracion == null ? '' : configuracion).split('|').map(p=>p.trim()).filter(p=>p !== '').forEach(p=>{
        if(p === 'x' || p === 'X'){
            p = '*';
        }
        let previo = tokens[tokens.length-1];
        if(/^[\d.]+$/.test(p)){
            previo && previo.t === 'num' ? previo.v += p : tokens.push({t:'num',v:p});
        }else if(OPERADORES[p]){
            tokens.push({t:'op',v:p});
        }else if(p === '(' || p === ')'){
            tokens.push({t:'par',v:p});
        }else{
            tokens.push({t:'rubro',v:p});
        }
    });
    return tokens;
}
const serializarFormula = function(tokens){
    return tokens.map(t=>t.v).join('|');
}
const nombresFormula = function(){
    let nombres = ['ingresos','salarioMinimoMensual'];
    (info.rubros || []).forEach(r=>{
        let token = tokenRubro(r.NombreRubro);
        if(token && !nombres.some(n=>n.toLowerCase() === token.toLowerCase())){
            nombres.push(token);
        }
    });
    return nombres;
}
const validarFormula = function(tokens){
    let nombres = nombresFormula();
    let espera = true;
    let abiertos = 0;
    let error = (mensaje,indice=null)=>({mensaje:mensaje,indice:indice});
    for(let i = 0; i < tokens.length; i++){
        let t = tokens[i];
        let pos = i+1;
        if(t.t === 'num' || t.t === 'rubro'){
            if(t.t === 'num' && !/^\d+(\.\d+)?$/.test(t.v)) return error(`Número incompleto en la posición ${pos}`,i);
            if(t.t === 'rubro' && !nombres.some(n=>n.toLowerCase() === t.v.toLowerCase())) return error(`El rubro «${t.v}» no existe en la pagaduría (posición ${pos})`,i);
            if(!espera) return error(`Falta un operador antes de la posición ${pos}`,i);
            espera = false;
        }else if(t.v === '('){
            if(!espera) return error(`Falta un operador antes del paréntesis en la posición ${pos}`,i);
            abiertos++;
        }else if(t.v === ')'){
            if(abiertos === 0) return error(`Paréntesis de cierre sin apertura en la posición ${pos}`,i);
            if(espera) return error(`Paréntesis vacío o mal ubicado en la posición ${pos}`,i);
            abiertos--;
        }else{
            if(espera) return error(i > 0 && tokens[i-1].t === 'op' ? `Dos operadores seguidos en la posición ${pos}` : `Operador mal ubicado en la posición ${pos}`,i);
            espera = true;
        }
    }
    if(tokens.length === 0) return error('');
    if(espera) return error('La fórmula termina en un operador',tokens.length-1);
    if(abiertos > 0) return error(abiertos === 1 ? 'Falta cerrar 1 paréntesis' : `Falta cerrar ${abiertos} paréntesis`);
    return null;
}
const construirBloques = function(){
    let p = info.parametros;
    let formulas = (info.formulas || []).slice();
    let umbral = numeroCorto(p.UmbralSMMLV);
    let lista = [];
    if(Number(p.UsaReglaSMMLV)){
        let menor = formulas.find(f=>nombreLimpio(f.TipoDescuentoMaximo) === '$');
        let mayor = formulas.find(f=>nombreLimpio(f.TipoDescuentoMaximo) === '%');
        menor && lista.push([menor,`Ingresos ≤ ${umbral} SMMLV`]);
        mayor && lista.push([mayor,`Ingresos > ${umbral} SMMLV`]);
    }else{
        formulas.sort((a,b)=>a.IdConfigCalculo-b.IdConfigCalculo);
        let unica = formulas.find(f=>nombreLimpio(f.Configuracion) !== '') || formulas[0];
        unica && lista.push([unica,'Fórmula de cupo']);
    }
    bloques = lista.map(([f,titulo])=>{
        let tokens = parsearFormula(f.Configuracion);
        return {id:f.IdConfigCalculo,titulo:titulo,tokens:tokens,original:serializarFormula(tokens),cursor:tokens.length,sel:null,mensaje:null};
    });
    bloqueActivo = 0;
    document.getElementById('bloquesFormula').innerHTML = bloques.length ? bloques.map((b,i)=>`
        <div class="ui-bloque-formula">
            <div class="ui-bloque-cab">
                <h3 class="ui-subtitulo" id="titulo-formula-${i}">${escapeHtml(b.titulo)}</h3>
                <span id="estado-formula-${i}" class="d-inline-flex flex-wrap gap-1"></span>
            </div>
            <div class="ui-formula${i == 0 ? ' is-activo' : ''}" id="lienzo-${i}" data-bloque="${i}" role="group" tabindex="0" aria-label="${escapeHtml(b.titulo == 'Fórmula de cupo' ? b.titulo : 'Fórmula para '+b.titulo.charAt(0).toLowerCase()+b.titulo.slice(1))}" aria-describedby="mensaje-formula-${i}"></div>
            <div id="mensaje-formula-${i}" class="mt-2" aria-live="polite"></div>
            <div class="ui-bloque-pie">
                <button type="button" class="btn btn-sm ui-btn ui-btn-sec" id="probar-formula-${i}" onclick="abrirPruebaFormula(${i})"><i class="fas fa-flask" aria-hidden="true"></i><span>Probar fórmula</span></button>
                <button type="button" class="btn btn-sm btn-primary ui-btn" id="guardar-formula-${i}" onclick="guardarFormula(${i})"><i class="fas fa-floppy-disk" aria-hidden="true"></i><span>Guardar fórmula</span></button>
            </div>
        </div>`).join('') : vacio('fa-square-root-variable','La pagaduría no tiene fórmulas configuradas','Guarda los parámetros para generar las fórmulas.');
    bloques.forEach((b,i)=>pintarBloque(i));
    pintarPaleta();
}
const textoToken = function(t){
    return t.t === 'op' ? OPERADORES[t.v][0] : (t.t === 'num' ? t.v.replace('.',',') : t.v);
}
const etiquetaToken = function(t){
    if(t.t === 'op') return 'Operador '+OPERADORES[t.v][1];
    if(t.t === 'par') return t.v === '(' ? 'Paréntesis de apertura' : 'Paréntesis de cierre';
    return (t.t === 'num' ? 'Número ' : 'Rubro ')+textoToken(t);
}
const pintarBloque = function(i,enfocar=false){
    let b = bloques[i];
    let lienzo = document.getElementById('lienzo-'+i);
    if(!b || !lienzo){
        return;
    }
    let error = validarFormula(b.tokens);
    let cursor = '<span class="ui-formula-cursor" aria-hidden="true"></span>';
    let fichas = b.tokens.map((t,j)=>`${j === b.cursor && b.sel === null ? cursor : ''}<button type="button" class="ui-chip ui-chip-${t.t}${b.sel === j ? ' is-selected' : ''}${error && error.indice === j ? ' is-error' : ''}" data-idx="${j}" aria-label="${escapeHtml(etiquetaToken(t))}" aria-pressed="${b.sel === j}" tabindex="${b.sel === j ? 0 : -1}">${escapeHtml(textoToken(t))}</button>`).join('');
    lienzo.tabIndex = b.sel === null ? 0 : -1;
    lienzo.innerHTML = b.tokens.length ? fichas+(b.cursor === b.tokens.length && b.sel === null ? cursor : '') : cursor+'<span class="ui-formula-vacia">Agrega rubros, números y operadores desde la paleta o con el teclado</span>';
    let mensaje = b.mensaje || (error && error.mensaje ? {tipo:'error',texto:error.mensaje} : null);
    document.getElementById('mensaje-formula-'+i).innerHTML = mensaje ? `<div class="ui-alerta ui-alerta-${mensaje.tipo} mb-0"><i class="fas ${mensaje.tipo === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info'}" aria-hidden="true"></i><span class="ui-alerta-texto">${escapeHtml(mensaje.texto)}</span></div>` : '';
    let sucio = serializarFormula(b.tokens) !== b.original;
    document.getElementById('estado-formula-'+i).innerHTML = (!b.tokens.length ? '<span class="ui-badge ui-badge-inactivo">Vacía</span>' : error ? '<span class="ui-badge ui-badge-error">Inválida</span>' : '<span class="ui-badge ui-badge-activo"><i class="fas fa-check" aria-hidden="true"></i> Válida</span>')+(sucio ? '<span class="ui-badge ui-badge-adv">Cambios sin guardar</span>' : '');
    document.getElementById('guardar-formula-'+i).disabled = !!error || !sucio;
    document.getElementById('probar-formula-'+i).disabled = !!error;
    if(enfocar){
        let ficha = b.sel !== null ? lienzo.querySelector(`[data-idx="${b.sel}"]`) : null;
        (ficha || lienzo).focus();
    }
}
const pintarPaleta = function(){
    let contenedor = document.getElementById('paletaFormula');
    if(!contenedor || !bloques.length){
        contenedor && (contenedor.innerHTML = '');
        return;
    }
    let boton = (clase,tipo,valor,texto,etiqueta)=>`<button type="button" class="ui-chip ui-chip-${clase}" aria-label="${escapeHtml(etiqueta)}" data-tipo="${tipo}" data-valor="${valor}" onclick="insertarToken(this.dataset.tipo,this.dataset.valor)">${texto}</button>`;
    contenedor.innerHTML = `
        <div class="ui-paleta" role="group" aria-label="Paleta de la fórmula">
            <div class="mb-3">
                <span class="ui-cifra-etiqueta">Rubros</span>
                <div class="d-flex flex-wrap gap-2">${nombresFormula().map(n=>boton('rubro','rubro',escapeHtml(n),'<i class="fas fa-plus me-1" aria-hidden="true"></i>'+escapeHtml(n),'Agregar rubro '+n)).join('')}</div>
            </div>
            <div class="d-flex flex-wrap gap-4">
                <div>
                    <span class="ui-cifra-etiqueta">Números</span>
                    <div class="ui-teclado">${['7','8','9','4','5','6','1','2','3','0'].map(n=>boton('num','num',n,n,'Agregar número '+n)).join('')}${boton('num','num','.',',','Agregar coma decimal')}</div>
                </div>
                <div>
                    <span class="ui-cifra-etiqueta">Operadores</span>
                    <div class="ui-teclado">${Object.keys(OPERADORES).map(o=>boton('op','op',o,OPERADORES[o][0],'Agregar '+OPERADORES[o][1])).join('')}${boton('par','par','(','(','Agregar paréntesis de apertura')}${boton('par','par',')',')','Agregar paréntesis de cierre')}</div>
                </div>
            </div>
            <div class="ui-card-pie justify-content-start">
                <button type="button" class="btn btn-sm ui-btn ui-btn-sec" onclick="borrarFicha()"><i class="fas fa-delete-left" aria-hidden="true"></i><span>Borrar ficha</span></button>
                <button type="button" class="btn btn-sm ui-btn btn-outline-danger" onclick="vaciarFormula()"><i class="fas fa-eraser" aria-hidden="true"></i><span>Vaciar</span></button>
            </div>
        </div>`;
}
const insertarToken = function(tipo,valor,enfocar=false){
    let b = bloques[bloqueActivo];
    if(!b){
        return;
    }
    if(b.sel !== null){
        b.cursor = b.sel+1;
        b.sel = null;
    }
    let previo = b.tokens[b.cursor-1];
    if(tipo === 'num' && previo && previo.t === 'num'){
        if(valor === '.' && previo.v.includes('.')){
            return;
        }
        previo.v += valor;
    }else{
        b.tokens.splice(b.cursor,0,{t:tipo,v:tipo === 'num' && valor === '.' ? '0.' : valor});
        b.cursor++;
    }
    b.mensaje = null;
    pintarBloque(bloqueActivo,enfocar);
}
const borrarFicha = function(adelante=false,enfocar=false){
    let b = bloques[bloqueActivo];
    if(!b){
        return;
    }
    if(b.sel !== null){
        b.tokens.splice(b.sel,1);
        b.cursor = b.sel;
        b.sel = null;
    }else if(adelante && b.cursor < b.tokens.length){
        b.tokens.splice(b.cursor,1);
    }else if(!adelante && b.cursor > 0){
        b.tokens.splice(b.cursor-1,1);
        b.cursor--;
    }
    b.mensaje = null;
    pintarBloque(bloqueActivo,enfocar);
}
const vaciarFormula = function(){
    let b = bloques[bloqueActivo];
    if(b){
        b.tokens = []; b.cursor = 0; b.sel = null; b.mensaje = null;
        pintarBloque(bloqueActivo);
    }
}
const clicPanel = function(e){
    let lienzo = e.target.closest('.ui-formula');
    if(!lienzo){
        return;
    }
    bloqueActivo = Number(lienzo.dataset.bloque);
    let b = bloques[bloqueActivo];
    let ficha = e.target.closest('.ui-chip');
    if(ficha){
        let j = Number(ficha.dataset.idx);
        b.sel = b.sel === j ? null : j;
        b.cursor = j+1;
    }else{
        b.sel = null;
        b.cursor = b.tokens.length;
    }
    pintarBloque(bloqueActivo,true);
}
const teclaLienzo = function(e){
    let lienzo = e.target.closest('.ui-formula');
    if(!lienzo || e.ctrlKey || e.metaKey || e.altKey){
        return;
    }
    bloqueActivo = Number(lienzo.dataset.bloque);
    let b = bloques[bloqueActivo];
    let k = e.key;
    let enFicha = e.target.classList.contains('ui-chip');
    if((k === 'Enter' || k === ' ') && enFicha){
        return;
    }
    let mover = function(posicion){
        b.sel = null;
        b.cursor = Math.max(0,Math.min(b.tokens.length,posicion));
        pintarBloque(bloqueActivo,true);
    };
    if(k === 'ArrowLeft') mover((b.sel !== null ? b.sel+1 : b.cursor)-1);
    else if(k === 'ArrowRight') mover((b.sel !== null ? b.sel+1 : b.cursor)+1);
    else if(k === 'Home') mover(0);
    else if(k === 'End') mover(b.tokens.length);
    else if(k === 'Backspace') borrarFicha(false,true);
    else if(k === 'Delete') borrarFicha(true,true);
    else if(k === 'Escape'){ b.sel = null; pintarBloque(bloqueActivo,true); }
    else if(/^\d$/.test(k)) insertarToken('num',k,true);
    else if(k === ',' || k === '.') insertarToken('num','.',true);
    else if(OPERADORES[k] || k === 'x' || k === 'X') insertarToken('op',OPERADORES[k] ? k : '*',true);
    else if(k === '(' || k === ')') insertarToken('par',k,true);
    else return;
    e.preventDefault();
}
const guardarFormula = async function(i){
    let b = bloques[i];
    let configuracion = serializarFormula(b.tokens);
    let boton = document.getElementById('guardar-formula-'+i);
    boton.disabled = true;
    try{
        await peticionPagaduria('guardar-pagaduria-info',{action:'config',idPagaduria:idSeleccionada,idConfiguracion:b.id,configuracion:configuracion});
        b.original = configuracion;
        b.mensaje = null;
        notificar('Fórmula guardada');
        recargarInfo(['cabecera','rubros']);
    }catch(error){
        b.mensaje = {tipo:'error',texto:error.message};
        errorPeticion(error.status == 422 ? null : error);
    }
    pintarBloque(i);
}

const abrirPruebaFormula = function(i){
    let b = bloques[i];
    bloquePrueba = i;
    let rubros = [];
    b.tokens.forEach(t=>{
        if(t.t === 'rubro' && !rubros.includes(t.v)){
            rubros.push(t.v);
        }
    });
    let defecto = {ingresos:2600000,salarioMinimoMensual:salarioMinimo};
    document.getElementById('probarSubtitulo').textContent = b.titulo+'. Ajusta los valores de ejemplo; el resultado se recalcula al escribir.';
    document.getElementById('probarValores').innerHTML = rubros.length ? rubros.map((r,j)=>`
        <div class="col-12 col-md-6">
            <label for="probar-${j}" class="form-label">${escapeHtml(r)}</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="text" inputmode="numeric" class="form-control" id="probar-${j}" data-rubro="${escapeHtml(r)}" value="${formatearMoneda(defecto[r] || 0,false)}" oninput="formatearInputMoneda(this);programarPrueba()">
            </div>
        </div>`).join('') : '<p class="ui-campo-ayuda col-12">La fórmula no usa rubros; solo contiene valores fijos.</p>';
    document.getElementById('modalProbarFormula').addEventListener('shown.bs.modal',()=>{
        let primero = document.querySelector('#probarValores input');
        primero && primero.focus();
    },{once:true});
    abrirModal('modalProbarFormula');
    ejecutarPrueba();
}
const programarPrueba = function(){
    clearTimeout(temporizadorPrueba);
    temporizadorPrueba = setTimeout(ejecutarPrueba,350);
}
const ejecutarPrueba = async function(){
    let b = bloques[bloquePrueba];
    let valores = {};
    document.querySelectorAll('#probarValores input').forEach(input=>{
        valores[input.dataset.rubro] = numeroLimpio(input.value) || '0';
    });
    let resultado = document.getElementById('probarResultado');
    let operacion = document.getElementById('probarOperacion');
    try{
        let res = await peticionPagaduria('probar-formula',{configuracion:serializarFormula(b.tokens),valores:JSON.stringify(valores)});
        operacion.textContent = String(res.operacion).replace(/\d+(\.\d+)?/g,m=>numeroCorto(m,4)).replace(/([+\-*/])/g,s=>' '+OPERADORES[s][0]+' ');
        let valor = Number(res.resultado) || 0;
        resultado.textContent = valor > 0 ? formatearMoneda(valor) : 'Sin cupo ('+formatearMoneda(valor)+')';
        resultado.classList.toggle('is-error',valor <= 0);
    }catch(error){
        operacion.textContent = error.message;
        resultado.textContent = '—';
        resultado.classList.add('is-error');
    }
}

const pintarParametros = function(){
    let p = info.parametros;
    let usa = !!Number(p.UsaReglaSMMLV);
    document.getElementById('tab-parametros').innerHTML = `
        <form novalidate onsubmit="guardarParametros(event)">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="usaReglaSMMLV" aria-describedby="ayuda-regla" ${usa ? 'checked' : ''} onchange="cambiarReglaSMMLV(this)">
                <label class="form-check-label fw-semibold" for="usaReglaSMMLV">Usar regla de SMMLV</label>
            </div>
            <span class="ui-campo-ayuda mb-3" id="ayuda-regla">Aplica fórmulas distintas según los ingresos del cliente</span>
            <div class="row">
                <div class="col-12 col-md-6">
                    <label for="umbralSMMLV" class="form-label">Umbral (número de SMMLV)</label>
                    <div class="input-group has-validation">
                        <input type="number" class="form-control" id="umbralSMMLV" min="1" max="999.99" step="0.5" value="${escapeHtml(Number(p.UmbralSMMLV) || 2)}" aria-describedby="ayuda-umbral error-umbralSMMLV" ${usa ? '' : 'disabled'} oninput="this.classList.remove('is-invalid');ayudaUmbral()">
                        <span class="input-group-text">SMMLV</span>
                        <span class="invalid-feedback" role="alert" id="error-umbralSMMLV"></span>
                    </div>
                    <span class="ui-campo-ayuda" id="ayuda-umbral"></span>
                </div>
            </div>
            <div class="ui-card-pie">
                <button type="submit" class="btn btn-primary ui-btn" id="btnGuardarParametros"><i class="fas fa-floppy-disk" aria-hidden="true"></i><span>Guardar parámetros</span></button>
            </div>
        </form>`;
    ayudaUmbral();
}
const ayudaUmbral = function(){
    let input = document.getElementById('umbralSMMLV');
    let ayuda = document.getElementById('ayuda-umbral');
    if(input && ayuda){
        ayuda.textContent = salarioMinimo && Number(input.value) > 0 ? 'Equivale a '+formatearMoneda(Number(input.value)*salarioMinimo)+' con el SMMLV vigente' : '';
    }
}
const cambiarReglaSMMLV = async function(input){
    let configuradas = (info.formulas || []).filter(f=>nombreLimpio(f.Configuracion) !== '').length;
    if(!input.checked && Number(info.parametros.UsaReglaSMMLV) && configuradas > 1){
        let ok = await confirmar('¿Desactivar la regla de SMMLV?','Se usará una sola fórmula para todos los ingresos; la otra se conservará sin uso.','Desactivar');
        if(!ok){
            input.checked = true;
        }
    }
    document.getElementById('umbralSMMLV').disabled = !input.checked;
}
const guardarParametros = async function(e){
    e.preventDefault();
    let usa = document.getElementById('usaReglaSMMLV').checked;
    let umbral = document.getElementById('umbralSMMLV');
    let valor = Number(umbral.value);
    if(usa && !(valor >= 1 && valor <= 999.99)){
        umbral.classList.add('is-invalid');
        document.getElementById('error-umbralSMMLV').textContent = 'Ingresa un umbral entre 1 y 999,99 SMMLV.';
        umbral.focus();
        return;
    }
    let datos = {idPagaduria:idSeleccionada,nombrePagaduria:nombreLimpio(info.parametros.NombrePagaduria),usaReglaSMMLV:usa ? 1 : 0};
    if(usa){
        datos.umbralSMMLV = valor;
    }
    let boton = document.getElementById('btnGuardarParametros');
    boton.disabled = true;
    try{
        await peticionPagaduria('guardar-pagaduria',datos);
        notificar('Parámetros guardados');
        await recargarInfo(['cabecera','formula','parametros']);
    }catch(error){
        if(error.data && error.data.errors && error.data.errors.umbralSMMLV){
            umbral.classList.add('is-invalid');
            document.getElementById('error-umbralSMMLV').textContent = [].concat(error.data.errors.umbralSMMLV)[0];
        }else{
            errorPeticion(error);
        }
    }finally{
        boton.disabled = false;
    }
}

const reglasEfectivas = function(){
    let lista = reglas.filter(r=>reglaEdicion === null || r.id !== reglaEdicion.id).map(r=>Object.assign({},r));
    if(reglaEdicion !== null){
        lista.push(Object.assign({},reglaEdicion,{min:Number(reglaEdicion.min),max:Number(reglaEdicion.max)}));
    }
    return lista;
}
const erroresBorrador = function(){
    let r = reglaEdicion;
    let errores = {};
    let entero = (v,a,b)=>/^\d+$/.test(String(v)) && Number(v) >= a && Number(v) <= b;
    if(!entero(r.min,18,120)) errores.min = 'La edad mínima debe ser un entero entre 18 y 120.';
    if(!entero(r.max,18,120)) errores.max = 'La edad máxima debe ser un entero entre 18 y 120.';
    else if(!errores.min && Number(r.min) > Number(r.max)) errores.max = 'La edad máxima debe ser mayor o igual a la mínima.';
    if(!entero(r.plazo,1,360)) errores.plazo = 'El plazo debe ser un entero entre 1 y 360 meses.';
    if(!/^\d+(\.\d{1,4})?$/.test(String(r.seguro)) || Number(r.seguro) > 5) errores.seguro = 'El seguro debe estar entre 0 y 5 %, con hasta 4 decimales.';
    return errores;
}
const analizarReglas = function(){
    let lista = reglasEfectivas().filter(r=>!isNaN(r.min) && !isNaN(r.max) && r.min <= r.max).sort((a,b)=>a.min-b.min);
    let solapes = [];
    let filas = new Set();
    for(let i = 0; i < lista.length; i++){
        for(let j = i+1; j < lista.length; j++){
            if(lista[i].min <= lista[j].max && lista[j].min <= lista[i].max){
                solapes.push(`El rango ${lista[i].min}–${lista[i].max} se solapa con ${lista[j].min}–${lista[j].max}`);
                filas.add(lista[i].id); filas.add(lista[j].id);
            }
        }
    }
    let huecos = [];
    let siguiente = 18;
    lista.forEach(r=>{
        if(r.min > siguiente){
            huecos.push(r.min-1 > siguiente ? `${siguiente}–${r.min-1}` : `${siguiente}`);
        }
        siguiente = Math.max(siguiente,r.max+1);
    });
    return {solapes:solapes,filas:filas,huecos:lista.length ? huecos : []};
}
const pintarAvisosReglas = function(){
    let analisis = analizarReglas();
    let errores = reglaEdicion ? erroresBorrador() : {};
    let mensajes = Object.keys(errores).filter(campo=>String(reglaEdicion[campo]) !== '').map(campo=>errores[campo]).concat(analisis.solapes);
    let html = mensajes.length ? `<div class="ui-alerta ui-alerta-error"><i class="fas fa-circle-exclamation" aria-hidden="true"></i><span class="ui-alerta-texto">${mensajes.map(escapeHtml).join('<br>')}</span></div>` : '';
    if(analisis.huecos.length){
        html += `<div class="ui-alerta ui-alerta-adv"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><span class="ui-alerta-texto">No hay regla para edades ${analisis.huecos.join(', ')}; esos clientes no podrán simular.</span></div>`;
    }
    document.getElementById('avisosReglas').innerHTML = html;
    document.querySelectorAll('#tablaReglas tbody tr').forEach(tr=>{
        tr.classList.toggle('ui-fila-error',analisis.filas.has(tr.dataset.id === 'nueva' ? 'nueva' : Number(tr.dataset.id)));
    });
    ['min','max','plazo','seguro'].forEach(campo=>{
        let input = document.getElementById('regla-'+campo);
        input && input.classList.toggle('is-invalid',!!errores[campo]);
    });
    let guardar = document.getElementById('btnGuardarRegla');
    if(guardar){
        guardar.disabled = Object.keys(errores).length > 0 || analisis.filas.has(reglaEdicion.id);
    }
}
const pintarReglas = function(){
    reglas.sort((a,b)=>a.min-b.min);
    let filas = reglas.map(r=>reglaEdicion !== null && reglaEdicion.id === r.id ? filaEdicion() : `
        <tr data-id="${Number(r.id)}">
            <td class="num">${r.min}</td>
            <td class="num">${r.max}</td>
            <td class="num">${r.plazo}</td>
            <td class="num">${formatearPorcentaje(r.seguro,4)}</td>
            <td><div class="ui-tabla-acciones">
                <button type="button" class="btn btn-sm ui-btn ui-btn-sec" aria-label="Editar regla de ${r.min} a ${r.max} años" title="Editar" ${reglaEdicion !== null ? 'disabled' : ''} onclick="editarRegla(${Number(r.id)})"><i class="fas fa-pen" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-sm ui-btn btn-outline-danger" aria-label="Eliminar regla de ${r.min} a ${r.max} años" title="Eliminar" ${reglaEdicion !== null ? 'disabled' : ''} onclick="eliminarRegla(${Number(r.id)})"><i class="fas fa-trash" aria-hidden="true"></i></button>
            </div></td>
        </tr>`).join('');
    if(reglaEdicion !== null && reglaEdicion.id === 'nueva'){
        filas += filaEdicion();
    }
    document.getElementById('tab-reglas').innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <p class="ui-descripcion mb-0">Plazo máximo y seguro mensual según la edad del cliente.</p>
            <button type="button" class="btn btn-sm ui-btn ui-btn-sec" ${reglaEdicion !== null ? 'disabled' : ''} onclick="editarRegla('nueva')"><i class="fas fa-plus" aria-hidden="true"></i><span>Agregar regla</span></button>
        </div>
        <div id="avisosReglas"></div>
        ${filas ? `<div class="ui-scroll"><table class="ui-tabla" id="tablaReglas">
            <thead><tr><th scope="col" class="num">Edad mín</th><th scope="col" class="num">Edad máx</th><th scope="col" class="num">Plazo máx (meses)</th><th scope="col" class="num">Seguro mensual (% del monto)</th><th scope="col" class="text-end">Acciones</th></tr></thead>
            <tbody>${filas}</tbody>
        </table></div>` : vacio('fa-user-clock','Sin reglas por edad','El simulador y el registro no ofrecerán plazos.')}`;
    pintarAvisosReglas();
}
const filaEdicion = function(){
    let r = reglaEdicion;
    let campo = (nombre,etiqueta,paso,min,max)=>`<td><input type="number" class="form-control form-control-sm text-end" id="regla-${nombre}" aria-label="${etiqueta}" step="${paso}" min="${min}" max="${max}" value="${escapeHtml(r[nombre])}" oninput="reglaEdicion['${nombre}'] = this.value;pintarAvisosReglas()"></td>`;
    return `<tr class="ui-fila-edicion" data-id="${r.id === 'nueva' ? 'nueva' : Number(r.id)}">
            ${campo('min','Edad mínima',1,18,120)}${campo('max','Edad máxima',1,18,120)}${campo('plazo','Plazo máximo en meses',1,1,360)}${campo('seguro','Seguro mensual en porcentaje',0.0001,0,5)}
            <td><div class="ui-tabla-acciones">
                <button type="button" class="btn btn-sm btn-primary ui-btn" id="btnGuardarRegla" onclick="guardarRegla()"><i class="fas fa-check" aria-hidden="true"></i><span>Guardar</span></button>
                <button type="button" class="btn btn-sm btn-outline-secondary ui-btn" onclick="cancelarRegla()"><span>Cancelar</span></button>
            </div></td>
        </tr>`;
}
const editarRegla = function(id){
    let actual = reglas.find(r=>r.id === id);
    reglaEdicion = actual ? Object.assign({},actual) : {id:'nueva',min:reglas.length ? Math.min(120,Math.max(...reglas.map(r=>r.max))+1) : 18,max:'',plazo:'',seguro:''};
    pintarReglas();
    let input = document.getElementById(actual ? 'regla-min' : 'regla-max');
    input && input.focus();
}
const cancelarRegla = function(){
    reglaEdicion = null;
    pintarReglas();
}
const guardarRegla = async function(){
    let r = reglaEdicion;
    let datos = {idPagaduria:idSeleccionada,edadMin:r.min,edadMax:r.max,plazoMaximo:r.plazo,porcentajeSeguro:Number((Number(r.seguro)/100).toFixed(8))};
    if(r.id !== 'nueva'){
        datos.idReglaEdad = r.id;
    }
    document.getElementById('btnGuardarRegla').disabled = true;
    try{
        await peticionPagaduria('guardar-regla-edad',datos);
        reglaEdicion = null;
        notificar('Regla guardada');
        await recargarInfo(['cabecera','reglas']);
    }catch(error){
        document.getElementById('avisosReglas').insertAdjacentHTML('afterbegin',`<div class="ui-alerta ui-alerta-error"><i class="fas fa-circle-exclamation" aria-hidden="true"></i><span class="ui-alerta-texto">${escapeHtml(error.message)}</span></div>`);
        document.getElementById('btnGuardarRegla').disabled = false;
    }
}
const eliminarRegla = async function(id){
    let r = reglas.find(regla=>regla.id === id);
    if(!r || !(await confirmar('¿Eliminar la regla?',`Se eliminará la regla de ${r.min} a ${r.max} años.`,'Eliminar',true))){
        return;
    }
    try{
        await peticionPagaduria('eliminar-regla-edad',{idReglaEdad:id,idPagaduria:idSeleccionada});
        notificar('Regla eliminada');
        await recargarInfo(['cabecera','reglas']);
    }catch(error){
        errorPeticion(error);
    }
}

const usoRubro = function(rubro){
    let token = tokenRubro(rubro.NombreRubro).toLowerCase();
    let tipos = (info.formulas || []).filter(f=>String(f.Configuracion || '').split('|').some(p=>p.trim().toLowerCase() === token)).map(f=>nombreLimpio(f.TipoDescuentoMaximo));
    if(!tipos.length){
        return rubro.enUso ? 'En fórmula' : '';
    }
    if(!Number(info.parametros.UsaReglaSMMLV)){
        return 'En fórmula';
    }
    return tipos.includes('$') && tipos.includes('%') ? 'En ambas' : (tipos.includes('$') ? 'En fórmula ≤ umbral' : 'En fórmula > umbral');
}
const pintarRubros = function(){
    let rubros = info.rubros || [];
    document.getElementById('tab-rubros').innerHTML = `
        <form class="row g-2 align-items-end mb-3" novalidate onsubmit="guardarRubro(event)">
            <div class="col-12 col-md">
                <label for="nombreRubro" class="form-label">Nuevo rubro</label>
                <input type="text" id="nombreRubro" class="form-control" maxlength="50" autocomplete="off" aria-describedby="error-nombreRubro ayuda-rubro" onkeypress="return noStrangeCharacters(event)" oninput="this.classList.remove('is-invalid');previsualizarRubro()">
                <span class="invalid-feedback" role="alert" id="error-nombreRubro"></span>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-primary ui-btn w-100" id="btnGuardarRubro"><i class="fas fa-plus" aria-hidden="true"></i><span>Agregar rubro</span></button>
            </div>
            <div class="col-12"><span class="ui-campo-ayuda mt-0" id="ayuda-rubro">El nombre se convertirá en el identificador que usa la fórmula.</span></div>
        </form>
        <span class="visually-hidden" id="rubro-en-uso">Usado en la fórmula; quítalo primero</span>
        ${rubros.length ? `<div class="ui-scroll"><table class="ui-tabla">
            <thead><tr><th scope="col">Rubro</th><th scope="col">Uso</th><th scope="col" class="text-end">Acciones</th></tr></thead>
            <tbody>${rubros.map(r=>`<tr>
                <td>${escapeHtml(nombreLimpio(r.NombreRubro))}${tokenRubro(r.NombreRubro) !== nombreLimpio(r.NombreRubro) ? `<span class="ui-campo-ayuda mt-0">${escapeHtml(tokenRubro(r.NombreRubro))}</span>` : ''}</td>
                <td>${usoRubro(r) ? `<span class="ui-badge ui-badge-info">${usoRubro(r)}</span>` : '<span class="ui-badge ui-badge-inactivo">Sin uso</span>'}</td>
                <td><div class="ui-tabla-acciones"><span${usoRubro(r) ? ' title="Usado en la fórmula; quítalo primero"' : ''}>
                    <button type="button" class="btn btn-sm ui-btn btn-outline-danger" ${usoRubro(r) ? 'disabled aria-describedby="rubro-en-uso"' : ''} onclick="eliminarRubro(${Number(r.IdRubro)})"><i class="fas fa-trash" aria-hidden="true"></i><span>Eliminar</span></button>
                </span></div></td>
            </tr>`).join('')}</tbody>
        </table></div>` : vacio('fa-tags','Sin rubros','Agrega los conceptos que usará la fórmula, por ejemplo «descuento ley».')}`;
}
const previsualizarRubro = function(){
    let token = tokenRubro(document.getElementById('nombreRubro').value);
    document.getElementById('ayuda-rubro').textContent = token ? 'Se guardará como: '+token : 'El nombre se convertirá en el identificador que usa la fórmula.';
}
const guardarRubro = async function(e){
    e.preventDefault();
    let input = document.getElementById('nombreRubro');
    let errorRubro = function(mensaje){
        input.classList.add('is-invalid');
        document.getElementById('error-nombreRubro').textContent = mensaje;
        input.focus();
    };
    if(input.value.trim() === ''){
        return errorRubro('Escribe el nombre del rubro.');
    }
    let boton = document.getElementById('btnGuardarRubro');
    boton.disabled = true;
    try{
        await peticionPagaduria('guardar-rubro-configuracion',{nombreRubro:input.value.trim(),IdPagaduria:idSeleccionada});
        notificar('Rubro agregado');
        await recargarInfo(['cabecera','rubros','paleta']);
        bloques.forEach((b,i)=>pintarBloque(i));
        document.getElementById('nombreRubro').focus();
    }catch(error){
        error.data && error.data.errors && error.data.errors.nombreRubro ? errorRubro([].concat(error.data.errors.nombreRubro)[0]) : errorPeticion(error);
        boton.disabled = false;
    }
}
const eliminarRubro = async function(id){
    let rubro = (info.rubros || []).find(r=>Number(r.IdRubro) === id);
    if(!rubro || !(await confirmar('¿Eliminar el rubro?',`Se eliminará «${nombreLimpio(rubro.NombreRubro)}» de la pagaduría.`,'Eliminar',true))){
        return;
    }
    try{
        await peticionPagaduria('eliminar-rubro-configuracion',{idRubro:id,idPagaduria:idSeleccionada});
        notificar('Rubro eliminado');
        await recargarInfo(['cabecera','rubros','paleta']);
        bloques.forEach((b,i)=>pintarBloque(i));
    }catch(error){
        errorPeticion(error);
    }
}
