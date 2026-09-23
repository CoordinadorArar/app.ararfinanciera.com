/** Contable contra fiscal: diferencia temporaria e impuesto diferido del corte. */

const detTokenC = () => $('meta[name="csrf-token-deterioro"]').attr('content');

const cfNum = v => Number(v || 0);
const cfClaves = ['ops', 'base', 'contable', 'acumAnt', 'ded', 'fiscal', 'deducible', 'imponible', 'activo', 'pasivo'];

let cfDatos = null;
let cfTarifa = 0.35;

window.addEventListener('load', function () {
    const idCorte = detCorteDeUrl();
    if (!idCorte) {
        window.location.href = `${globalUrl}/deterioro-cortes`;
        return;
    }
    document.getElementById('btnResumenCF').href = `${globalUrl}/deterioro-resumen?corte=${idCorte}`;
    document.getElementById('migaResumen').href = `${globalUrl}/deterioro-resumen?corte=${idCorte}`;
    document.getElementById('btnDetalleCF').href = `${globalUrl}/deterioro-detalle-operaciones?corte=${idCorte}&vista=diferido`;
    document.getElementById('btnEvolucionCF').href = `${globalUrl}/deterioro-evolucion?corte=${idCorte}`;
    document.getElementById('btnSuspensionesCF').href = `${globalUrl}/deterioro-suspensiones?corte=${idCorte}`;
    document.getElementById('btnConciliacionCF').href = `${globalUrl}/deterioro-conciliacion?corte=${idCorte}`;
    document.getElementById('btnControlesCF').href = `${globalUrl}/deterioro-controles?corte=${idCorte}`;
    cargarComparativo(idCorte);
});

const cargarComparativo = async function (idCorte) {
    $('#divComparativo').preloader();
    const datos = new FormData();
    datos.append('idCorte', idCorte);
    const res = await makeOptionsFetch(`${globalUrl}/deterioro-comparativo-datos`, datos, 'post', detTokenC());
    $('#divComparativo').preloader('remove');

    if (res.res !== 'ok') { detError('No se pudo cargar', res.text); return; }

    cfDatos = res;
    cfTarifa = cfNum(res.tarifaRenta) > 1 ? cfNum(res.tarifaRenta) / 100 : cfNum(res.tarifaRenta) || 0.35;

    document.getElementById('migaFecha').textContent = detFecha(res.corte.fecha_corte);
    document.getElementById('badgeAlcance').innerHTML = detBadgeFiscal(res.corte.fecha_corte);
    document.getElementById('avisoDiciembre').innerHTML = detAvisoFiscal(res.corte.fecha_corte, 'diferido');
    document.querySelectorAll('.det-tarifa-renta').forEach(e => { e.textContent = cfTexTarifa(); });

    pintarFranja();
    pintarPuente();
    pintarMovimiento();
    pintarReversion();
    pintarEvolucion();
    pintarCuadresCF(res.cuadres || []);
    detMenuExportar(res);
};

/** Normaliza la fila del resumen a los conceptos del puente, sin netear signos. */
const cfFila = function (r) {
    const temp = cfNum(r.diferencia_temporaria);
    const activo = Math.abs(cfNum(r.diferido_activo));
    const pasivo = Math.abs(cfNum(r.diferido_pasivo));
    const acumAnt = cfNum(r.fiscal_acumulado_anterior);
    const ded = cfNum(r.deduccion_fiscal_ano);

    return {
        producto: r.producto || '',
        rango: r.rango || r.rango_codigo || 'Corriente',
        esFiscal: r.deduce_fiscal === undefined ? (acumAnt > 0 || ded > 0) : Number(r.deduce_fiscal) === 1,
        ops: cfNum(r.operaciones),
        base: cfNum(r.base),
        contable: cfNum(r.deterioro_contable !== undefined ? r.deterioro_contable : r.deterioro),
        acumAnt: acumAnt,
        ded: ded,
        fiscal: cfNum(r.deterioro_fiscal_acumulado),
        deducible: pasivo && cfTarifa ? activo / cfTarifa : Math.max(temp, 0),
        imponible: activo && cfTarifa ? pasivo / cfTarifa : Math.max(-temp, 0),
        activo: activo,
        pasivo: pasivo
    };
};

const cfTotales = () => cfClaves.reduce((a, k) => (a[k] = 0, a), {});
const cfSumar = (d, f) => cfClaves.forEach(k => { d[k] += f[k]; });
const cfOrden = r => detOrdenRango[r] ?? 9;

