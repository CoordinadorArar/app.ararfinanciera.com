/** Resumen del corte: matriz producto x rango y semáforos de cuadre. */

const detTokenR = () => $('meta[name="csrf-token-deterioro"]').attr('content');

window.addEventListener('load', function () {
    const idCorte = detCorteDeUrl();
    if (!idCorte) {
        window.location.href = `${globalUrl}/deterioro-cortes`;
        return;
    }
    document.getElementById('btnDetalle').href = `${globalUrl}/deterioro-detalle-operaciones?corte=${idCorte}`;
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
    pintarTarjetas(res);
    pintarMatriz(res.resumen);
    pintarCuadres(res.cuadres);
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

    const tarjeta = (etiqueta, valor, destacada) =>
        '<div class="det-tarjeta' + (destacada ? ' destacada' : '') + '">'
        + '<span class="et">' + etiqueta + '</span><span class="vl">' + valor + '</span></div>';

    document.getElementById('tarjetas').innerHTML =
        tarjeta('Operaciones', detPesos(operaciones))
        + tarjeta('Cuotas', detPesos(res.corte.filas_origen))
        + tarjeta('Capital', detPesos(capital))
        + tarjeta('Interés', detPesos(interes))
        + tarjeta('Base de deterioro', detPesos(base))
        + tarjeta('Deterioro contable', detMoneda2.format(deterioro), true);
};

const pintarMatriz = function (filas) {
    const orden = { 'Corriente': 0, 'A': 1, 'B': 2, 'C': 3, 'D': 4, 'E': 5, 'F': 6 };
    filas.sort((a, b) => a.producto === b.producto
        ? (orden[a.rango] ?? 9) - (orden[b.rango] ?? 9)
        : a.producto.localeCompare(b.producto));

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

const pintarCuadres = function (cuadres) {
    let html = '';
    cuadres.forEach(c => {
        html += '<div class="det-cuadre">'
            + '<span class="det-punto ' + (c.estado === 'OK' ? 'ok' : 'falla') + '"></span>'
            + '<span>' + c.descripcion + '</span>'
            + '<span class="dif">' + detMoneda2.format(c.diferencia) + '</span>'
            + '</div>';
    });
    document.getElementById('listaCuadres').innerHTML = html
        || '<p class="text-muted mb-0">Sin controles registrados.</p>';
};
