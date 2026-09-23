/** Resumen del corte: matriz producto x rango y semáforos de cuadre. */

const detTokenR = () => $('meta[name="csrf-token-deterioro"]').attr('content');

const ordenarResumen = filas => filas.sort((a, b) => a.producto === b.producto
    ? (detOrdenRango[a.rango] ?? 9) - (detOrdenRango[b.rango] ?? 9)
    : a.producto.localeCompare(b.producto));

const detTarjeta = (etiqueta, valor, destacada) =>
    '<div class="det-tarjeta' + (destacada ? ' destacada' : '') + '">'
    + '<span class="et">' + etiqueta + '</span><span class="vl">' + valor + '</span></div>';

window.addEventListener('load', function () {
    const idCorte = detCorteDeUrl();
    if (!idCorte) {
        window.location.href = `${globalUrl}/deterioro-cortes`;
        return;
    }
    document.getElementById('btnDetalle').href = `${globalUrl}/deterioro-detalle-operaciones?corte=${idCorte}`;
    document.getElementById('btnComparativo').href = `${globalUrl}/deterioro-contable-fiscal?corte=${idCorte}`;
    document.getElementById('btnEvolucion').href = `${globalUrl}/deterioro-evolucion?corte=${idCorte}`;
    document.getElementById('btnSuspensiones').href = `${globalUrl}/deterioro-suspensiones?corte=${idCorte}`;
    document.getElementById('btnConciliacion').href = `${globalUrl}/deterioro-conciliacion?corte=${idCorte}`;
    document.getElementById('btnControles').href = `${globalUrl}/deterioro-controles?corte=${idCorte}`;
    cargarResumen(idCorte);
});

const cargarResumen = async function (idCorte) {
    $('#divResumen').preloader();
    const datos = new FormData();
    datos.append('idCorte', idCorte);
    const res = await makeOptionsFetch(`${globalUrl}/deterioro-resumen-datos`, datos, 'post', detTokenR());
    $('#divResumen').preloader('remove');

    if (res.res !== 'ok') { detError('No se pudo cargar', res.text); return; }

    document.getElementById('tituloFecha').textContent = detFecha(res.corte.fecha_corte);
    document.getElementById('migaFecha').textContent = detFecha(res.corte.fecha_corte);
    pintarTarjetas(res);
    pintarMatriz(res.resumen);
    pintarFiscal(res.resumen, res.corte);
    pintarGeneral(res.resumen);
    pintarCuadres(res.cuadres);
    detMenuExportar(res);
};

const pintarTarjetas = function (res) {
    let capital = 0, interes = 0, base = 0, deterioro = 0, operaciones = 0;
    res.resumen.forEach(r => {
        capital += Number(r.capital_corriente) + Number(r.capital_vencido);
        interes += Number(r.interes_corriente) + Number(r.interes_vencido);
        base += Number(r.base);
        deterioro += Number(r.deterioro);
        operaciones += Number(r.operaciones);
    });

    document.getElementById('tarjetas').innerHTML =
        detTarjeta('Operaciones', detPesos(operaciones))
        + detTarjeta('Cuotas', detPesos(res.corte.filas_origen))
        + detTarjeta('Capital', detPesos(capital))
        + detTarjeta('Interés', detPesos(interes))
        + detTarjeta('Base de deterioro', detPesos(base))
        + detTarjeta('Deterioro contable', detMoneda2.format(deterioro), true);
};