const cfTotalGeneral = function () {
    const t = cfTotales();
    (cfDatos.resumen || []).map(cfFila).forEach(f => cfSumar(t, f));
    return t;
};

/* --- Franja-ecuación --- */

const cfTexTarifa = () => (cfTarifa * 100).toFixed(0) + ' %';

/** Los cortes anteriores a la fase 3 no tienen fiscal acumulado: no hay ecuación que pintar. */
const cfSinFase3 = r => cfNum(r.contable) > 0 && !cfNum(r.fiscal);

const pintarFranja = function () {
    const t = cfTotalGeneral();
    const m = cfDatos.movimiento || {};
    const contable = cfNum(m.contable) || t.contable;
    const fiscal = cfNum(m.fiscal) || t.fiscal;
    const franja = document.getElementById('franjaEcuacion');

    if (cfSinFase3({ contable: contable, fiscal: fiscal })) {
        franja.className = '';
        franja.innerHTML = '<div class="det-aviso info"><i class="fas fa-circle-info mt-1"></i><div>'
            + '<strong>Corte anterior a la fase 3.</strong> Registra deterioro contable por '
            + detMoneda2.format(contable) + ', pero no tiene deterioro fiscal acumulado calculado: '
            + 'sin ese dato no existe diferencia temporaria ni impuesto diferido que presentar. '
            + 'Vuelva a calcular el corte para obtener el comparativo contable&ndash;fiscal.</div></div>';
        return;
    }
    franja.className = 'det-puente';

    const temporaria = m.temporaria !== undefined ? cfNum(m.temporaria) : t.deducible - t.imponible;
    const diferido = m.diferido !== undefined ? cfNum(m.diferido) : t.activo - t.pasivo;

    const topadas = cfNum(cfDatos.corte.operaciones_topadas)
        || (cfDatos.resumen || []).reduce((a, r) => a + cfNum(r.operaciones_topadas), 0);
    const preliminar = detEsDiciembre(cfDatos.corte.fecha_corte) ? '' : ' preliminar';
    const tarifa = cfTexTarifa();
    const op = signo => '<span class="op"><i>' + signo + '</i><b>&darr;</b></span>';

    document.getElementById('franjaEcuacion').innerHTML =
        detTarjetaCifra('Deterioro contable', detPesos(contable, true), detEntero(t.ops) + ' operaciones')
        + op('&minus;')
        + detTarjetaCifra('Fiscal acumulado', detPesos(fiscal, true),
            topadas ? detEntero(topadas) + ' topadas por RN-09' : 'Deducción acumulada a la fecha')
        + op('=')
        + detTarjetaCifra('Diferencia temporaria', detPesos(Math.abs(temporaria), true),
            temporaria < 0 ? 'Diferencia imponible' : 'Diferencia deducible', 'destacada')
        + op('&times;' + tarifa)
        + detTarjetaCifra('Impuesto diferido', detPesos(Math.abs(diferido), true),
            (diferido < 0 ? 'Pasivo' : 'Activo') + ' por impuesto diferido'
            + '<span class="nota">' + (preliminar ? 'Preliminar · ' : '')
            + 'Tarifa de renta ' + tarifa + '</span>',
            'entregable' + preliminar);
};

/* --- Puente --- */

const cfCeldas = function (v, pct) {
    return '<td class="num">' + detEntero(v.ops) + '</td>'
        + '<td class="num">' + detPesos(v.base, true) + '</td>'
        + '<td class="num">' + (pct === null ? '' : detPorcentaje2(pct)) + '</td>'
        + '<td class="num">' + detPesos(v.contable, true) + '</td>'
        + '<td class="num">' + detPesos(v.acumAnt, true) + '</td>'
        + '<td class="num">' + detPesos(v.ded, true) + '</td>'
        + '<td class="num">' + detPesos(v.fiscal, true) + '</td>'
        + '<td class="num">' + detPesos(v.deducible, true) + '</td>'
        + '<td class="num">' + detPesos(v.imponible, true) + '</td>'
        + '<td class="num">' + detPesos(v.activo, true) + '</td>'
        + '<td class="num">' + detPesos(v.pasivo, true) + '</td>';
};

const cfPct = v => (v.base ? v.contable / v.base : 0);

/**
 * Los rangos que no deducen se cierran en una sola fila, pero conservan
 * operaciones, base y deterioro contable: ese deterioro es justamente el que
 * origina la diferencia temporaria deducible del grupo.
 */
const cfFilaSinFiscal = resto =>
    '<tr class="det-sin-fiscal"><td class="det-fija">Rangos sin deducción fiscal</td>'
    + cfCeldas(resto, null) + '</tr>';

