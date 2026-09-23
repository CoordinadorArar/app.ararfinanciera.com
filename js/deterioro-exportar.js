/** Exportables del corte (§16): el mismo menú en las siete pantallas de corte. */

const expToken = () => $('meta[name="csrf-token-deterioro"]').attr('content');

const expTexto = t => String(t === null || t === undefined ? '' : t)
    .replace(/[<>&"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));

/** Pasado este punto el archivo ya no llega: el aviso tiene que ser accionable. */
const expEsperaMaxima = 120000;

let expConfig = {};
let expDescargando = false;

/** Orden fijo en las siete pantallas: del resumen al archivo de transición. */
const expSalidas = [
    { clave: 'resumen', ruta: '/deterioro-exportar-resumen', extension: 'pdf',
        rotulo: 'Resumen del corte &middot; PDF',
        descripcion: 'Matriz por producto y rango, movimiento del mes y controles de cuadre' },
    { clave: 'detalle', ruta: '/deterioro-exportar-detalle', extension: 'xlsx',
        rotulo: 'Detalle por operación &middot; Excel',
        descripcion: 'Todas las operaciones del corte' },
    { clave: 'asiento', ruta: '/deterioro-exportar-asiento', extension: 'csv',
        rotulo: 'Asiento contable &middot; archivo plano',
        descripcion: 'Ajuste del período, auxiliar por producto y anexos fiscales' },
    { clave: 'transicion', ruta: '/deterioro-exportar-transicion', extension: 'xlsx',
        rotulo: 'Excel de transición',
        descripcion: 'Réplica de las hojas del libro actual &middot; archivo grande' }
];

const expCorte = () => expConfig.corte || {};
const expPreliminar = () => expCorte().estado === 'CALCULADO';
const expConSalvedad = () => expCorte().estado === 'CERRADO' && !!expCorte().cerrado_con_salvedad;
const expAsiento = () => (expConfig.exportables || {}).asiento || {};
const expAsientoDisponible = () => expAsiento().disponible === true;

/**
 * El ámbar anuncia una espera que termina —Contabilidad define el PUC y el
 * asiento sale—; lo que ninguna gestión resuelve va en gris, con el criterio de
 * los controles que no aplican al corte.
 */
const expBadgeAsiento = {
    'SIN_CUENTAS': '<span class="det-badge det-ambar">Sin cuentas</span>',
    'SIN_CORTE_ANTERIOR': '<span class="det-badge det-inactivo">Sin corte anterior</span>',
    'SIN_CALCULAR': '<span class="det-badge det-inactivo">Sin calcular</span>'
};

const expDescripcionAsiento = {
    'SIN_CUENTAS': 'Contabilidad no ha definido el PUC',
    'SIN_CORTE_ANTERIOR': 'El corte es el primero de la serie: no hay ajuste del período',
    'SIN_CALCULAR': 'Hay que ejecutar el cálculo antes de exportar el asiento'
};

const expTituloAsiento = {
    'SIN_CUENTAS': 'Falta definir las cuentas contables',
    'SIN_CORTE_ANTERIOR': 'El primer corte de la serie no tiene asiento',
    'SIN_CALCULAR': 'El corte todavía no se ha calculado'
};

/** Qué trae cada salida. En el detalle depende de la pantalla desde la que se pide. */
const expDescripcion = function (salida) {
    if (salida.clave === 'detalle' && expConfig.filtros) {
        return 'Con los filtros aplicados &middot; ' + detEntero(expConfig.conteo)
            + (Number(expConfig.conteo) === 1 ? ' operación' : ' operaciones');
    }
    if (salida.clave === 'asiento' && !expAsientoDisponible()) {
        return expDescripcionAsiento[expAsiento().motivo] || 'El asiento no está disponible para este corte';
    }
    return salida.descripcion;
};

/**
 * La calidad del corte se declara en los cuatro subtítulos, sin sustituir la
 * descripción: las cuatro salidas de un corte calculado son igual de
 * provisionales, y la salvedad solo cambia lo que el PDF trae dentro.
 */
const expSubtitulo = function (salida) {
    let marca = '';
    if (expPreliminar()) {
        marca = ' &middot; Corte calculado, no cerrado: la cifra puede cambiar';
    } else if (expConSalvedad() && salida.clave === 'resumen') {
        marca = ' &middot; Cerrado con salvedades: el PDF incluye el motivo y los requisitos sin resolver';
    }

    return expDescripcion(salida) + marca;
};

/** El asiento indisponible se ofrece igual: el motivo se anuncia, no se esconde. */
const expItem = function (salida) {
    // Sin distintivo el ítem se leería como descargable: un motivo que el módulo
    // todavía no conozca tiene que caer igual en el badge neutro.
    const badge = salida.clave === 'asiento' && !expAsientoDisponible()
        ? ' ' + (expBadgeAsiento[expAsiento().motivo]
            || '<span class="det-badge det-inactivo">No disponible</span>') : '';

    return '<li><button type="button" class="dropdown-item" onclick="detDescargar(\'' + salida.clave + '\')">'
        + salida.rotulo + badge
        + '<span class="det-subtitulo">' + expSubtitulo(salida) + '</span></button></li>';
};

/** Cada pantalla pasa su propia respuesta: solo se retiene lo que el menú usa. */
const expFusionar = function (config) {
    ['corte', 'permisos', 'exportables', 'conteo', 'filtros', 'requiere'].forEach(function (clave) {
        if ((config || {})[clave] !== undefined) expConfig[clave] = config[clave];
    });
};

/**
 * El permiso y el estado llegan por fetch: la vista solo deja el contenedor.
 * Sin permiso no se pinta nada —ni menú vacío ni botón muerto— y con el corte
 * sin calcular se pinta deshabilitado, porque calcular sí lo resuelve el usuario.
 */
const detMenuExportar = function (config) {
    expFusionar(config);
    const contenedor = document.getElementById('accionesExportar');
    if (!contenedor || !expConfig.corte) return;

    // Donde la pantalla aporta el menú en dos lecturas paralelas, declara con
    // `requiere` la pieza que falta: el menú se pinta una sola vez y ya sin
    // desfase, en vez de anunciar primero un contenido que no es el suyo.
    if ((expConfig.requiere || []).some(clave => expConfig[clave] === undefined)) return;

    if (!(expConfig.permisos || {}).exportar) { contenedor.innerHTML = ''; return; }

    const rotulo = '<i class="fas fa-download"></i>&nbsp; Exportar';
    if (expCorte().estado === 'ABIERTO') {
        contenedor.innerHTML = '<span data-bs-toggle="tooltip" title="El corte todavía no se ha calculado: '
            + 'no hay cifras que exportar">'
            + '<button class="btn btn-sm det-btn-exportar dropdown-toggle" disabled>' + rotulo
            + '</button></span>';
        detTooltips('#accionesExportar');
        return;
    }

    contenedor.innerHTML = '<div class="dropdown">'
        + '<button class="btn btn-sm det-btn-exportar dropdown-toggle" id="btnExportar" '
        + 'data-bs-toggle="dropdown" aria-expanded="false">' + rotulo + '</button>'
        + '<ul class="dropdown-menu dropdown-menu-end det-menu-exportar">'
        + expSalidas.map(expItem).join('') + '</ul></div>';
};

/** Primer toast del módulo: no bloquea, va abajo a la derecha y no lleva botón. */
const detToast = function (icono, titulo, temporizador) {
    Swal.fire({ toast: true, position: 'bottom-end', showConfirmButton: false,
        timer: temporizador || false, icon: icono, title: titulo });
};

/**
 * Ninguno de los tres motivos es una falla: icono informativo y nunca detError.
 * Con cuentas por definir se listan los conceptos en gris, porque nadie desde
 * esta pantalla los resuelve; con los otros dos motivos no hay lista que
 * mostrar y el mensaje del servidor ya viene redactado para leerse tal cual.
 */
const expAvisoAsiento = function (asiento) {
    const faltan = asiento.faltanCuentas || [];
    const conceptos = faltan.map(c => '<div class="det-requisito">'
        + '<span class="det-punto nota"></span><span>' + expTexto(c) + '</span></div>').join('');

    const cuerpo = faltan.length
        ? '<p class="det-subtitulo">El asiento no se puede generar porque Contabilidad todavía no ha '
            + 'definido la cuenta de ' + detEntero(faltan.length)
            + (faltan.length === 1 ? ' concepto' : ' conceptos')
            + '. No es una falla del módulo ni del corte: en cuanto las cuentas queden parametrizadas, '
            + 'el archivo se genera sin recalcular nada.</p>'
            + '<div class="text-start">' + conceptos + '</div>'
            + '<p class="det-subtitulo mb-0">Definición pendiente de Contabilidad.</p>'
        : '<p class="det-subtitulo mb-0">'
            + expTexto(asiento.mensaje || 'El asiento no está disponible para este corte.') + '</p>';

    Swal.fire({
        icon: 'info',
        title: expTituloAsiento[asiento.motivo] || 'El asiento no está disponible',
        html: cuerpo,
        confirmButtonText: 'Entendido', confirmButtonColor: 'rgb(65,110,195)'
    });
};

/** El nombre lo fija el servidor; el compuesto aquí es solo el respaldo. */
const expNombre = function (respuesta, salida) {
    const cabecera = respuesta.headers.get('Content-Disposition') || '';
    const encontrado = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(cabecera);
    if (encontrado) {
        try { return decodeURIComponent(encontrado[1]); } catch (e) { return encontrado[1]; }
    }

    return 'deterioro_' + salida.clave + '_' + detFecha(expCorte().fecha_corte) + '.' + salida.extension;
};

/** Ancla sintética sobre el blob: con la descarga directa no hay rechazo que leer. */
const expGuardar = function (blob, nombre) {
    const url = URL.createObjectURL(blob);
    const ancla = document.createElement('a');
    ancla.href = url;
    ancla.download = nombre;
    document.body.appendChild(ancla);
    ancla.click();
    ancla.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
};

/** El detalle exporta lo que la grilla muestra: reutiliza sus mismos filtros. */
const expDatos = function (clave) {
    if (clave === 'detalle' && expConfig.filtros) return expConfig.filtros();
    const datos = new FormData();
    datos.append('idCorte', expCorte().id_corte);
    return datos;
};

const expRechazo = async function (respuesta) {
    let datos = {};
    try { datos = await respuesta.json(); } catch (e) { datos = {}; }

    const asiento = (datos.exportables || {}).asiento;
    if (asiento && asiento.disponible === false) {
        // La disponibilidad del rechazo es la de ahora: pudo cambiar desde que se pintó el menú.
        detMenuExportar({ exportables: datos.exportables });
        expAvisoAsiento(asiento);
        return;
    }
    detError(datos.title || 'No se pudo generar el archivo',
        datos.text || 'El servidor rechazó la exportación.');
};

/** El toast de espera no tiene temporizador: se cierra donde se deja de esperar. */
const expCerrarEspera = function (aviso) {
    clearTimeout(aviso);
    Swal.close();
};

/**
 * Una descarga a la vez y sin preloader de pantalla: lo único que se bloquea es
 * el botón. Antes de tocar el blob hay que descartar que el cuerpo sea un
 * rechazo de negocio —JSON con 200— o una página de error del servidor: guardar
 * un 500 como .xlsx deja al usuario un archivo que no abre y sin mensaje.
 */
const detDescargar = async function (clave) {
    if (expDescargando) return;
    const salida = expSalidas.find(s => s.clave === clave);
    if (clave === 'asiento' && !expAsientoDisponible()) { expAvisoAsiento(expAsiento()); return; }

    const boton = document.getElementById('btnExportar');
    const rotulo = boton.innerHTML;
    expDescargando = true;
    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>&nbsp; Generando…';

    const aviso = setTimeout(() => detToast('info', 'Generando el archivo…'), 400);
    const aborto = new AbortController();
    const vence = setTimeout(() => aborto.abort(), expEsperaMaxima);

    try {
        const respuesta = await fetch(`${globalUrl}${salida.ruta}`, {
            method: 'post', body: expDatos(clave),
            // Sin estas dos cabeceras una excepción no controlada vuelve como la
            // página de error de Laravel, que el cliente no sabe leer.
            headers: { 'X-CSRF-TOKEN': expToken(), 'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest' },
            signal: aborto.signal
        });
        expCerrarEspera(aviso);

        if (respuesta.status === 403) {
            detError('Sin permiso', 'Tu usuario no tiene permiso para exportar el corte');
        } else if (!respuesta.ok) {
            detError('No se pudo generar el archivo', 'El servidor respondió con un error ('
                + respuesta.status + ') y no devolvió el archivo. Vuelve a intentarlo; si persiste, '
                + 'avisa a Desarrollo.');
        } else if ((respuesta.headers.get('Content-Type') || '').indexOf('json') !== -1) {
            await expRechazo(respuesta);
        } else {
            const nombre = expNombre(respuesta, salida);
            expGuardar(await respuesta.blob(), nombre);
            detToast('success', 'Se descargó ' + nombre, 4000);
        }
    } catch (e) {
        expCerrarEspera(aviso);
        detError('No se pudo generar el archivo', aborto.signal.aborted
            ? 'La generación superó el tiempo máximo. Vuelve a intentarlo; si persiste, exporta el '
                + 'detalle acotado por producto.'
            : 'No fue posible descargar el archivo. Vuelve a intentarlo.');
    }

    clearTimeout(vence);
    // El rechazo repinta el menú: el botón que hay que soltar es el vigente, no
    // el que se bloqueó.
    const vigente = document.getElementById('btnExportar');
    if (vigente) {
        vigente.disabled = false;
        vigente.innerHTML = rotulo;
    }
    expDescargando = false;
};
