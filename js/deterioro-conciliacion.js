/** Conciliación con SIESA: cruce por operación y explicación de cada partida. */

const concToken = () => $('meta[name="csrf-token-deterioro"]').attr('content');

const concNum = v => Number(v || 0);

const concTexto = t => String(t === null || t === undefined ? '' : t)
    .replace(/[<>&"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));

/** Por gravedad, no por volumen: primero las dos fallas de cruce. */
const concPaneles = [
    { tipo: 'SOLO_FACTORING', sufijo: 'SoloFactoring', titulo: 'Operaciones sin saldo en SIESA' },
    { tipo: 'SOLO_SIESA', sufijo: 'SoloSiesa', titulo: 'Saldos de SIESA sin operación en el corte' },
    { tipo: 'DIFERENCIA', sufijo: 'Diferencia', titulo: 'Diferencias de saldo' }
];

const concEsDiferencia = panel => panel.tipo === 'DIFERENCIA';
const concColumnas = panel => concEsDiferencia(panel) ? 9 : 7;
const concColDif = panel => concEsDiferencia(panel) ? 4 : 3;

const concEtiquetaTipo = {
    'SOLO_FACTORING': 'Sin saldo en SIESA',
    'SOLO_SIESA': 'Sin operación en el corte',
    'DIFERENCIA': 'Diferencia de saldo'
};

const concEtiquetaEstado = { 'EXPLICADA': 'Explicada', 'EN_GESTION': 'En gestión' };

let concIdCorte = null;
let concDatos = null;
let concPuedeExplicar = true;
const concTablas = {};

window.addEventListener('load', function () {
    concIdCorte = detCorteDeUrl();
    if (!concIdCorte) {
        window.location.href = `${globalUrl}/deterioro-cortes`;
        return;
    }
    document.getElementById('migaResumen').href = `${globalUrl}/deterioro-resumen?corte=${concIdCorte}`;
    document.getElementById('btnResumenCo').href = `${globalUrl}/deterioro-resumen?corte=${concIdCorte}`;
    document.getElementById('btnDetalleCo').href = `${globalUrl}/deterioro-detalle-operaciones?corte=${concIdCorte}`;
    document.getElementById('btnComparativoCo').href = `${globalUrl}/deterioro-contable-fiscal?corte=${concIdCorte}`;
    document.getElementById('btnEvolucionCo').href = `${globalUrl}/deterioro-evolucion?corte=${concIdCorte}`;
    document.getElementById('btnSuspensionesCo').href = `${globalUrl}/deterioro-suspensiones?corte=${concIdCorte}`;
    document.getElementById('btnControlesCo').href = `${globalUrl}/deterioro-controles?corte=${concIdCorte}`;
    cargarConciliacion();
});

const concFiltroEstado = () => document.getElementById('filtroEstadoConc').value;

/**
 * Una sola lectura para toda la pantalla: las tarjetas, la barra y los badges
 * de las anclas salen del mismo corte que las tablas y no pueden quedar
 * desfasados de ellas.
 */
const cargarConciliacion = async function () {
    $('#divConciliacion').preloader();
    const datos = new FormData();
    datos.append('idCorte', concIdCorte);
    datos.append('tipo', '');
    datos.append('estado', concFiltroEstado());
    datos.append('busqueda', document.getElementById('filtroBusquedaConc').value);

    const res = await makeOptionsFetch(`${globalUrl}/deterioro-conciliacion-datos`, datos, 'post', concToken());
    $('#divConciliacion').preloader('remove');
    if (res.res !== 'ok') { detError('No se pudo cargar', res.text); return; }

    concDatos = res;
    concPuedeExplicar = !!(res.permisos && res.permisos.conciliar);

    document.getElementById('migaFecha').textContent = detFecha(res.corte.fecha_corte);
    document.getElementById('badgeEstado').innerHTML = detEstadoCorte(res.corte.estado);

    pintarAvisoConciliacion();
    pintarTarjetasConciliacion();
    pintarAnclas();
    concPaneles.forEach(pintarPanel);
    pintarCuadresConc();
    poblarEstadosExplicacion();
    concPaneles.forEach(panel => detTooltips('#panel' + panel.sufijo));
    detMenuExportar(res);
};

const concResumen = function (tipo) {
    return (concDatos.resumen || []).find(r => r.tipo === tipo)
        || { partidas: 0, diferencia: 0, diferencia_absoluta: 0, explicadas: 0, sin_explicar: 0 };
};

/* --- Consecuencia y avance --- */

/**
 * El número manda y la barra lo acompaña: un porcentaje no dice cuántas
 * partidas faltan por explicar.
 */
const pintarAvisoConciliacion = function () {
    const c = concDatos.cobertura || {};
    const partidas = concNum(c.partidas);
    const pendientes = concNum(c.sin_explicar);
    const explicadas = partidas - pendientes;

    const barra = !partidas ? '' : '<div class="det-progreso"><span class="pista">'
        + '<span class="relleno" style="width:' + Math.round(explicadas * 100 / partidas) + '%"></span></span>'
        + '<span class="cifra">' + detEntero(explicadas) + ' de ' + detEntero(partidas) + ' explicadas</span></div>';

    document.getElementById('avisoConciliacion').innerHTML = pendientes
        ? '<div class="det-aviso"><i class="fas fa-triangle-exclamation mt-1"></i><div>'
        + detEntero(pendientes) + (pendientes === 1 ? ' partida sin explicar.' : ' partidas sin explicar.')
        + ' El corte no se puede cerrar mientras queden partidas pendientes.' + barra + '</div></div>'
        : '<div class="det-aviso info"><i class="fas fa-circle-check mt-1"></i><div>'
        + (partidas
            ? 'Las ' + detEntero(partidas) + ' partidas de la conciliación están explicadas.'
            : 'El corte no tiene partidas de conciliación: cada operación cruza con SIESA al peso.')
        + ' El corte se puede cerrar.' + barra + '</div></div>';
};

/** El signo es dirección, no gravedad: los dos sentidos se rotulan igual. */
const concDireccion = function (valor) {
    const n = concNum(valor);
    if (!n) return 'Las dos fuentes suman el mismo saldo';
    return n < 0
        ? 'SIESA reporta menos saldo que el sistema de factoring'
        : 'SIESA reporta más saldo que el sistema de factoring';
};

const concDif = function (valor) {
    const n = concNum(valor);
    return '<span class="det-dif"><span class="sg">' + (n < 0 ? '&minus;' : '+') + '</span>'
        + detMoneda2.format(Math.abs(n)) + '</span>';
};

const pintarTarjetasConciliacion = function () {
    const c = concDatos.cobertura || {};
    const operaciones = concNum(c.operaciones);
    const cruzan = concNum(c.cruzan);
    const cobertura = operaciones ? (cruzan * 100 / operaciones).toFixed(1) : '0,0';

    document.getElementById('tarjetasConciliacion').innerHTML =
        detTarjetaCifra('Partidas por explicar', detEntero(c.sin_explicar),
            'de ' + detEntero(c.partidas) + ' partidas conciliatorias', 'destacada')
        + detTarjetaCifra('Diferencia neta', concDif(c.diferencia),
            concDireccion(c.diferencia) + ' &middot; ' + detMoneda2.format(concNum(c.diferencia_absoluta))
            + ' en valor absoluto')
        + detTarjetaCifra('Operaciones conciliadas', detEntero(c.conciliadas),
            'Coinciden al peso &mdash; sin gestión pendiente')
        + detTarjetaCifra('Cobertura del cruce', detEntero(cruzan) + ' de ' + detEntero(operaciones),
            String(cobertura).replace('.', ',') + ' % de las operaciones tiene saldo en SIESA');
};

/** Tres poblaciones apiladas: el badge cuenta lo pendiente, no el total. */
const pintarAnclas = function () {
    document.getElementById('anclasPaneles').innerHTML = concPaneles.map(function (panel) {
        const pendientes = concNum(concResumen(panel.tipo).sin_explicar);
        return '<a href="#panel' + panel.sufijo + '">' + panel.titulo + ' '
            + (pendientes
                ? '<span class="det-badge det-ambar">' + detEntero(pendientes) + ' por explicar</span>'
                : '<span class="det-badge det-estado-cerrado">Sin pendientes</span>') + '</a>';
    }).join('');
};

/* --- Paneles --- */

const concOrdenEstado = { 'PENDIENTE': 0, 'EN_GESTION': 1, 'EXPLICADA': 2 };

const concBadgeEstado = function (estado) {
    if (estado === 'EXPLICADA') return '<span class="det-badge det-estado-cerrado">Explicada</span>';
    if (estado === 'EN_GESTION') return '<span class="det-badge det-ambar">En gestión</span>';
    return '<span class="det-badge det-ambar">Por explicar</span>';
};

/**
 * En este panel una diferencia de cero no existe: la partida solo se genera
 * por encima de la tolerancia, y un 0,00 se leería como cuadre.
 */
const concCeldaDif = function (valor, orden) {
    const n = concNum(valor);
    if (!n) {
        return '<td class="num" data-order="0"><span class="det-badge det-rango-f" data-bs-toggle="tooltip" '
            + 'title="Las dos fuentes reportan el mismo saldo: una partida de conciliación sin diferencia '
            + 'es un defecto de datos">0,00 &middot; revisar</span></td>';
    }
    return '<td class="num" data-order="' + orden + '">' + concDif(n) + '</td>';
};

const concCeldaExplicacion = function (p) {
    const orden = ' data-order="' + (concOrdenEstado[p.estado] || 0) + '">';
    if (!p.explicacion) {
        return '<td class="det-texto"' + orden + detAusente('Sin explicación registrada') + '</td>';
    }
    return '<td class="det-texto"' + orden + concTexto(p.explicacion)
        + '<span class="det-subtitulo d-block">Usuario ' + concTexto(p.id_usuario)
        + ' &middot; ' + detFecha(p.fecha) + '</span></td>';
};

const concCeldaAccion = function (p) {
    if (!concPuedeExplicar) return '<td></td>';
    const explicada = p.estado === 'EXPLICADA';
    const clase = explicada ? 'btn-light' : 'btn-primary';
    const rotulo = explicada ? 'Ver / editar' : 'Explicar';

    if (concDatos.corte.estado === 'CERRADO') {
        return '<td class="text-end"><span data-bs-toggle="tooltip" '
            + 'title="El corte está cerrado: sus partidas ya no se pueden explicar">'
            + '<button class="btn ' + clase + ' btn-sm py-0 px-2" disabled>' + rotulo + '</button></span></td>';
    }
    return '<td class="text-end"><button class="btn ' + clase + ' btn-sm py-0 px-2" '
        + 'onclick="abrirExplicar(' + p.numero_operacion + ')">' + rotulo + '</button></td>';
};

/**
 * La columna del saldo que la población no tiene no se pinta con guiones: se
 * elimina y la ausencia queda declarada en el subtítulo del panel.
 */
const concCeldasSaldo = function (p, panel) {
    if (concEsDiferencia(panel)) return detCeldaNum(p.saldo_siesa) + detCeldaNum(p.saldo_factoring);
    return detCeldaNum(panel.tipo === 'SOLO_SIESA' ? p.saldo_siesa : p.saldo_factoring);
};

const concFila = function (p, panel) {
    const dif = concNum(p.diferencia);
    const pendiente = p.estado !== 'EXPLICADA';

    return '<tr' + (pendiente ? ' class="det-atencion"' : '') + '>'
        + '<td><strong>' + p.numero_operacion + '</strong></td>'
        + '<td>' + concTexto(p.cliente) + '<span class="det-subtitulo d-block">'
        + (p.nit ? concTexto(p.nit) : detAusente('El origen no reporta NIT para esta partida')) + '</span></td>'
        + concCeldasSaldo(p, panel)
        + concCeldaDif(dif, concEsDiferencia(panel) ? dif : Math.abs(dif))
        + '<td data-order="' + (concOrdenEstado[p.estado] || 0) + '">' + concBadgeEstado(p.estado) + '</td>'
        + concCeldaExplicacion(p)
        + concCeldaAccion(p)
        + (concEsDiferencia(panel)
            ? '<td class="num" data-order="' + Math.abs(dif) + '">' + detMoneda2.format(Math.abs(dif)) + '</td>' : '')
        + '</tr>';
};

/**
 * Los dos totales van juntos y salen del resumen del corte, no de lo paginado:
 * el neto solo esconde las compensaciones entre partidas de signo contrario.
 */
const concPie = function (panel) {
    const r = concResumen(panel.tipo);
    const fila = function (rotulo, valor) {
        let celdas = '';
        for (let i = 0; i < concColumnas(panel); i++) {
            celdas += i === 0 ? '<td>' + rotulo + '</td>'
                : i === concColDif(panel) ? '<td class="num">' + valor + '</td>' : '<td></td>';
        }
        return '<tr>' + celdas + '</tr>';
    };
    return fila('Suma algebraica', concDif(r.diferencia))
        + fila('Suma en valor absoluto', detMoneda2.format(concNum(r.diferencia_absoluta)));
};

const concVacio = function (panel) {
    return concFiltroEstado() === 'PENDIENTE'
        ? detVacio('fa-circle-check', 'Sin partidas por explicar',
            'Ninguna partida de esta población queda pendiente con el filtro aplicado.')
        : detVacio('fa-inbox', 'Sin partidas', 'No hay partidas de esta población para el filtro aplicado.');
};

const pintarPanel = function (panel) {
    const filas = (concDatos.partidas || []).filter(p => p.tipo === panel.tipo);
    const r = concResumen(panel.tipo);

    document.getElementById('chip' + panel.sufijo).innerHTML =
        '<span class="det-badge det-inactivo ms-2">' + detEntero(r.partidas) + ' en el corte</span>';

    if (concTablas[panel.tipo]) { concTablas[panel.tipo].destroy(); concTablas[panel.tipo] = null; }

    if (!filas.length) {
        document.getElementById('scroll' + panel.sufijo).style.display = 'none';
        document.getElementById('tbody' + panel.sufijo).innerHTML = '';
        document.getElementById('tfoot' + panel.sufijo).innerHTML = '';
        document.getElementById('vacio' + panel.sufijo).innerHTML = concVacio(panel);
        return;
    }
    document.getElementById('scroll' + panel.sufijo).style.display = '';
    document.getElementById('vacio' + panel.sufijo).innerHTML = '';
    document.getElementById('tbody' + panel.sufijo).innerHTML = filas.map(p => concFila(p, panel)).join('');
    document.getElementById('tfoot' + panel.sufijo).innerHTML = concPie(panel);

    const accion = concColumnas(panel) - (concEsDiferencia(panel) ? 2 : 1);
    concTablas[panel.tipo] = crearTablaDeterioro('tabla' + panel.sufijo, {
        order: [[concEsDiferencia(panel) ? 8 : concColDif(panel), 'desc']],
        columnDefs: concEsDiferencia(panel)
            ? [{ targets: [accion], orderable: false }, { targets: [8], visible: false, searchable: false }]
            : [{ targets: [accion], orderable: false }]
    });
};

const pintarCuadresConc = function () {
    const html = (concDatos.cuadres || [])
        .filter(c => c.codigo === 'C-CONCILIA' || String(c.codigo).indexOf('C-SIESA') === 0)
        .map(detFilaCuadre).join('');
    document.getElementById('listaCuadresConc').innerHTML = html
        || '<p class="text-muted mb-0">Sin controles registrados.</p>';
};

/* --- Modal Explicar partida --- */

const poblarEstadosExplicacion = function () {
    document.getElementById('explicarEstado').innerHTML = '<option value="">Seleccione…</option>'
        + (concDatos.estados || []).map(e =>
            '<option value="' + e + '">' + (concEtiquetaEstado[e] || e) + '</option>').join('');
};

const contarExplicacion = function () {
    document.getElementById('contadorExplicacion').textContent =
        document.getElementById('explicarTexto').value.length + ' de 500';
};

/** Nadie explica a ciegas: la cabecera repite la partida sin dejarla editar. */
const abrirExplicar = function (numeroOperacion) {
    const p = (concDatos.partidas || []).find(x => Number(x.numero_operacion) === Number(numeroOperacion));
    if (!p) return;

    ['estado', 'explicacion'].forEach(campo => { document.getElementById('error-' + campo).innerHTML = ''; });

    document.getElementById('explicarCabecera').innerHTML =
        '<div><strong>Operación ' + p.numero_operacion + '</strong> '
        + '<span class="det-badge det-inactivo">' + (concEtiquetaTipo[p.tipo] || p.tipo) + '</span></div>'
        + '<div class="det-subtitulo">' + concTexto(p.cliente) + '</div>'
        + '<div class="mt-1 det-subtitulo">Diferencia (SIESA &minus; Factoring)</div>'
        + '<div>' + concDif(p.diferencia) + '</div>';

    document.getElementById('explicarEstado').value = concEtiquetaEstado[p.estado] ? p.estado : '';
    document.getElementById('explicarTexto').value = p.explicacion || '';
    document.getElementById('explicarTexto').dataset.operacion = p.numero_operacion;
    contarExplicacion();
    new bootstrap.Modal(document.getElementById('modalExplicarPartida')).show();
};

const confirmarExplicar = function () {
    Swal.fire({
        title: '¿Guardar la explicación?',
        text: 'La partida queda explicada con tu usuario y la fecha en la bitácora del corte, y habilita el '
            + 'cierre cuando ninguna partida quede pendiente.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Guardar', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d9a520'
    }).then(v => { if (v.isConfirmed) guardarExplicacion(); });
};

const guardarExplicacion = async function () {
    const datos = new FormData();
    datos.append('idCorte', concIdCorte);
    datos.append('numeroOperacion', document.getElementById('explicarTexto').dataset.operacion);
    datos.append('estado', document.getElementById('explicarEstado').value);
    datos.append('explicacion', document.getElementById('explicarTexto').value);

    const res = await makeOptionsFetch(`${globalUrl}/deterioro-explicar-partida`, datos, 'post', concToken());
    if (res.errors) { showErrors(res); return; }

    bootstrap.Modal.getInstance(document.getElementById('modalExplicarPartida')).hide();
    if (res.res !== 'ok') { detError(res.title || 'Error', res.text); return; }

    // Recarga completa: mutar solo la fila dejaría mintiendo las tarjetas, la
    // barra de avance y los badges de las anclas.
    Swal.fire({ title: res.title, text: res.text, icon: 'success', confirmButtonColor: 'rgb(65,110,195)' })
        .then(() => cargarConciliacion());
};