const pintarPuente = function () {
    if (!cfDatos) return;
    const porProducto = document.getElementById('vistaProducto').checked;
    const filas = (cfDatos.resumen || []).map(cfFila);
    const columnas = document.querySelectorAll('#tablaPuente thead tr:last-child th').length;

    document.getElementById('thPrimera').textContent = porProducto ? 'Producto y rango' : 'Rango';

    const grupos = {};
    filas.forEach(function (f) {
        const clave = porProducto ? f.producto : '';
        const g = grupos[clave] || (grupos[clave] = { visibles: {}, resto: cfTotales(), total: cfTotales() });
        cfSumar(g.total, f);
        if (!f.esFiscal) { cfSumar(g.resto, f); return; }
        const v = g.visibles[f.rango] || (g.visibles[f.rango] = cfTotales());
        cfSumar(v, f);
    });

    let html = '';
    Object.keys(grupos).sort().forEach(function (clave) {
        const g = grupos[clave];
        Object.keys(g.visibles).sort((a, b) => cfOrden(a) - cfOrden(b)).forEach(function (rango) {
            const v = g.visibles[rango];
            html += '<tr><td class="det-fija">'
                + (clave ? clave + ' ' : '') + detBadgeRango(rango) + '</td>'
                + cfCeldas(v, cfPct(v)) + '</tr>';
        });
        if (g.resto.ops || g.resto.base) html += cfFilaSinFiscal(g.resto);
        if (clave) {
            html += '<tr class="det-total"><td class="det-fija">Total ' + clave + '</td>'
                + cfCeldas(g.total, cfPct(g.total)) + '</tr>';
        }
    });

    const t = cfTotalGeneral();
    document.getElementById('tbodyPuente').innerHTML = html
        || '<tr><td colspan="' + columnas + '" class="text-center text-muted py-4">El corte no tiene resultados.</td></tr>';
    document.getElementById('tfootPuente').innerHTML = html
        ? '<tr><td class="det-fija">TOTAL GENERAL</td>' + cfCeldas(t, cfPct(t)) + '</tr>'
        : '';

    const sinImponible = !t.imponible && !t.pasivo;
    document.getElementById('thImponible').classList.toggle('apagado', sinImponible);
    document.getElementById('thPasivo').classList.toggle('apagado', sinImponible);
    document.getElementById('notaImponible').innerHTML = sinImponible
        ? 'Este corte no presenta diferencias temporarias imponibles.'
        : '<a href="' + globalUrl + '/deterioro-detalle-operaciones?corte=' + cfDatos.corte.id_corte
        + '&vista=diferido&pasivo=1"><i class="fas fa-arrow-right-long"></i>&nbsp; '
        + 'Ver las operaciones con diferencia temporaria imponible</a>';

    detTooltips('#tablaPuente');
};

/* --- Movimiento del período --- */

const cfVariacion = function (actual, anterior, hay) {
    if (!hay) return '<td class="num">'
        + detAusente('No hay corte anterior comparable con qué calcular la variación') + '</td>';
    const d = actual - anterior;
    if (!d) return '<td class="num"><span class="det-cero">0</span></td>';
    return '<td class="num"><span class="det-var ' + (d > 0 ? 'sube' : 'baja') + '">'
        + (d > 0 ? '+' : '&minus;') + detMoneda2.format(Math.abs(d)) + '</span></td>';
};

