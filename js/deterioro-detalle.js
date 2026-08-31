/** Detalle por operación, con descenso a las cuotas de origen. */

const detTokenD = () => $('meta[name="csrf-token-deterioro"]').attr('content');
let detIdCorte = null;
let detTabla = null;

window.addEventListener('load', function () {
    detIdCorte = detCorteDeUrl();
    if (!detIdCorte) {
        window.location.href = `${globalUrl}/deterioro-cortes`;
        return;
    }
    document.getElementById('btnResumen').href = `${globalUrl}/deterioro-resumen?corte=${detIdCorte}`;
    cargarDetalle();
});

const cargarDetalle = async function () {
    $('#divDetalle').preloader();
    const datos = new FormData();
    datos.append('idCorte', detIdCorte);
    datos.append('producto', document.getElementById('filtroProducto').value);
    datos.append('rango', document.getElementById('filtroRango').value);
    datos.append('busqueda', document.getElementById('filtroBusqueda').value);
    datos.append('soloDeterioro', document.getElementById('filtroSoloDeterioro').checked ? '1' : '');

    const res = await makeOptionsFetch(`${globalUrl}/deterioro-detalle-datos`, datos, 'post', detTokenD());
    $('#divDetalle').preloader('remove');

    if (res.res !== 'ok') { detError('No se pudo cargar', res.text); return; }

    if (detTabla) { detTabla.destroy(); detTabla = null; }

    let html = '';
    res.operaciones.forEach(function (o) {
        html += '<tr>'
            + '<td><strong>' + o.id_operacion + '</strong></td>'
            + '<td title="' + (o.id_cliente || '') + '">' + (o.cliente || '') + '</td>'
            + '<td>' + (o.producto || '') + '</td>'
            + '<td data-order="' + o.rango + '">' + detBadgeRango(o.rango) + '</td>'
            + '<td class="num">' + (o.dias_mora_operacion || 0) + '</td>'
            + '<td class="num">' + o.cuotas + '</td>'
            + '<td class="num">' + detPesos(o.capital_vencido) + '</td>'
            + '<td class="num">' + detPesos(o.interes_vencido) + '</td>'
            + '<td class="num">' + detPesos(o.base_deterioro) + '</td>'
            + '<td class="num">' + detPorcentaje(o.pct_contable) + '</td>'
            + '<td class="num">' + detPesos(o.deterioro_contable, true) + '</td>'
            + '<td class="text-end"><button class="btn btn-light btn-sm py-0 px-2" title="Ver cuotas" '
            + 'onclick="verCuotas(' + o.id_operacion + ')"><i class="fas fa-magnifying-glass"></i></button></td>'
            + '</tr>';
    });
    document.getElementById('tbodyDetalle').innerHTML = html
        || '<tr><td colspan="12" class="text-center text-muted py-4">Sin operaciones para el filtro.</td></tr>';

    if (res.operaciones.length) {
        detTabla = crearTablaDeterioro('tablaDetalle', {
            order: [[10, 'desc']],
            columnDefs: [{ targets: [11], orderable: false }]
        });
    }
};

const verCuotas = async function (idOperacion) {
    const datos = new FormData();
    datos.append('idCorte', detIdCorte);
    datos.append('idOperacion', idOperacion);

    const res = await makeOptionsFetch(`${globalUrl}/deterioro-cuotas-operacion`, datos, 'post', detTokenD());
    if (res.res !== 'ok') { detError('No se pudo cargar', 'No fue posible traer las cuotas.'); return; }

    document.getElementById('tituloCuotas').textContent =
        'Cuotas de la operación ' + idOperacion + ' · ' + res.cuotas.length + ' registros';

    let html = '';
    res.cuotas.forEach(function (c) {
        html += '<tr>'
            + '<td class="num">' + c.id_cuota + '</td>'
            + '<td>' + detFecha(c.fec_inicial_corriente) + '</td>'
            + '<td>' + detFecha(c.fec_final_corriente) + '</td>'
            + '<td class="num">' + c.dias_mora_cuota + '</td>'
            + '<td>' + (c.estado_cuota === 'VENCIDA'
                ? '<span class="det-badge det-rango-f">Vencida</span>'
                : '<span class="det-badge det-rango-corriente">Corriente</span>') + '</td>'
            + '<td class="num">' + detPesos(c.saldo_capital) + '</td>'
            + '<td class="num">' + detPesos(c.saldo_intereses) + '</td>'
            + '<td class="num">' + detPesos(c.saldo_admon) + '</td>'
            + '<td class="num">' + detPesos(c.capital_vencido) + '</td>'
            + '<td class="num">' + detPesos(c.interes_vencido) + '</td>'
            + '<td class="num">' + detPesos(c.interes_mora) + '</td>'
            + '<td class="num">' + detPesos(c.capital_mes_anterior) + '</td>'
            + '</tr>';
    });
    document.getElementById('tbodyCuotas').innerHTML = html;

    new bootstrap.Modal(document.getElementById('modalCuotas')).show();
};
