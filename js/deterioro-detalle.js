/** Detalle por operación, con descenso a las cuotas de origen. */

const detTokenD = () => $('meta[name="csrf-token-deterioro"]').attr('content');
let detIdCorte = null;
let detTabla = null;
let detFechaCorte = null;

/** Columnas que alterna el conmutador de vista. La base y la identidad son fijas. */
const detColContable = [5, 6, 7, 9, 10];
const detColFiscal = [11, 12, 13, 14];
const detColDiferido = [15, 16, 17, 18];

const detColVista = { contable: detColContable, fiscal: detColFiscal, diferido: detColDiferido };
const detOrdenVista = { contable: 10, fiscal: 14, diferido: 16 };

const detVista = () => document.getElementById('vistaDiferido').checked ? 'diferido'
    : document.getElementById('vistaFiscal').checked ? 'fiscal' : 'contable';

window.addEventListener('load', function () {
    detIdCorte = detCorteDeUrl();
    if (!detIdCorte) {
        window.location.href = `${globalUrl}/deterioro-cortes`;
        return;
    }
    document.getElementById('btnResumen').href = `${globalUrl}/deterioro-resumen?corte=${detIdCorte}`;

    // El comparativo entra directo a la vista de diferido, y puede pedir el pasivo.
    const parametros = new URLSearchParams(window.location.search);
    const vista = parametros.get('vista');
    if (vista === 'fiscal' || vista === 'diferido') {
        document.getElementById(vista === 'fiscal' ? 'vistaFiscal' : 'vistaDiferido').checked = true;
        if (vista === 'diferido' && parametros.get('pasivo') === '1') {
            document.getElementById('filtroSoloPasivo').checked = true;
        }
        detUiVista();
    }

    cargarCorte();
    cargarDetalle();
});

/** El endpoint del detalle no devuelve el corte y el año gravable se deriva de su fecha. */
const cargarCorte = async function () {
    const datos = new FormData();
    datos.append('idCorte', detIdCorte);
    const res = await makeOptionsFetch(`${globalUrl}/deterioro-resumen-datos`, datos, 'post', detTokenD());
    if (res.res !== 'ok') return;

    detFechaCorte = res.corte.fecha_corte;
    document.getElementById('tituloFecha').textContent = detFecha(detFechaCorte);
    document.getElementById('avisoFiscalDetalle').innerHTML = detAvisoFiscal(detFechaCorte);
    if (detVista() !== 'contable') {
        document.getElementById('badgeFiscalDetalle').innerHTML = detBadgeFiscal(detFechaCorte);
    }
};

/** Estado visual de la vista: aviso fiscal, filtros propios y etiquetas. */
const detUiVista = function () {
    const vista = detVista();
    const fiscal = vista === 'fiscal';

    document.getElementById('avisoFiscalDetalle').style.display = vista === 'contable' ? 'none' : '';
    document.getElementById('campoSoloTopadas').style.display = fiscal ? '' : 'none';
    document.getElementById('campoSoloPasivo').style.display = vista === 'diferido' ? '' : 'none';
    document.getElementById('etiquetaSoloDeterioro').textContent =
        fiscal ? 'Solo con deducción fiscal' : 'Solo con deterioro';
    document.getElementById('badgeFiscalDetalle').innerHTML =
        vista !== 'contable' && detFechaCorte ? detBadgeFiscal(detFechaCorte) : '';

    return vista;
};

const cambiarVista = function () {
    const vista = detUiVista();
    const fiscal = vista === 'fiscal';
    const diferido = vista === 'diferido';

    // Los filtros del interruptor cambian de significado con la vista: hay que releer.
    const releer = document.getElementById('filtroSoloDeterioro').checked
        || document.getElementById('filtroSoloTopadas').checked
        || document.getElementById('filtroSoloPasivo').checked;
    if (!fiscal) document.getElementById('filtroSoloTopadas').checked = false;
    if (!diferido) document.getElementById('filtroSoloPasivo').checked = false;
    if (releer) { cargarDetalle(); return; }

    detVisibilidadColumnas(vista);
    if (!detTabla) return;
    detTabla.columns.adjust().order([detOrdenVista[vista], 'desc']).draw();
};

/** Sin filas no hay DataTables y el encabezado se alterna sobre las celdas. */
const detVisibilidadColumnas = function (vista) {
    const celdas = detTabla ? null : document.querySelectorAll('#tablaDetalle thead th');
    Object.keys(detColVista).forEach(function (v) {
        const ver = v === vista;
        detColVista[v].forEach(function (i) {
            if (detTabla) detTabla.column(i).visible(ver, false);
            else celdas[i].style.display = ver ? '' : 'none';
        });
    });
};

const cargarDetalle = async function () {
    $('#divDetalle').preloader();
    const vista = detVista();
    const fiscal = vista === 'fiscal';
    const solo = document.getElementById('filtroSoloDeterioro').checked ? '1' : '';
    const datos = new FormData();
    datos.append('idCorte', detIdCorte);
    datos.append('producto', document.getElementById('filtroProducto').value);
    datos.append('rango', document.getElementById('filtroRango').value);
    datos.append('busqueda', document.getElementById('filtroBusqueda').value);
    datos.append(fiscal ? 'soloDeduccion' : 'soloDeterioro', solo);
    if (fiscal) datos.append('soloTopadas', document.getElementById('filtroSoloTopadas').checked ? '1' : '');
    if (vista === 'diferido') datos.append('soloPasivo', document.getElementById('filtroSoloPasivo').checked ? '1' : '');

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
            + detCeldaNum(o.deterioro_fiscal_individual)
            + detCeldaNum(o.fiscal_acumulado_anterior)
            + detCeldaNum(o.saldo_topado)
            + detCeldaDeduccion(o.deduccion_fiscal_ano, o.deterioro_fiscal_individual, o.saldo_topado)
            + detCeldaNum(o.deterioro_fiscal_acumulado)
            + detCeldaSigno(o.diferencia_temporaria, 'Diferencia temporaria imponible')
            + detCeldaSigno(o.impuesto_diferido_activo, 'Impuesto diferido pasivo')
            + '<td class="num">' + (o.ano_reversion_fiscal || '<span class="det-cero">&mdash;</span>') + '</td>'
            + '<td class="text-end"><button class="btn btn-light btn-sm py-0 px-2" title="Ver cuotas" '
            + 'onclick="verCuotas(' + o.id_operacion + ')"><i class="fas fa-magnifying-glass"></i></button></td>'
            + '</tr>';
    });
    document.getElementById('tbodyDetalle').innerHTML = html
        || '<tr><td colspan="20" class="text-center text-muted py-4">Sin operaciones para el filtro.</td></tr>';

    const celdas = document.querySelectorAll('#tablaDetalle thead th');
    detColContable.concat(detColFiscal, detColDiferido).forEach(i => { celdas[i].style.display = ''; });

    if (!res.operaciones.length) { detVisibilidadColumnas(vista); return; }

    detTabla = crearTablaDeterioro('tablaDetalle', {
        order: [[detOrdenVista[vista], 'desc']],
        columnDefs: [
            { targets: [19], orderable: false },
            { targets: detColContable, visible: vista === 'contable' },
            { targets: detColFiscal, visible: fiscal },
            { targets: detColDiferido, visible: vista === 'diferido' }
        ]
    });
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