const pintarMovimiento = function () {
    const m = cfDatos.movimiento || {};
    const hay = Number(m.tiene_anterior) === 1;
    const val = (a, b) => cfNum(m[a] !== undefined ? m[a] : m[b]);

    // Un corte anterior a la fase 3 no tiene fiscal acumulado: ese cero no es un dato.
    const sin = cfSinFase3({ contable: val('contable'), fiscal: val('fiscal') });

    const fila = function (etiqueta, actual, anterior, sinDato) {
        return '<tr><td>' + etiqueta + '</td>'
            + '<td class="num">' + (hay ? detPesos(anterior, true)
                : detAusente('Es el primer corte de la serie: no hay corte anterior con qué comparar')) + '</td>'
            + '<td class="num">' + (sinDato
                ? detAusente('El corte es anterior a la fase 3 y no tiene comparativo contable contra fiscal')
                : detPesos(actual, true)) + '</td>'
            + cfVariacion(actual, anterior, hay && !sinDato) + '</tr>';
    };

    const temporaria = val('temporaria');
    const diferido = val('diferido');

    document.getElementById('thCorteAnterior').innerHTML = hay && m.fecha_anterior
        ? 'Corte anterior ' + detFecha(m.fecha_anterior) : 'Corte anterior';
    document.getElementById('thCorteActual').textContent = 'Este corte ' + detFecha(cfDatos.corte.fecha_corte);

    document.getElementById('tbodyMovimiento').innerHTML =
        fila('Deterioro contable', val('contable'), val('contable_anterior', 'contable_ant'))
        + fila('Deterioro fiscal acumulado', val('fiscal'), val('fiscal_anterior', 'fiscal_ant'), sin)
        + fila('Deducción del año', val('deduccion_ano', 'deduccion'), val('deduccion_anterior', 'deduccion_ant'))
        + fila('Diferencia temporaria ' + (temporaria < 0 ? 'imponible' : 'deducible'),
            Math.abs(temporaria), Math.abs(val('temporaria_anterior', 'temporaria_ant')), sin)
        + fila('Impuesto diferido ' + (diferido < 0 ? 'pasivo' : 'activo'),
            Math.abs(diferido), Math.abs(val('diferido_anterior', 'diferido_ant')), sin);

    document.getElementById('notaMovimiento').textContent = hay ? ''
        : Number(m.anterior_sin_fase3) === 1
            ? 'El corte anterior es previo a la fase 3 y no tiene comparativo contable-fiscal.'
            : 'Primer corte de la serie: no hay período anterior con el cual comparar.';
};

/* --- Reversión proyectada --- */

const cfTramos = {
    'ANO_CORRIENTE': { clase: 'corriente', texto: 'Año corriente' },
    'ANO_SIGUIENTE': { clase: 'siguiente', texto: 'Año siguiente' },
    'POSTERIOR': { clase: 'posterior', texto: 'Años posteriores' },
    'SIN_PROYECCION': { clase: 'posterior', texto: 'Sin proyección' }
};

const cfVacio = (icono, titulo, frase) =>
    '<div class="det-vacio"><i class="fas ' + icono + '"></i>'
    + '<div class="tit">' + titulo + '</div>'
    + '<p class="det-subtitulo mb-0">' + frase + '</p></div>';

const pintarReversion = function () {
    const filas = (cfDatos.proyeccion || []).map(p => ({
        ano: p.ano_reversion_fiscal || p.ano,
        tramo: p.tramo || 'POSTERIOR',
        ops: cfNum(p.operaciones),
        temporaria: cfNum(p.temporaria),
        diferido: cfNum(p.diferido),
        magnitud: Math.abs(cfNum(p.temporaria))
    })).sort((a, b) => (a.ano || 9999) - (b.ano || 9999));

    if (!filas.some(f => f.magnitud || f.diferido)) {
        document.getElementById('reversionGrafico').innerHTML =
            cfVacio('fa-calendar-days', 'Sin reversión proyectada',
                'Aún no hay operaciones con reversión proyectada para este corte.');
        document.getElementById('reversionTabla').innerHTML = '';
        return;
    }

    const maximo = Math.max.apply(null, filas.map(f => f.magnitud));
    const usados = {};
    filas.forEach(f => { usados[f.tramo] = true; });

    let leyenda = '<div class="det-leyenda">';
    Object.keys(cfTramos).filter(k => usados[k]).forEach(function (k) {
        leyenda += '<span><i class="' + cfTramos[k].clase + '"></i>' + cfTramos[k].texto + '</span>';
    });
    leyenda += '</div>';

    let barras = '';
    filas.forEach(function (f) {
        const ancho = maximo && f.magnitud ? Math.max(f.magnitud / maximo * 100, 0) : 0;
        barras += '<div class="det-barra">'
            + '<span class="eti">' + (f.ano || 'Sin año') + '</span>'
            + '<span class="pista"><span class="relleno ' + cfTramos[f.tramo].clase + '" style="width:'
            + (f.magnitud > 0 ? 'max(2px,' + ancho.toFixed(2) + '%)' : '0') + '"></span></span>'
            + '<span class="cifra' + (f.temporaria < 0 ? ' det-var baja' : '') + '"'
            + (f.temporaria < 0 ? ' title="Diferencia temporaria imponible"' : '') + '>'
            + (f.temporaria < 0 ? '&minus;' + detMoneda2.format(Math.abs(f.temporaria)) : detPesos(f.temporaria, true))
            + '</span></div>';
    });

    document.getElementById('reversionGrafico').innerHTML = leyenda + barras;

    let cuerpo = '';
    const t = { temporaria: 0, diferido: 0 };
    filas.forEach(function (f) {
        t.temporaria += f.temporaria;
        t.diferido += f.diferido;
        cuerpo += '<tr><td>' + (f.ano || 'Sin proyección') + '</td>'
            + detCeldaSigno(f.temporaria, 'Diferencia temporaria imponible')
            + detCeldaSigno(f.diferido, 'Impuesto diferido pasivo') + '</tr>';
    });

    document.getElementById('reversionTabla').innerHTML =
        '<table class="det-tabla mt-3"><thead><tr><th>Año</th>'
        + '<th class="num">Diferencia que revierte</th>'
        + '<th class="num">Impuesto diferido</th></tr></thead>'
        + '<tbody>' + cuerpo + '</tbody>'
        + '<tfoot><tr><td>TOTAL</td>'
        + detCeldaSigno(t.temporaria, 'Diferencia temporaria imponible')
        + detCeldaSigno(t.diferido, 'Impuesto diferido pasivo') + '</tr></tfoot></table>';
};

