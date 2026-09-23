/** Controles del corte: requisitos de cierre, C-1, C-2, cuadres y bitácora. */

const ctrlToken = () => $('meta[name="csrf-token-deterioro"]').attr('content');

const ctrlNum = v => Number(v || 0);

const ctrlTexto = t => String(t === null || t === undefined ? '' : t)
    .replace(/[<>&"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));

/** El cierre y la bitácora necesitan el minuto: con el día no se ordena la gestión. */
const ctrlFechaHora = v => String(v || '').replace('T', ' ').substring(0, 16);

let ctrlIdCorte = null;
let ctrlDatos = null;
let ctrlTablaProrrogas = null;
let ctrlTablaBajas = null;
let ctrlTablaBitacora = null;
let ctrlBitacoraCargada = false;
let ctrlSecuencia = 0;

window.addEventListener('load', function () {
    ctrlIdCorte = detCorteDeUrl();
    if (!ctrlIdCorte) {
        window.location.href = `${globalUrl}/deterioro-cortes`;
        return;
    }
    document.getElementById('migaResumen').href = `${globalUrl}/deterioro-resumen?corte=${ctrlIdCorte}`;
    document.getElementById('btnResumenCt').href = `${globalUrl}/deterioro-resumen?corte=${ctrlIdCorte}`;
    document.getElementById('btnDetalleCt').href = `${globalUrl}/deterioro-detalle-operaciones?corte=${ctrlIdCorte}`;
    document.getElementById('btnComparativoCt').href = `${globalUrl}/deterioro-contable-fiscal?corte=${ctrlIdCorte}`;
    document.getElementById('btnEvolucionCt').href = `${globalUrl}/deterioro-evolucion?corte=${ctrlIdCorte}`;
    document.getElementById('btnSuspensionesCt').href = `${globalUrl}/deterioro-suspensiones?corte=${ctrlIdCorte}`;
    document.getElementById('btnConciliacionCt').href = `${globalUrl}/deterioro-conciliacion?corte=${ctrlIdCorte}`;
    cargarControles();
});

/**
 * Una sola lectura para toda la pantalla: las tarjetas, el tablero, los badges
 * de las anclas y la matriz de botones del cierre salen del mismo corte que las
 * tablas y no pueden quedar desfasados de ellas.
 */
const cargarControles = async function () {
    $('#divControles').preloader();
    const datos = new FormData();
    datos.append('idCorte', ctrlIdCorte);
    const res = await makeOptionsFetch(`${globalUrl}/deterioro-controles-datos`, datos, 'post', ctrlToken());
    $('#divControles').preloader('remove');

    if (res.res !== 'ok') { detError('No se pudo cargar', res.text); return; }

    ctrlDatos = res;
    document.getElementById('migaFecha').textContent = detFecha(res.corte.fecha_corte);
    document.getElementById('badgeEstado').innerHTML =
        detEstadoCorte(res.corte.estado, res.corte.cerrado_con_salvedad);

    pintarTarjetasControles();
    pintarAnclasControles();
    pintarCierre();
    pintarFotoSalvedad();
    pintarTablero();
    pintarProrrogas();
    pintarBajasCtrl();
    pintarCuadresCtrl();
    poblarCausalesBaja();
    prepararBitacora();
    detTooltips('#divControles');
    detMenuExportar(res);
};

/* --- Lectura de la respuesta --- */

const ctrlCorte = () => ctrlDatos.corte || {};
const ctrlCierre = () => ctrlDatos.cierre || {};
const ctrlBloqueos = () => ctrlCierre().bloqueos || [];
const ctrlBajas = () => ctrlDatos.bajas || [];
const ctrlProrrogas = () => ctrlDatos.prorrogas || [];
const ctrlPermisos = () => ctrlDatos.permisos || {};
const ctrlPendientes = () => ctrlBajas().filter(b => b.clasificacion === 'PENDIENTE');
const ctrlFallas = () => (ctrlDatos.cuadres || []).filter(c => c.estado === 'FALLA');
const ctrlBloqueo = tipo => ctrlBloqueos().find(b => b.tipo === tipo);

const ctrlSinCalcular = () => ctrlCorte().estado === 'ABIERTO';
const ctrlCerrado = () => ctrlCorte().estado === 'CERRADO';

/** Los cortes cerrados antes de guardarse el nombre solo tienen el id. */
const ctrlAutor = (nombre, id) => nombre ? ctrlTexto(nombre) : 'el usuario ' + ctrlTexto(id);

/** Orden fijo por gravedad, no el de llegada de la respuesta. */
const ctrlOrdenBloqueo = ['CUADRE_EN_FALLA', 'CONCILIACION_SIN_EXPLICAR', 'BAJA_SIN_CLASIFICAR'];

const ctrlOrdenar = lista => (lista || []).slice()
    .sort((a, b) => ctrlOrdenBloqueo.indexOf(a.tipo) - ctrlOrdenBloqueo.indexOf(b.tipo));

const ctrlEtiquetaCorta = {
    'CUADRE_EN_FALLA': 'controles de cuadre',
    'CONCILIACION_SIN_EXPLICAR': 'conciliación con SIESA',
    'BAJA_SIN_CLASIFICAR': 'bajas sin clasificar'
};

const ctrlEtiquetaTipo = {
    'SOLO_FACTORING': 'Sin saldo en SIESA',
    'SOLO_SIESA': 'Sin operación en el corte',
    'DIFERENCIA': 'Diferencia de saldo'
};

const ctrlPlural = { 'control': 'controles', 'partida': 'partidas', 'operación': 'operaciones' };

const ctrlUnidad = (cantidad, unidad) => detEntero(cantidad) + ' '
    + (Math.abs(ctrlNum(cantidad)) === 1 ? unidad : (ctrlPlural[unidad] || unidad + 's'));

const ctrlFaltan = n =>
    (ctrlNum(n) === 1 ? 'Falta ' : 'Faltan ') + ctrlUnidad(n, 'requisito');

/** Enumera en castellano: «cuadres, conciliación y bajas». */
const ctrlLista = function (valores) {
    if (valores.length < 2) return valores.join('');
    return valores.slice(0, -1).join(', ') + ' y ' + valores[valores.length - 1];
};

/* --- Tarjetas y anclas --- */

const ctrlSinCalcularPie = 'El corte todavía no se ha calculado';

const pintarTarjetasControles = function () {
    const contenedor = document.getElementById('tarjetasControles');

    if (ctrlSinCalcular()) {
        contenedor.innerHTML =
            detTarjetaCifra('Requisitos de cierre', '&mdash;', ctrlSinCalcularPie, 'destacada')
            + detTarjetaCifra('Prórrogas que reducen mora', '&mdash;', 'Se identifican al calcular el corte')
            + detTarjetaCifra('Bajas por clasificar', '&mdash;', 'Se identifican al calcular el corte')
            + detTarjetaCifra('Controles en falla', '&mdash;', 'Los controles corren al calcular el corte');
        return;
    }

    const corte = ctrlCorte();
    const bloqueos = ctrlOrdenar(ctrlBloqueos());
    const pendientes = ctrlPendientes().length;
    const prorrogas = ctrlProrrogas();
    const liberado = prorrogas.reduce((a, p) => a + ctrlNum(p.deterioro_liberado), 0);
    const fallas = ctrlFallas();
    const diferencia = fallas.reduce((a, c) => a + Math.abs(ctrlNum(c.diferencia)), 0);

    // El estado vigente de un corte cerrado es la foto del cierre, no el recuento vivo.
    const destacada = ctrlCerrado()
        ? detTarjetaCifra('Cierre del corte',
            corte.cerrado_con_salvedad ? 'Con salvedades' : 'Cerrado',
            'Cerrado el ' + ctrlFechaHora(corte.fecha_cierre) + ' por '
            + ctrlAutor(corte.usuario_cierre, corte.id_usuario_cierre), 'destacada')
        : detTarjetaCifra('Requisitos pendientes', detEntero(bloqueos.length) + ' de 3',
            bloqueos.length
                ? 'Pendiente: ' + ctrlLista(bloqueos.map(b => ctrlEtiquetaCorta[b.tipo] || b.tipo))
                : 'El corte cumple los tres requisitos de cierre', 'destacada');

    contenedor.innerHTML = destacada
        + detTarjetaCifra('Prórrogas que reducen mora', detEntero(prorrogas.length),
            detMoneda2.format(liberado) + ' de deterioro liberado')
        + detTarjetaCifra('Bajas por clasificar',
            detEntero(pendientes) + ' de ' + detEntero(ctrlBajas().length),
            'Operaciones que salieron en el período')
        + detTarjetaCifra('Controles en falla', detEntero(fallas.length),
            detMoneda2.format(diferencia) + ' de diferencia en valor absoluto');
};

const ctrlBadge = (clase, texto) => '<span class="det-badge ' + clase + '">' + texto + '</span>';

/** Un corte cerrado no ofrece cerrarse: informa de su cierre. */
const ctrlBadgeCierre = function () {
    if (ctrlCerrado()) {
        return ctrlCorte().cerrado_con_salvedad
            ? ctrlBadge('det-salvedad', 'Cerrado con salvedades')
            : ctrlBadge('det-estado-cerrado', 'Cerrado');
    }
    const faltan = ctrlBloqueos().length;
    return faltan ? ctrlBadge('det-ambar', detEntero(faltan) + ' por resolver')
        : ctrlBadge('det-estado-cerrado', 'Se puede cerrar');
};

/** El badge cuenta lo pendiente de cada bloque, no su volumen. */
const pintarAnclasControles = function () {
    const anclas = [
        { href: '#panelCierre', titulo: 'Cierre', badge: ctrlBadgeCierre() },
        { href: '#panelProrrogas', titulo: 'C-1 · Prórrogas', pendientes: ctrlProrrogas().length,
            pend: 'por revisar', ok: 'Sin novedad' },
        { href: '#panelBajas', titulo: 'C-2 · Bajas', pendientes: ctrlPendientes().length,
            pend: 'por clasificar', ok: 'Todas clasificadas' },
        { href: '#panelCuadresCtrl', titulo: 'Controles de cuadre', pendientes: ctrlFallas().length,
            pend: 'en falla', ok: 'Todos cuadran' }
    ];
    if (ctrlPermisos().auditar) {
        // La bitácora no es un requisito: su badge no lleva la semántica del verde.
        anclas.push({ href: '#panelBitacora', titulo: 'Bitácora', neutro: true,
            onclick: ' onclick="abrirBitacora()"',
            badge: ctrlBadge('det-inactivo', 'Registro de eventos') });
    }

    document.getElementById('anclasControles').innerHTML = anclas.map(function (a) {
        const badge = a.badge || (a.pendientes
            ? ctrlBadge('det-ambar', detEntero(a.pendientes) + ' ' + a.pend)
            : ctrlBadge('det-estado-cerrado', a.ok));
        return '<a href="' + a.href + '"' + (a.onclick || '') + '>' + a.titulo
            + (ctrlSinCalcular() && !a.neutro ? '' : ' ' + badge) + '</a>';
    }).join('');
};

/* --- Requisitos y acciones de cierre --- */

const ctrlHrefBloqueo = function (tipo) {
    if (tipo === 'CUADRE_EN_FALLA') return '#panelCuadresCtrl';
    if (tipo === 'BAJA_SIN_CLASIFICAR') return '#panelBajas';
    return `${globalUrl}/deterioro-conciliacion?corte=${ctrlIdCorte}`;
};

const ctrlRotuloBloqueo = {
    'CUADRE_EN_FALLA': 'Ver controles',
    'CONCILIACION_SIN_EXPLICAR': 'Explicar partidas',
    'BAJA_SIN_CLASIFICAR': 'Clasificar bajas'
};

/** El desglose del bloqueo se rotula en castellano: el tipo crudo no se lee. */
const ctrlDesglose = function (b) {
    const detalle = b.detalle || [];
    if (b.tipo === 'CUADRE_EN_FALLA') {
        return detalle.map(d => ctrlTexto(d.codigo)).join(' &middot; ');
    }
    if (b.tipo === 'CONCILIACION_SIN_EXPLICAR') {
        return detalle.map(d => (ctrlEtiquetaTipo[d.tipo] || ctrlTexto(d.tipo))
            + ' ' + detEntero(d.cantidad)).join(' &middot; ');
    }
    return '';
};

/**
 * La fila del requisito es la misma en la lista viva, en el modal del forzado y
 * en la foto congelada, pero el gris y la acción son ejes distintos: el gris
 * marca lo que ya no se puede resolver, y en el modal el usuario está decidiendo
 * sobre requisitos que siguen rojos.
 */
const ctrlFilaRequisito = function (b, gris, sinAccion) {
    const desglose = ctrlDesglose(b);
    return '<div class="det-requisito">'
        + '<span class="det-punto ' + (gris ? 'nota' : 'falla') + '"></span>'
        + '<span>' + ctrlTexto(b.concepto)
        + (desglose ? '<span class="det-subtitulo d-block">' + desglose + '</span>' : '') + '</span>'
        + '<span class="cifra">' + ctrlUnidad(b.cantidad, b.unidad)
        + ' &middot; ' + detMoneda2.format(ctrlNum(b.valor)) + '</span>'
        + (sinAccion ? '' : '<span class="acc"><a href="' + ctrlHrefBloqueo(b.tipo) + '">'
            + (ctrlRotuloBloqueo[b.tipo] || ctrlTexto(b.tipo)) + '</a></span>')
        + '</div>';
};

/** Con el corte limpio la lista no se deja vacía: se pinta como checklist cumplida. */
const ctrlChecklistCumplida = function () {
    return ['Sin controles en falla', 'Conciliación explicada', 'Bajas clasificadas'].map(t =>
        '<div class="det-requisito"><span class="det-punto ok"></span><span>' + t + '</span></div>').join('');
};

const pintarCierre = function () {
    const corte = ctrlCorte();
    const bloqueos = ctrlOrdenar(ctrlBloqueos());
    const aviso = document.getElementById('avisoCierre');
    const lista = document.getElementById('listaRequisitos');

    if (corte.estado === 'ABIERTO') {
        aviso.innerHTML = '<div class="det-aviso"><i class="fas fa-triangle-exclamation mt-1"></i><div>'
            + 'Hay que calcular el corte antes de cerrarlo. '
            + '<a href="' + globalUrl + '/deterioro-cortes">Ir a Cortes</a></div></div>';
        lista.innerHTML = '';
        document.getElementById('accionesCierre').innerHTML = '';
        document.getElementById('accionForzar').innerHTML = '';
        return;
    }

    if (corte.estado === 'CERRADO') {
        aviso.innerHTML = corte.salvedad
            ? '<div class="det-aviso"><i class="fas fa-triangle-exclamation mt-1"></i><div>'
            + 'Cerrado CON SALVEDADES el ' + ctrlFechaHora(corte.fecha_cierre)
            + ' por ' + ctrlAutor(corte.usuario_cierre, corte.id_usuario_cierre)
            + '. Los requisitos sin resolver quedaron congelados en la foto del cierre.</div></div>'
            : '<div class="det-aviso info"><i class="fas fa-circle-check mt-1"></i><div>'
            + 'Cerrado sin salvedades el ' + ctrlFechaHora(corte.fecha_cierre)
            + ' por ' + ctrlAutor(corte.usuario_cierre, corte.id_usuario_cierre) + '.</div></div>';
        lista.innerHTML = '';
        pintarAccionesCierre();
        return;
    }

    aviso.innerHTML = ctrlCierre().puede
        ? '<div class="det-aviso info"><i class="fas fa-circle-check mt-1"></i><div>'
        + 'El corte cumple los requisitos y se puede cerrar.</div></div>'
        : '<div class="det-aviso"><i class="fas fa-triangle-exclamation mt-1"></i><div>'
        + ctrlFaltan(bloqueos.length) + ' por resolver antes de cerrar: '
        + ctrlLista(bloqueos.map(b => ctrlUnidad(b.cantidad, b.unidad))) + '.</div></div>';

    lista.innerHTML = ctrlCierre().puede
        ? ctrlChecklistCumplida()
        : bloqueos.map(b => ctrlFilaRequisito(b, false, false)).join('');

    pintarAccionesCierre();
};

/**
 * Falta de permiso es botón ausente; el deshabilitado se reserva para los
 * requisitos sin resolver, que sí se pueden resolver. Forzar el cierre nunca
 * es el botón primario ni se rotula «Cerrar»: es la excepción, no el camino.
 */
const pintarAccionesCierre = function () {
    const p = ctrlPermisos();
    const puede = !!ctrlCierre().puede;
    const faltan = ctrlBloqueos().length;
    let html = '';
    let forzar = '';

    if (ctrlCerrado()) {
        html = p.reabrir
            ? '<button class="btn btn-outline-danger btn-sm" onclick="abrirReabrirCorte()">Reabrir corte</button>'
            : '';
    } else {
        if (p.cerrar && puede) {
            html = '<button class="btn btn-primary btn-sm" onclick="confirmarCerrarCorte(this)">Cerrar corte</button>';
        } else if (p.cerrar) {
            html = '<span data-bs-toggle="tooltip" title="' + ctrlFaltan(faltan) + ' por resolver">'
                + '<button class="btn btn-primary btn-sm" disabled>Cerrar corte</button></span>';
        }
        // La ruta del forzado pasa antes por el permiso de cerrar: sin él es un 403.
        if (p.cerrar && p.forzarCierre && !puede) {
            forzar = '<button class="btn btn-link btn-sm p-0 text-danger" onclick="abrirForzarCierre()">'
                + 'Forzar el cierre con salvedad</button>';
        }
    }

    document.getElementById('accionesCierre').innerHTML = html;
    document.getElementById('accionForzar').innerHTML = forzar;
    detTooltips('#accionesCierre');
};

/**
 * La foto se pinta con los bloqueos que el cierre congeló, no con los de hoy:
 * es la única forma de reconstruir dentro de un año por qué se cerró así.
 */
const pintarFotoSalvedad = function () {
    const s = ctrlCorte().salvedad;
    const panel = document.getElementById('panelFotoSalvedad');

    if (!s) {
        panel.style.display = 'none';
        document.getElementById('contenidoFotoSalvedad').innerHTML = '';
        return;
    }
    panel.style.display = '';

    document.getElementById('contenidoFotoSalvedad').innerHTML =
        '<span class="det-dato"><strong>Cerrado el ' + ctrlFechaHora(s.fecha) + '</strong>'
        + ' por ' + ctrlAutor(s.usuario, s.id_usuario) + '</span>'
        + '<span class="det-dato mt-2">' + ctrlTexto(s.motivo) + '</span>'
        + '<div class="det-congelado"><p class="det-subtitulo mb-1">'
        + 'Requisitos que quedaron sin resolver al cerrar</p>'
        + ctrlOrdenar(s.bloqueos).map(b => ctrlFilaRequisito(b, true, true)).join('') + '</div>';
};

/* --- Tablero de los cinco controles --- */

const ctrlFilaTablero = function (f) {
    return '<div class="det-requisito"><span class="det-punto ' + f.punto + '"></span>'
        + '<span>' + f.titulo + (f.sinFase ? ' <span class="det-na">no disponible en esta fase</span>' : '')
        + '<span class="det-subtitulo d-block">' + f.detalle + '</span></span>'
        + '<span class="cifra">' + (f.cifra || '<span class="dif na">&mdash;</span>') + '</span>'
        + (f.href ? '<span class="acc"><a href="' + f.href + '">' + f.acc + '</a></span>' : '')
        + '</div>';
};

const pintarTablero = function () {
    const prorrogas = ctrlProrrogas().length;
    const pendientes = ctrlPendientes().length;
    const conciliacion = ctrlBloqueo('CONCILIACION_SIN_EXPLICAR');

    const filas = [
        {
            vivo: true, punto: prorrogas ? 'nota' : 'ok',
            titulo: 'C-1 &middot; Prórrogas que reducen la antigüedad de la mora',
            detalle: prorrogas
                ? 'Operaciones cuya mora bajó contra el corte anterior, pendientes de revisión'
                : 'Ninguna operación redujo su antigüedad de mora en este corte',
            cifra: prorrogas ? ctrlUnidad(prorrogas, 'operación') : 'Sin novedad',
            href: '#panelProrrogas', acc: 'Revisar'
        },
        {
            vivo: true, punto: pendientes ? 'falla' : 'ok',
            titulo: 'C-2 &middot; Bajas del período',
            detalle: pendientes
                ? 'La clasificación de la salida es requisito para cerrar el corte'
                : 'Todas las salidas del período tienen causa registrada',
            cifra: pendientes ? ctrlUnidad(pendientes, 'operación') + ' sin clasificar' : 'Clasificadas',
            href: '#panelBajas', acc: 'Clasificar bajas'
        },
        {
            vivo: true, punto: conciliacion ? 'falla' : 'ok',
            titulo: 'C-3 &middot; Conciliación con SIESA',
            detalle: conciliacion
                ? 'Partidas del cruce contra SIESA sin explicación registrada'
                : 'Las partidas del cruce contra SIESA están explicadas',
            cifra: conciliacion
                ? ctrlUnidad(conciliacion.cantidad, conciliacion.unidad) : 'Explicada',
            href: `${globalUrl}/deterioro-conciliacion?corte=${ctrlIdCorte}`, acc: 'Ir a la conciliación'
        },
        {
            punto: 'nota', sinFase: true,
            titulo: 'C-4 &middot; Sincronía de marcas con factoring',
            detalle: 'Compara las marcas de suspensión del módulo contra las del sistema de factoring'
        },
        {
            punto: 'nota', sinFase: true,
            titulo: 'C-5 &middot; Cobertura del cargue inicial de suspensiones',
            detalle: 'Verifica que toda operación del archivo de Contabilidad quede con base y deterioro resueltos'
        }
    ];

    document.getElementById('listaTablero').innerHTML = filas.map(function (f) {
        if (ctrlSinCalcular() && f.vivo) {
            return ctrlFilaTablero(Object.assign({}, f,
                { punto: 'nota', cifra: '', href: '', detalle: ctrlSinCalcularPie }));
        }
        // Sobre un corte cerrado la acción llevaría a pantallas donde ya nada se edita.
        return ctrlFilaTablero(ctrlCerrado() ? Object.assign({}, f, { href: '' }) : f);
    }).join('');
};

/* --- C-1 · Prórrogas que reducen la antigüedad de la mora --- */

const ctrlCifra = (etiqueta, valor) =>
    '<div class="text-end"><span class="det-subtitulo d-block">' + etiqueta + '</span>'
    + '<span class="det-spark-cifra">' + valor + '</span></div>';

/** El deterioro liberado en negativo es un aumento: no se netea ni se esconde. */
const ctrlEfectoProrroga = function (valor) {
    const n = ctrlNum(valor);
    if (n > 0) return '<span class="det-chip libera"><i class="fas fa-arrow-down"></i>Libera</span>';
    if (n < 0) return '<span class="det-var aumenta">+' + detMoneda2.format(Math.abs(n)) + '</span>';
    return '<span class="det-cero">Sin efecto</span>';
};

const ctrlFilaProrroga = function (p) {
    const cruzo = ctrlNum(p.cruzo_umbral_fiscal) === 1;
    return '<tr' + (cruzo ? ' class="det-atencion"' : '') + '>'
        + '<td><strong>' + p.id_operacion + '</strong>'
        + (p.nom_operacion ? '<span class="det-subtitulo d-block">' + ctrlTexto(p.nom_operacion) + '</span>' : '')
        + '</td>'
        + '<td>' + ctrlTexto(p.cliente)
        + '<span class="det-subtitulo d-block">' + ctrlTexto(p.id_cliente) + '</span></td>'
        + '<td>' + ctrlTexto(p.producto) + '</td>'
        + '<td class="num" data-order="' + ctrlNum(p.dias_mora_anterior) + '">'
        + detEntero(p.dias_mora_anterior) + '</td>'
        + '<td class="num" data-order="' + ctrlNum(p.dias_mora) + '">' + detEntero(p.dias_mora) + '</td>'
        + '<td class="num" data-order="' + ctrlNum(p.dias_reducidos) + '">' + detEntero(p.dias_reducidos)
        + (cruzo ? ' <span class="det-badge det-ambar" data-bs-toggle="tooltip" '
            + 'title="La operación dejó de cumplir la mora mínima que exige la deducción fiscal">'
            + 'Umbral fiscal</span>' : '') + '</td>'
        + '<td data-order="' + (detOrdenRango[p.rango_anterior] ?? 9) + '">'
        + detBadgeRango(p.rango_anterior) + '</td>'
        + '<td data-order="' + (detOrdenRango[p.rango] ?? 9) + '">' + detBadgeRango(p.rango) + '</td>'
        + detCeldaNum(p.base_deterioro)
        + detCeldaNum(p.deterioro_liberado)
        + '<td>' + ctrlEfectoProrroga(p.deterioro_liberado) + '</td>'
        + '</tr>';
};

const pintarProrrogas = function () {
    const prorrogas = ctrlSinCalcular() ? [] : ctrlProrrogas();
    const liberado = prorrogas.reduce((a, p) => a + ctrlNum(p.deterioro_liberado), 0);
    const cruzaron = prorrogas.filter(p => ctrlNum(p.cruzo_umbral_fiscal) === 1).length;

    document.getElementById('cifrasProrrogas').innerHTML = !prorrogas.length ? '' :
        ctrlCifra('Operaciones', detEntero(prorrogas.length))
        + ctrlCifra('Deterioro liberado', detMoneda2.format(liberado))
        + ctrlCifra('Cruzaron el umbral fiscal', detEntero(cruzaron));

    if (ctrlTablaProrrogas) { ctrlTablaProrrogas.destroy(); ctrlTablaProrrogas = null; }

    if (!prorrogas.length) {
        document.getElementById('scrollProrrogas').style.display = 'none';
        document.getElementById('leyendaProrrogas').style.display = 'none';
        document.getElementById('tbodyProrrogas').innerHTML = '';
        document.getElementById('vacioProrrogas').innerHTML = ctrlSinCalcular()
            ? detVacio('fa-calculator', 'Sin prórrogas que revisar todavía',
                'El corte todavía no se ha calculado: las prórrogas se identifican al calcularlo.')
            : ctrlCorte().id_corte_anterior
            ? detVacio('fa-circle-check', 'Sin prórrogas que revisar',
                'Ninguna operación redujo su antigüedad de mora respecto al corte anterior.')
            : detVacio('fa-scale-balanced', 'Sin corte anterior',
                'Las prórrogas se identifican contra un corte anterior, y este corte no lo tiene.');
        pintarCuadreProrrogas();
        return;
    }

    document.getElementById('scrollProrrogas').style.display = '';
    document.getElementById('leyendaProrrogas').style.display = '';
    document.getElementById('vacioProrrogas').innerHTML = '';
    document.getElementById('tbodyProrrogas').innerHTML = prorrogas.map(ctrlFilaProrroga).join('');

    ctrlTablaProrrogas = crearTablaDeterioro('tablaProrrogas', {
        order: [[9, 'desc']],
        columnDefs: [{ targets: [10], orderable: false }]
    });
    detTooltips('#panelProrrogas');
    pintarCuadreProrrogas();
};

/** El cuadre propio de C-1 va bajo su tabla, además de la lista general. */
const pintarCuadreProrrogas = function () {
    const cuadre = (ctrlDatos.cuadres || []).find(c => c.codigo === 'C-PRORROGA');
    document.getElementById('cuadreProrrogas').innerHTML = cuadre
        ? '<div class="det-cierre">' + detFilaCuadre(cuadre) + '</div>' : '';
};

/* --- C-2 · Bajas del período --- */

const ctrlOrdenClasificacion = b => b.clasificacion === 'PENDIENTE' ? 0 : 1;

const ctrlEstadoBaja = function (b) {
    if (b.clasificacion === 'PENDIENTE') {
        return '<span class="det-badge det-ambar">Sin clasificar</span>';
    }
    return '<span class="det-badge det-estado-cerrado">'
        + ctrlTexto(b.causal || b.clasificacion) + '</span>'
        + (ctrlNum(b.cierra_fiscal) === 1
            ? ' <span class="det-badge det-inactivo" data-bs-toggle="tooltip" '
            + 'title="Esta causal cierra la deducción fiscal acumulada de la operación">Cierra fiscal</span>' : '');
};

const ctrlCeldaObservacion = function (b) {
    const orden = ' data-order="' + ctrlOrdenClasificacion(b) + '">';
    if (!b.observacion) {
        return '<td class="det-texto"' + orden + detAusente('Sin clasificación registrada') + '</td>';
    }
    return '<td class="det-texto"' + orden + ctrlTexto(b.observacion)
        + '<span class="det-subtitulo d-block">' + ctrlTexto(b.usuario || ('Usuario ' + b.id_usuario))
        + ' &middot; ' + detFecha(b.fecha) + '</span></td>';
};

const ctrlCeldaAccionBaja = function (b) {
    const pendiente = b.clasificacion === 'PENDIENTE';
    const clase = pendiente ? 'btn-primary' : 'btn-light';
    const rotulo = pendiente ? 'Clasificar' : 'Ver / editar';

    if (ctrlCorte().estado === 'CERRADO') {
        return '<td class="text-end"><span data-bs-toggle="tooltip" '
            + 'title="El corte está cerrado: sus bajas ya no se pueden clasificar">'
            + '<button class="btn ' + clase + ' btn-sm py-0 px-2" disabled>' + rotulo + '</button></span></td>';
    }
    return '<td class="text-end"><button class="btn ' + clase + ' btn-sm py-0 px-2" '
        + 'onclick="abrirClasificarBaja(' + b.id_operacion + ')">' + rotulo + '</button></td>';
};

/** Sin permiso de clasificar la columna no se pinta vacía: se elimina entera. */
const ctrlHayAccionBaja = () => !!ctrlPermisos().clasificarBaja;

const ctrlFilaBaja = function (b) {
    const pendiente = b.clasificacion === 'PENDIENTE';
    return '<tr' + (pendiente ? ' class="det-atencion"' : '') + '>'
        + '<td><strong>' + b.id_operacion + '</strong>'
        + (b.nom_operacion ? '<span class="det-subtitulo d-block">' + ctrlTexto(b.nom_operacion) + '</span>' : '')
        + '</td>'
        + '<td>' + ctrlTexto(b.cliente)
        + '<span class="det-subtitulo d-block">' + ctrlTexto(b.id_cliente) + '</span></td>'
        + '<td>' + ctrlTexto(b.producto) + '</td>'
        + '<td data-order="' + (detOrdenRango[b.rango] ?? 9) + '">' + detBadgeRango(b.rango) + '</td>'
        + '<td class="num" data-order="' + ctrlNum(b.dias_mora_operacion) + '">'
        + detEntero(b.dias_mora_operacion) + '</td>'
        + detCeldaNum(b.base_deterioro)
        + detCeldaNum(b.deterioro_contable)
        + '<td data-order="' + ctrlOrdenClasificacion(b) + '">' + ctrlEstadoBaja(b) + '</td>'
        + ctrlCeldaObservacion(b)
        + '<td>' + (b.referencia_operacion_nueva
            ? ctrlTexto(b.referencia_operacion_nueva)
            : detAusente('Sin operación nueva registrada')) + '</td>'
        + (ctrlHayAccionBaja() ? ctrlCeldaAccionBaja(b) : '')
        + '</tr>';
};

const pintarBajasCtrl = function () {
    const bajas = ctrlSinCalcular() ? [] : ctrlBajas();
    const pendientes = bajas.filter(b => b.clasificacion === 'PENDIENTE').length;
    const descomposicion = ctrlSinCalcular() ? [] : (ctrlDatos.descomposicionBajas || []);
    const liberado = bajas.reduce((a, b) => a + ctrlNum(b.deterioro_contable), 0);
    const fiscal = descomposicion.reduce((a, d) => a + ctrlNum(d.fiscal_cerrado), 0);

    document.getElementById('cifrasBajasCtrl').innerHTML = !bajas.length ? '' :
        ctrlCifra('Bajas del período', detEntero(bajas.length))
        + ctrlCifra('Sin clasificar', detEntero(pendientes)
            + (pendientes ? ' <span class="det-badge det-ambar">Pendientes</span>' : ''))
        + ctrlCifra('Deterioro liberado', detMoneda2.format(liberado))
        + ctrlCifra('Deducción fiscal cerrada', detMoneda2.format(fiscal));

    if (ctrlTablaBajas) { ctrlTablaBajas.destroy(); ctrlTablaBajas = null; }

    if (!bajas.length) {
        document.getElementById('contenidoBajasCtrl').style.display = 'none';
        document.getElementById('tbodyBajasCtrl').innerHTML = '';
        document.getElementById('tbodyDescomposicionBajas').innerHTML = '';
        document.getElementById('vacioBajasCtrl').innerHTML = ctrlSinCalcular()
            ? detVacio('fa-calculator', 'Sin bajas que clasificar todavía',
                'El corte todavía no se ha calculado: las bajas se identifican al calcularlo.')
            : ctrlCorte().id_corte_anterior
            ? detVacio('fa-inbox', 'Sin bajas en el período',
                'Todas las operaciones del corte anterior siguen presentes en este corte.')
            : detVacio('fa-scale-balanced', 'Sin corte anterior',
                'Las bajas se identifican contra un corte anterior, y este corte no lo tiene.');
        return;
    }
    document.getElementById('contenidoBajasCtrl').style.display = '';
    document.getElementById('vacioBajasCtrl').innerHTML = '';

    pintarDescomposicionBajas(descomposicion);

    document.getElementById('theadBajasCtrl').innerHTML = '<tr>'
        + '<th>Operación</th><th>Cliente</th><th>Producto</th><th>Rango</th>'
        + '<th class="num">Días de mora</th><th class="num">Base</th>'
        + '<th class="num">Deterioro que liberó</th><th>Estado</th><th>Observación</th><th>Referencia</th>'
        + (ctrlHayAccionBaja() ? '<th></th>' : '') + '</tr>';

    document.getElementById('tbodyBajasCtrl').innerHTML = bajas.map(ctrlFilaBaja).join('');

    // Pendientes primero y dentro de cada grupo por deterioro: lo que falta por
    // clasificar es lo que bloquea el cierre.
    ctrlTablaBajas = crearTablaDeterioro('tablaBajasCtrl', {
        order: [[7, 'asc'], [6, 'desc']],
        columnDefs: ctrlHayAccionBaja() ? [{ targets: [10], orderable: false }] : []
    });
    detTooltips('#panelBajas');
};

/**
 * Un cero en la deducción fiscal cerrada de una causal que no la cierra diría
 * «no había nada que cerrar», y es otra cosa: esa causal no cierra ninguna.
 */
const pintarDescomposicionBajas = function (descomposicion) {
    const total = { operaciones: 0, base: 0, deterioro: 0, fiscal_cerrado: 0 };

    const filas = descomposicion.map(function (d) {
        Object.keys(total).forEach(k => { total[k] += ctrlNum(d[k]); });
        return '<tr' + (d.clasificacion === 'PENDIENTE' ? ' class="det-atencion"' : '') + '>'
            + '<td>' + ctrlTexto(d.causal) + '</td>'
            + '<td class="num">' + detEntero(d.operaciones) + '</td>'
            + '<td class="num">' + detPesos(d.base, true) + '</td>'
            + '<td class="num">' + detPesos(d.deterioro, true) + '</td>'
            + '<td class="num">' + (ctrlNum(d.cierra_fiscal) === 1
                ? detPesos(d.fiscal_cerrado, true)
                : detAusente('Esta causal no cierra deducción acumulada')) + '</td></tr>';
    }).join('');

    document.getElementById('tbodyDescomposicionBajas').innerHTML = filas
        + '<tr class="det-total"><td>TOTAL</td>'
        + '<td class="num">' + detEntero(total.operaciones) + '</td>'
        + '<td class="num">' + detPesos(total.base, true) + '</td>'
        + '<td class="num">' + detPesos(total.deterioro, true) + '</td>'
        + '<td class="num">' + detPesos(total.fiscal_cerrado, true) + '</td></tr>';
};

/* --- Controles de cuadre --- */

const pintarCuadresCtrl = function () {
    const html = (ctrlDatos.cuadres || []).map(detFilaCuadre).join('');
    document.getElementById('listaCuadresCtrl').innerHTML = html
        || '<p class="text-muted mb-0">Sin controles registrados.</p>';
};

/* --- Modal Clasificar la salida --- */

const ctrlCausal = codigo => (ctrlDatos.causales || []).find(c => c.codigo === codigo);

const poblarCausalesBaja = function () {
    document.getElementById('clasificarCausal').innerHTML = '<option value="">Seleccione…</option>'
        + (ctrlDatos.causales || []).map(c =>
            '<option value="' + ctrlTexto(c.codigo) + '">' + ctrlTexto(c.descripcion)
            + '</option>').join('');
};

const contarObservacionBaja = function () {
    document.getElementById('contadorObservacionBaja').textContent =
        document.getElementById('clasificarObservacion').value.length + ' de 500';
};

const cambiarCausalBaja = function () {
    const causal = ctrlCausal(document.getElementById('clasificarCausal').value);
    document.getElementById('campoReferencia').style.display =
        causal && ctrlNum(causal.pide_referencia) === 1 ? '' : 'none';

    document.getElementById('avisoCausal').innerHTML = causal && ctrlNum(causal.cierra_fiscal) === 1
        ? '<div class="det-aviso mb-0"><i class="fas fa-triangle-exclamation mt-1"></i><div>'
        + 'Esta causal cierra la deducción fiscal acumulada de la operación en este corte.</div></div>'
        : '';
};

/** Nadie clasifica a ciegas: la cabecera repite la baja sin dejarla editar. */
const abrirClasificarBaja = function (idOperacion) {
    const b = ctrlBajas().find(x => Number(x.id_operacion) === Number(idOperacion));
    if (!b) return;

    ['clasificacion', 'observacion'].forEach(campo => {
        document.getElementById('error-' + campo).innerHTML = '';
    });

    document.getElementById('clasificarCabecera').innerHTML =
        '<div><strong>Operación ' + b.id_operacion + '</strong> ' + detBadgeRango(b.rango) + '</div>'
        + '<div class="det-subtitulo">' + ctrlTexto(b.cliente) + ' &middot; ' + ctrlTexto(b.producto) + '</div>'
        + '<div class="mt-1 det-subtitulo">Deterioro que liberó</div>'
        + '<div>' + detMoneda2.format(ctrlNum(b.deterioro_contable)) + '</div>';

    document.getElementById('clasificarCausal').value =
        ctrlCausal(b.clasificacion) ? b.clasificacion : '';
    document.getElementById('clasificarReferencia').value = b.referencia_operacion_nueva || '';
    document.getElementById('clasificarObservacion').value = b.observacion || '';
    document.getElementById('clasificarObservacion').dataset.operacion = b.id_operacion;
    contarObservacionBaja();
    cambiarCausalBaja();
    new bootstrap.Modal(document.getElementById('modalClasificarBaja')).show();
};

/** El error se escribe junto al campo y sin cerrar el modal: lo digitado no se pierde. */
const confirmarClasificarBaja = function () {
    const causal = document.getElementById('clasificarCausal').value;
    const observacion = document.getElementById('clasificarObservacion').value.trim();

    document.getElementById('error-clasificacion').innerHTML = causal
        ? '' : 'La causa de la salida es obligatoria.';
    document.getElementById('error-observacion').innerHTML = observacion
        ? '' : 'La observación es obligatoria.';
    if (!causal || !observacion) return;

    Swal.fire({
        title: '¿Guardar la clasificación?',
        text: 'La salida queda registrada con tu usuario y la fecha en la bitácora del corte. Si la causal '
            + 'cierra la deducción fiscal acumulada, se cierra en este mismo corte.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d9a520'
    }).then(v => { if (v.isConfirmed) guardarClasificacionBaja(); });
};

const guardarClasificacionBaja = async function () {
    const datos = new FormData();
    datos.append('idCorte', ctrlIdCorte);
    datos.append('idOperacion', document.getElementById('clasificarObservacion').dataset.operacion);
    datos.append('clasificacion', document.getElementById('clasificarCausal').value);
    datos.append('observacion', document.getElementById('clasificarObservacion').value);
    datos.append('referencia', document.getElementById('clasificarReferencia').value);

    const res = await makeOptionsFetch(`${globalUrl}/deterioro-clasificar-salida`, datos, 'post', ctrlToken());
    if (res.res !== 'ok') { detError(res.title || 'Error', res.text); return; }

    ctrlCerrarModal('modalClasificarBaja');

    Swal.fire({ title: res.title, text: res.text, icon: 'success', confirmButtonColor: 'rgb(65,110,195)' })
        .then(() => cargarControles());
};

/* --- Cierre, forzado y reapertura --- */

/**
 * La falta de permiso se aborta con 403 y llega como HTML: makeOptionsFetch
 * reventaría al parsearlo y la pantalla quedaría muda.
 */
const ctrlEnviar = async function (ruta, datos) {
    const respuesta = await fetch(`${globalUrl}${ruta}`, {
        method: 'post', body: datos, headers: { 'X-CSRF-TOKEN': ctrlToken() }
    });
    if (respuesta.status === 403) return { res: 'sin-permiso' };
    try {
        return await respuesta.json();
    } catch (e) {
        return { res: 'bad', title: 'Error', text: 'El servidor no devolvió una respuesta válida.' };
    }
};

const ctrlCerrarModal = function (id) {
    const modal = bootstrap.Modal.getInstance(document.getElementById(id));
    if (modal) modal.hide();
};

const confirmarCerrarCorte = function (boton) {
    Swal.fire({
        title: '¿Cerrar el corte?',
        text: 'El corte queda inmutable: ninguna escritura del módulo lo vuelve a tocar. Con el corte de '
            + 'diciembre, el cierre registra además el acumulado fiscal del año gravable.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Cerrar corte', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d9a520'
    }).then(v => { if (v.isConfirmed) enviarCierre('', boton); });
};

const contarMotivoSalvedad = function () {
    document.getElementById('contadorMotivoSalvedad').textContent =
        document.getElementById('motivoSalvedad').value.length + ' de 500';
};

const abrirForzarCierre = function () {
    document.getElementById('error-motivoSalvedad').innerHTML = '';
    document.getElementById('motivoSalvedad').value = '';
    contarMotivoSalvedad();

    document.getElementById('cerrarFechaCorte').textContent = detFecha(ctrlCorte().fecha_corte);
    document.getElementById('cerrarBloqueos').innerHTML =
        ctrlOrdenar(ctrlBloqueos()).map(b => ctrlFilaRequisito(b, false, true)).join('');

    new bootstrap.Modal(document.getElementById('modalForzarCierre')).show();
};

const confirmarForzarCierre = function (boton) {
    const motivo = document.getElementById('motivoSalvedad').value.trim();
    if (!motivo) {
        document.getElementById('error-motivoSalvedad').innerHTML =
            'El motivo de la salvedad es obligatorio.';
        return;
    }
    enviarCierre(motivo, boton);
};

const enviarCierre = async function (motivo, boton) {
    if (boton) boton.disabled = true;
    const datos = new FormData();
    datos.append('idCorte', ctrlIdCorte);
    datos.append('motivoSalvedad', motivo);

    const res = await ctrlEnviar('/deterioro-cerrar-corte', datos);
    if (boton) boton.disabled = false;

    if (res.res === 'sin-permiso') {
        detError('Sin permiso', 'Tu usuario no tiene permiso para forzar el cierre del corte con salvedad.');
        return;
    }
    ctrlCerrarModal('modalForzarCierre');
    // El rechazo trae los requisitos de ahora: la lista en pantalla ya no sirve.
    if (res.res !== 'ok') {
        detError(res.title || 'No se pudo cerrar', res.text);
        cargarControles();
        return;
    }

    // Recarga completa: el cierre cambia el estado, las acciones, el tablero y
    // la foto de la salvedad, no una fila.
    Swal.fire({
        title: res.title, text: res.text,
        icon: res.conSalvedad ? 'warning' : 'success',
        confirmButtonColor: res.conSalvedad ? '#d9a520' : 'rgb(65,110,195)'
    }).then(() => cargarControles());
};

const contarMotivoReapertura = function () {
    document.getElementById('contadorMotivoReapertura').textContent =
        document.getElementById('motivoReapertura').value.length + ' de 500';
};

const abrirReabrirCorte = function () {
    document.getElementById('error-motivo').innerHTML = '';
    document.getElementById('motivoReapertura').value = '';
    contarMotivoReapertura();
    document.getElementById('reabrirFechaCorte').textContent = detFecha(ctrlCorte().fecha_corte);
    new bootstrap.Modal(document.getElementById('modalReabrirCorte')).show();
};

const confirmarReabrirCorte = async function (boton) {
    const motivo = document.getElementById('motivoReapertura').value.trim();
    if (!motivo) {
        document.getElementById('error-motivo').innerHTML = 'El motivo de la reapertura es obligatorio.';
        return;
    }
    if (boton) boton.disabled = true;

    const datos = new FormData();
    datos.append('idCorte', ctrlIdCorte);
    datos.append('motivo', motivo);

    const res = await ctrlEnviar('/deterioro-reabrir-corte', datos);
    if (boton) boton.disabled = false;

    if (res.res === 'sin-permiso') {
        detError('Sin permiso', 'Tu usuario no tiene permiso para reabrir un corte cerrado.');
        return;
    }
    ctrlCerrarModal('modalReabrirCorte');
    if (res.res !== 'ok') { detError(res.title || 'No se pudo reabrir', res.text); return; }

    Swal.fire({ title: res.title, text: res.text, icon: 'success', confirmButtonColor: 'rgb(65,110,195)' })
        .then(() => cargarControles());
};

/* --- Bitácora del corte --- */

const prepararBitacora = function () {
    const panel = document.getElementById('panelBitacora');
    if (!ctrlPermisos().auditar) {
        panel.style.display = 'none';
        return;
    }
    panel.style.display = '';
    if (panel.open) cargarBitacora(true);
};

/** El ancla de la bitácora tiene que desplegar el panel: plegado no muestra nada. */
const abrirBitacora = function () {
    document.getElementById('panelBitacora').open = true;
};

const ctrlValorFiltro = function (id) {
    const el = document.getElementById(id);
    return el ? el.value : '';
};

/** Carga perezosa: la bitácora no se consulta hasta que el panel se abre. */
const cargarBitacora = async function (forzar) {
    const panel = document.getElementById('panelBitacora');
    if (!panel.open || !ctrlPermisos().auditar) return;
    if (ctrlBitacoraCargada && !forzar) return;

    const datos = new FormData();
    datos.append('idCorte', ctrlIdCorte);
    datos.append('accion', ctrlValorFiltro('filtroAccionBit'));
    datos.append('idUsuario', ctrlValorFiltro('filtroUsuarioBit'));
    datos.append('desde', ctrlValorFiltro('filtroDesdeBit'));
    datos.append('hasta', ctrlValorFiltro('filtroHastaBit'));
    datos.append('busqueda', ctrlValorFiltro('filtroBusquedaBit'));

    $('#panelBitacora').preloader();
    const res = await makeOptionsFetch(`${globalUrl}/deterioro-bitacora-datos`, datos, 'post', ctrlToken());
    $('#panelBitacora').preloader('remove');

    if (res.res !== 'ok') { detError('No se pudo cargar la bitácora', res.text); return; }

    ctrlBitacoraCargada = true;
    poblarFiltrosBitacora(res.filtros || {});
    pintarBitacora(res);
};

const poblarFiltrosBitacora = function (filtros) {
    const accion = document.getElementById('filtroAccionBit');
    const usuario = document.getElementById('filtroUsuarioBit');
    const seleccionAccion = accion.value;
    const seleccionUsuario = usuario.value;

    accion.innerHTML = '<option value="">Todas</option>'
        + (filtros.acciones || []).map(a => '<option value="' + ctrlTexto(a.accion) + '">'
            + ctrlTexto(a.accion) + ' (' + detEntero(a.eventos) + ')</option>').join('');
    usuario.innerHTML = '<option value="">Todos</option>'
        + (filtros.usuarios || []).map(u => '<option value="' + ctrlTexto(u.id_usuario) + '">'
            + ctrlTexto(u.usuario || ('Usuario ' + u.id_usuario)) + '</option>').join('');

    accion.value = seleccionAccion;
    usuario.value = seleccionUsuario;
};

/**
 * El cierre guarda la foto completa en valor_nuevo: se muestra el arranque y el
 * resto queda a un clic, porque en la celda dejaría la tabla ilegible.
 */
const ctrlCeldaValor = function (valor) {
    const texto = String(valor === null || valor === undefined ? '' : valor);
    if (!texto) return '<td class="det-texto">' + detAusente('Sin valor registrado') + '</td>';
    if (texto.length <= 160) return '<td class="det-texto">' + ctrlTexto(texto) + '</td>';

    const id = 'valorBitacora' + (++ctrlSecuencia);
    return '<td class="det-texto"><span id="' + id + '">' + ctrlTexto(texto.substring(0, 160))
        + '…</span> <a href="#" onclick="verTodoBitacora(event,\'' + id + '\')">ver todo</a>'
        + '<span class="d-none" id="' + id + 'Todo">' + ctrlTexto(texto) + '</span></td>';
};

const verTodoBitacora = function (evento, id) {
    evento.preventDefault();
    document.getElementById(id).textContent = document.getElementById(id + 'Todo').textContent;
    evento.target.remove();
};

const pintarBitacora = function (res) {
    const eventos = res.eventos || [];
    const total = ctrlNum(res.total);

    document.getElementById('badgeBitacora').innerHTML =
        '<span class="det-badge det-inactivo">' + detEntero(total) + ' eventos</span>';

    document.getElementById('avisoBitacora').innerHTML = total > ctrlNum(res.limite)
        ? '<div class="det-aviso"><i class="fas fa-triangle-exclamation mt-1"></i><div>Mostrando los '
        + detEntero(res.limite) + ' eventos más recientes de ' + detEntero(total)
        + '. Acote con los filtros.</div></div>'
        : '';

    if (ctrlTablaBitacora) { ctrlTablaBitacora.destroy(); ctrlTablaBitacora = null; }

    document.getElementById('tbodyBitacora').innerHTML = eventos.map(e =>
        '<tr><td>' + ctrlFechaHora(e.fecha) + '</td>'
        + '<td><span class="det-badge det-inactivo">' + ctrlTexto(e.accion) + '</span></td>'
        + '<td>' + (e.id_operacion ? ctrlTexto(e.id_operacion)
            : detAusente('El evento no es de una operación')) + '</td>'
        + ctrlCeldaValor(e.valor_anterior)
        + ctrlCeldaValor(e.valor_nuevo)
        + '<td>' + ctrlTexto(e.usuario || ('Usuario ' + e.id_usuario)) + '</td>'
        + '<td>' + ctrlTexto(e.ip) + '</td></tr>').join('');

    document.getElementById('scrollBitacora').style.display = eventos.length ? '' : 'none';
    document.getElementById('vacioBitacora').innerHTML = eventos.length ? ''
        : detVacio('fa-clock-rotate-left', 'Sin eventos',
            'La bitácora no tiene eventos de este corte con los filtros aplicados.');
    if (!eventos.length) return;

    ctrlTablaBitacora = crearTablaDeterioro('tablaBitacora', { order: [[0, 'desc']] });
    detTooltips('#panelBitacora');
};