const pintarMatriz = function (filas) {
    ordenarResumen(filas);

    const t = { ops: 0, cc: 0, cv: 0, ic: 0, iv: 0, base: 0, det: 0 };
    let html = '', productoActual = null, sub = null;

    const filaSubtotal = (producto, s) =>
        '<tr class="det-total"><td colspan="3">Total ' + producto + '</td>'
        + '<td class="num">' + detPesos(s.ops) + '</td>'
        + '<td class="num">' + detPesos(s.cc) + '</td>'
        + '<td class="num">' + detPesos(s.cv) + '</td>'
        + '<td class="num">' + detPesos(s.ic) + '</td>'
        + '<td class="num">' + detPesos(s.iv) + '</td>'
        + '<td class="num">' + detPesos(s.base) + '</td>'
        + '<td class="num">' + detMoneda2.format(s.det) + '</td></tr>';

    filas.forEach(function (r) {
        if (productoActual !== null && r.producto !== productoActual) {
            html += filaSubtotal(productoActual, sub);
        }
        if (r.producto !== productoActual) {
            productoActual = r.producto;
            sub = { ops: 0, cc: 0, cv: 0, ic: 0, iv: 0, base: 0, det: 0 };
        }
        const v = {
            ops: Number(r.operaciones), cc: Number(r.capital_corriente), cv: Number(r.capital_vencido),
            ic: Number(r.interes_corriente), iv: Number(r.interes_vencido),
            base: Number(r.base), det: Number(r.deterioro)
        };
        Object.keys(v).forEach(k => { sub[k] += v[k]; t[k] += v[k]; });

        html += '<tr>'
            + '<td>' + r.producto + '</td>'
            + '<td>' + detBadgeRango(r.rango) + '</td>'
            + '<td class="num">' + detPorcentaje(r.pct) + '</td>'
            + '<td class="num">' + detPesos(v.ops) + '</td>'
            + '<td class="num">' + detPesos(v.cc) + '</td>'
            + '<td class="num">' + detPesos(v.cv) + '</td>'
            + '<td class="num">' + detPesos(v.ic) + '</td>'
            + '<td class="num">' + detPesos(v.iv) + '</td>'
            + '<td class="num">' + detPesos(v.base) + '</td>'
            + '<td class="num">' + detMoneda2.format(v.det) + '</td>'
            + '</tr>';
    });
    if (productoActual !== null) html += filaSubtotal(productoActual, sub);

    document.getElementById('tbodyResumen').innerHTML = html
        || '<tr><td colspan="10" class="text-center text-muted py-4">El corte no tiene resultados.</td></tr>';

    document.getElementById('tfootResumen').innerHTML = productoActual === null ? '' :
        '<tr><td colspan="3">TOTAL GENERAL</td>'
        + '<td class="num">' + detPesos(t.ops) + '</td>'
        + '<td class="num">' + detPesos(t.cc) + '</td>'
        + '<td class="num">' + detPesos(t.cv) + '</td>'
        + '<td class="num">' + detPesos(t.ic) + '</td>'
        + '<td class="num">' + detPesos(t.iv) + '</td>'
        + '<td class="num">' + detPesos(t.base) + '</td>'
        + '<td class="num">' + detMoneda2.format(t.det) + '</td></tr>';
};

/**
 * Panel fiscal. Solo se listan los rangos que deducen según deduce_fiscal de la
 * paramétrica congelada del corte; los demás se cierran en una fila por
 * producto, de modo que operaciones y base sigan cuadrando contra la matriz
 * contable sin arrastrar quince filas en cero.
 *
 * Los totales de las cuatro columnas fiscales suman únicamente las filas
 * visibles, para que la columna cuadre con lo que se ve.
 */