/* --- Evolución --- */

const pintarEvolucion = function () {
    const serie = (cfDatos.evolucion || []).slice()
        .sort((a, b) => detFecha(a.fecha_corte).localeCompare(detFecha(b.fecha_corte)));

    // Los cortes previos a la fase 3 quedan en la tabla, pero no en la línea: su
    // cero no es un dato, es la ausencia del dato.
    const conDato = serie.filter(s => !cfSinFase3(s));
    const previos = serie.length - conDato.length;
    const valores = conDato.map(s => cfNum(s.temporaria));
    const ultimo = valores[valores.length - 1] || 0;
    const primero = valores[0] || 0;
    const delta = ultimo - primero;

    document.getElementById('evolucionCabecera').innerHTML = !conDato.length ? '' :
        '<p class="det-spark-cifra mb-1">' + detPesos(Math.abs(ultimo), true)
        + (conDato.length > 1
            ? ' <span class="det-var ' + (delta >= 0 ? 'sube' : 'baja') + '">'
            + (delta >= 0 ? '+' : '&minus;') + detMoneda2.format(Math.abs(delta))
            + '</span> <span class="det-subtitulo">contra el corte ' + detFecha(conDato[0].fecha_corte) + '</span>'
            : '') + '</p>';

    document.getElementById('evolucionGrafico').innerHTML = conDato.length >= 3
        ? detSpark(valores)
        : '<div class="det-aviso info"><i class="fas fa-circle-info mt-1"></i><div>La serie tiene '
        + conDato.length + (conDato.length === 1 ? ' corte' : ' cortes') + ' con comparativo contable&ndash;fiscal'
        + (previos ? ' y ' + previos + (previos === 1 ? ' corte anterior' : ' cortes anteriores')
            + ' a la fase 3, que no entran en la gráfica' : '')
        + '. La gráfica de evolución se habilita a partir del tercer corte.</div></div>';

    const guion = '<td class="num">'
        + detAusente('Corte anterior a la fase 3: sin comparativo contable contra fiscal') + '</td>';
    let html = '';
    serie.forEach(function (s, i) {
        const sin = cfSinFase3(s);
        const previo = serie[i - 1];
        const t = cfNum(s.temporaria);
        html += '<tr' + (sin ? ' class="det-sin-fiscal"' : '') + '><td>' + detFecha(s.fecha_corte)
            + (sin ? ' <span class="det-badge det-inactivo" '
                + 'title="Corte anterior a la fase 3: sin comparativo contable-fiscal">Sin fase 3</span>' : '')
            + '</td>'
            + '<td class="num">' + detPesos(s.contable, true) + '</td>'
            + (sin ? guion + guion + guion
                : '<td class="num">' + detPesos(s.fiscal, true) + '</td>'
                + '<td class="num">' + detPesos(Math.abs(t), true) + '</td>'
                + '<td class="num">' + detPesos(Math.abs(cfNum(s.diferido)), true) + '</td>')
            + cfVariacion(t, cfNum(previo && previo.temporaria), i > 0 && !sin && !cfSinFase3(previo))
            + '</tr>';
    });

    document.getElementById('tbodyEvolucion').innerHTML = html
        || '<tr><td colspan="6" class="text-center text-muted py-4">Sin cortes en la serie.</td></tr>';
};

const pintarCuadresCF = function (cuadres) {
    let html = '';
    cuadres.forEach(function (c) { html += detFilaCuadre(c); });
    document.getElementById('listaCuadresCF').innerHTML = html
        || '<p class="text-muted mb-0">Sin controles registrados.</p>';
};