const pintarFiscal = function (filas, corte) {
    ordenarResumen(filas);
    document.getElementById('badgeFiscal').innerHTML = detBadgeFiscal(corte.fecha_corte);
    document.getElementById('avisoFiscal').innerHTML = detAvisoFiscal(corte.fecha_corte);

    const t = { ops: 0, base: 0, ind: 0, acum: 0, top: 0, ded: 0 };
    let html = '', productoActual = null, sub = null, resto = null;

    const celdas = (s) =>
        '<td class="num">' + detPesos(s.ops) + '</td>'
        + '<td class="num">' + detPesos(s.base) + '</td>'
        + '<td class="num">' + detPesos(s.ind, true) + '</td>'
        + '<td class="num">' + detPesos(s.acum, true) + '</td>'
        + '<td class="num">' + detPesos(s.top, true) + '</td>'
        + detCeldaDeduccion(s.ded, s.ind, s.top);

    const cerrarProducto = (producto, s, r) =>
        (r.ops || r.base
            ? '<tr class="det-sin-fiscal"><td colspan="3">Rangos sin deducción fiscal</td>'
            + '<td class="num">' + detPesos(r.ops) + '</td>'
            + '<td class="num">' + detPesos(r.base) + '</td>'
            + ('<td class="num">' + detPesos(0) + '</td>').repeat(4)
            + '</tr>'
            : '')
        + '<tr class="det-total"><td colspan="3">Total ' + producto + '</td>' + celdas(s) + '</tr>';

    filas.forEach(function (r) {
        if (productoActual !== null && r.producto !== productoActual) {
            html += cerrarProducto(productoActual, sub, resto);
        }
        if (r.producto !== productoActual) {
            productoActual = r.producto;
            sub = { ops: 0, base: 0, ind: 0, acum: 0, top: 0, ded: 0 };
            resto = { ops: 0, base: 0 };
        }
        const esFiscal = Number(r.deduce_fiscal) === 1;
        const v = {
            ops: Number(r.operaciones), base: Number(r.base),
            ind: esFiscal ? Number(r.deterioro_fiscal_individual) : 0,
            acum: esFiscal ? Number(r.fiscal_acumulado_anterior) : 0,
            top: esFiscal ? Number(r.saldo_topado) : 0,
            ded: esFiscal ? Number(r.deduccion_fiscal_ano) : 0
        };
        Object.keys(v).forEach(k => { sub[k] += v[k]; t[k] += v[k]; });

        if (!esFiscal) {
            resto.ops += v.ops;
            resto.base += v.base;
            return;
        }
        html += '<tr><td>' + r.producto + '</td>'
            + '<td>' + detBadgeRango(r.rango) + '</td>'
            + '<td class="num">' + detPorcentaje(r.pct_fiscal) + '</td>'
            + celdas(v) + '</tr>';
    });
    if (productoActual !== null) html += cerrarProducto(productoActual, sub, resto);

    document.getElementById('tbodyFiscal').innerHTML = html
        || '<tr><td colspan="9" class="text-center text-muted py-4">El corte no tiene resultados.</td></tr>';

    document.getElementById('tfootFiscal').innerHTML = productoActual === null ? '' :
        '<tr><td colspan="3">TOTAL GENERAL</td>' + celdas(t) + '</tr>';

    document.getElementById('tarjetasFiscales').innerHTML =
        detTarjeta('Fiscal individual (33 %)', detPesos(t.ind, true))
        + detTarjeta('Acumulado años anteriores', detPesos(t.acum, true))
        + detTarjeta('Recorte por tope', detPesos(t.ind - t.ded, true))
        + detTarjeta('Deducción del año', detMoneda2.format(t.ded), true);
};

/** Método general: cálculo paralelo de referencia, sin subtotales por producto. */
const pintarGeneral = function (filas) {
    const porRango = {};
    filas.forEach(function (r) {
        const g = porRango[r.rango] || (porRango[r.rango] = { base: 0, gen: 0 });
        g.base += Number(r.base);
        g.gen += Number(r.deterioro_fiscal_general);
    });

    let html = '';
    Object.keys(porRango)
        .sort((a, b) => (detOrdenRango[a] ?? 9) - (detOrdenRango[b] ?? 9))
        .forEach(function (rango) {
            const g = porRango[rango];
            html += '<tr><td>' + detBadgeRango(rango) + '</td>'
                + '<td class="num">' + detPorcentaje(g.base ? g.gen / g.base : 0) + '</td>'
                + '<td class="num">' + detPesos(g.base) + '</td>'
                + '<td class="num">' + detPesos(g.gen, true) + '</td></tr>';
        });

    document.getElementById('tbodyGeneral').innerHTML = html
        || '<tr><td colspan="4" class="text-center text-muted py-4">El corte no tiene resultados.</td></tr>';
};

const pintarCuadres = function (cuadres) {
    let html = '';
    cuadres.forEach(c => { html += detFilaCuadre(c); });
    document.getElementById('listaCuadres').innerHTML = html
        || '<p class="text-muted mb-0">Sin controles registrados.</p>';
};
