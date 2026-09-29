/** Detalle por operación, con descenso a las cuotas de origen. */

const detTokenD = () => $('meta[name="csrf-token-deterioro"]').attr('content');
let detIdCorte = null;
let detTabla = null;
let detFechaCorte = null;
let detCorteEstado = null;
let detEvaluadas = null;
let detProrrogaMedida = null;

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
    document.getElementById('migaResumen').href = `${globalUrl}/deterioro-resumen?corte=${detIdCorte}`;
    document.getElementById('btnEvolucionDet').href = `${globalUrl}/deterioro-evolucion?corte=${detIdCorte}`;
    document.getElementById('btnComparativoDet').href = `${globalUrl}/deterioro-contable-fiscal?corte=${detIdCorte}`;
    document.getElementById('btnSuspensionesDet').href = `${globalUrl}/deterioro-suspensiones?corte=${detIdCorte}`;
    document.getElementById('btnConciliacionDet').href = `${globalUrl}/deterioro-conciliacion?corte=${detIdCorte}`;
    document.getElementById('btnControlesDet').href = `${globalUrl}/deterioro-controles?corte=${detIdCorte}`;

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

    // El corte y la grilla se leen en paralelo: el menú de exportables espera al
    // conteo de la grilla para pintarse una sola vez y con su subtítulo real.
    detMenuExportar({ requiere: ['conteo'] });
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
    detCorteEstado = res.corte.estado;
    detAvisoRepetidas();
    detAvisoProrroga();
    document.getElementById('tituloFecha').textContent = detFecha(detFechaCorte);
    document.getElementById('migaFecha').textContent = detFecha(detFechaCorte);
    document.getElementById('avisoFiscalDetalle').innerHTML = detAvisoFiscal(detFechaCorte);
    detMenuExportar(res);
    if (detVista() !== 'contable') {
        document.getElementById('badgeFiscalDetalle').innerHTML = detBadgeFiscal(detFechaCorte);
    }
};

/**
 * Cuotas repetidas del origen. Sin la marca evaluada no se afirma nada: el
 * corte anterior a esta entrega no midió las repetidas, y eso no es cero.
 */
const detAvisoSinEvaluar = function () {
    const accion = detCorteEstado && detCorteEstado !== 'CERRADO'
        ? ' Vuelva a calcular el corte para evaluarlas. '
        + '<a href="' + globalUrl + '/deterioro-cortes">Ir a Cortes</a>'
        : '';
    return '<div class="det-aviso"><i class="fas fa-triangle-exclamation mt-1"></i>'
        + '<div>Este corte se calculó antes de que se midieran las cuotas repetidas. Sus totales no '
        + 'las excluyen y en el detalle no se marcan.' + accion + '</div></div>';
};

const detAvisoRepetidas = function () {
    document.getElementById('avisoRepetidasDetalle').innerHTML =
        detEvaluadas === false ? detAvisoSinEvaluar() : '';
};

/** El interruptor sólo existe si el corte trae repetidas; al ocultarse, se desmarca. */
const detUiRepetidas = function (operaciones) {
    if (operaciones.length) detEvaluadas = Number(operaciones[0].duplicadas_evaluadas) === 1;
    const filtro = document.getElementById('filtroSoloRepetidas');
    const hay = filtro.checked
        || (detEvaluadas && operaciones.some(o => Number(o.cuotas_duplicadas) > 0));
    document.getElementById('campoSoloRepetidas').style.display = hay ? '' : 'none';
    if (!hay) filtro.checked = false;
    detAvisoRepetidas();
};

const detAvisoProrroga = function () {
    document.getElementById('avisoProrrogaDetalle').innerHTML =
        detProrrogaMedida === false ? detAvisoSinProrroga(detCorteEstado) : '';
};

/**
 * Mismo trato que las repetidas: el interruptor sólo existe si el corte trae
 * prórroga. La bandera no se recalcula con una respuesta ya filtrada por
 * prórroga ni con una respuesta vacía: ahí conserva el último valor conocido
 * del corte, porque «ninguna fila» no distingue el corte sin prórroga del
 * anterior a la política.
 */
const detUiProrroga = function (operaciones) {
    const filtro = document.getElementById('filtroSoloProrroga');
    if (operaciones.length && !filtro.checked) {
        detProrrogaMedida = operaciones[0].interes_prorroga_siesa !== null
            && operaciones[0].interes_prorroga_siesa !== undefined;
    }
    const hay = filtro.checked
        || (detProrrogaMedida && operaciones.some(o => Number(o.interes_prorroga_siesa) > 0));
    document.getElementById('campoSoloProrroga').style.display = hay ? '' : 'none';
    if (!hay) filtro.checked = false;
    detAvisoProrroga();
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
    datos.append('soloDuplicadas', document.getElementById('filtroSoloRepetidas').checked ? '1' : '');
    datos.append('soloProrroga', document.getElementById('filtroSoloProrroga').checked ? '1' : '');

    const res = await makeOptionsFetch(`${globalUrl}/deterioro-detalle-datos`, datos, 'post', detTokenD());
    $('#divDetalle').preloader('remove');

    if (res.res !== 'ok') { detError('No se pudo cargar', res.text); return; }

    // El exportable del detalle sale con los filtros de esta lectura: el conteo
    // del menú y el nombre del archivo no pueden quedar desfasados de la tabla.
    detMenuExportar({ conteo: res.operaciones.length, filtros: () => datos });

    if (detTabla) { detTabla.destroy(); detTabla = null; }

    detUiRepetidas(res.operaciones);
    detUiProrroga(res.operaciones);

    let html = '';
    res.operaciones.forEach(function (o) {
        html += '<tr>'
            + '<td><strong>' + o.id_operacion + '</strong>' + detMarcaRepetidas(o) + '</td>'
            + '<td title="' + (o.id_cliente || '') + '">' + (o.cliente || '') + '</td>'
            + '<td>' + (o.producto || '') + '</td>'
            + '<td data-order="' + o.rango + '">' + detBadgeRango(o.rango) + '</td>'
            + '<td class="num">' + (o.dias_mora_operacion || 0) + '</td>'
            + '<td class="num">' + o.cuotas + '</td>'
            + detCeldaVencido(o.capital_vencido_siesa, o.capital_vencido, o.origen_saldo_siesa, o)
            + detCeldaVencido(o.interes_vencido_siesa, o.interes_vencido, o.origen_saldo_siesa, o)
            + detCeldaBase(o)
            + '<td class="num" data-order="' + Number(o.pct_contable || 0) + '">' + detPorcentaje(o.pct_contable) + '</td>'
            + '<td class="num" data-order="' + Number(o.deterioro_contable || 0) + '">' + detPesos(o.deterioro_contable, true) + '</td>'
            + detCeldaNum(o.deterioro_fiscal_individual)
            + detCeldaNum(o.fiscal_acumulado_anterior)
            + detCeldaNum(o.saldo_topado)
            + detCeldaDeduccion(o.deduccion_fiscal_ano, o.deterioro_fiscal_individual, o.saldo_topado)
            + detCeldaNum(o.deterioro_fiscal_acumulado)
            + detCeldaSigno(o.diferencia_temporaria, 'Diferencia temporaria imponible')
            + detCeldaSigno(o.impuesto_diferido_activo, 'Impuesto diferido pasivo')
            + '<td class="num" data-order="' + (o.ano_reversion_fiscal || -1) + '">'
            + (o.ano_reversion_fiscal
                || detAusente('La operación no tiene año de reversión proyectado')) + '</td>'
            + '<td class="text-end"><button class="btn btn-light btn-sm py-0 px-2" title="Ver cuotas" '
            + 'onclick="verCuotas(' + o.id_operacion + ',' + Number(o.interes_prorroga_siesa || 0)
            + ')"><i class="fas fa-magnifying-glass"></i></button></td>'
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
    // La marca puede caer en cualquier página: el tooltip se rehace en cada dibujo.
    detTabla.on('draw', () => detTooltips('#tablaDetalle'));
    detTooltips('#tablaDetalle');
};

/** Subtítulo en la celda de identidad: sin columna propia y visible en las tres vistas. */
const detMarcaRepetidas = function (o) {
    const n = Number(o.cuotas_duplicadas || 0);
    if (!detEvaluadas || n <= 0) return '';
    return '<span class="det-subtitulo d-block" data-bs-toggle="tooltip" '
        + 'title="Cuotas que factoring entrega repetidas. Se ven en el detalle pero no suman '
        + 'en los totales de la operación.">'
        + detEntero(n) + (n === 1 ? ' cuota repetida' : ' cuotas repetidas') + '</span>';
};

/** Sublínea en la celda de la base: cinco operaciones de dos mil no justifican columna. */
const detMarcaProrroga = function (o) {
    const n = Number(o.interes_prorroga_siesa || 0);
    if (!detProrrogaMedida || n <= 0) return '';
    return '<span class="det-suma" data-bs-toggle="tooltip" title="' + detTextoProrroga + '">+ '
        + detMoneda.format(n) + ' prórroga</span>';
};

const detOrigenSiesa = { TERCERO: 'saldo del tercero', OPE: 'operación SIESA', NOTA: 'nota contable' };

const detCeldaBase = function (o) {
    const congelada = o.base_congelada !== null && o.base_congelada !== undefined;
    const base = Number((congelada ? o.base_congelada : o.base_deterioro) || 0);
    const siesa = o.origen_base === 'SIESA';
    const fuente = !congelada ? '' : '<span class="det-fuente" data-bs-toggle="tooltip" title="'
        + (siesa
            ? 'Operación con intereses suspendidos: la base es capital vencido más interés vencido de SIESA más prórroga vencida. Base sin suspender (factoring): '
            : 'Operación con intereses suspendidos sin saldo atribuido en SIESA: la base es capital vencido de factoring más interés congelado más prórroga vencida. Base sin suspender: ')
        + detMoneda.format(Number(o.base_deterioro || 0)) + '.">'
        + (siesa ? '<b>SIESA</b> · suspendida' : 'suspendida · congelada') + '</span>';
    return '<td class="num" data-order="' + base + '">' + detPesos(base) + fuente + detMarcaProrroga(o) + '</td>';
};

const detCeldaVencido = function (siesa, factoring, origen, o) {
    if (siesa === null || siesa === undefined) {
        return '<td class="num" data-order="' + Number(factoring || 0) + '">' + detPesos(factoring) + '</td>';
    }
    const s = Number(siesa || 0), f = Number(factoring || 0), d = s - f;
    const texto = 'Valor vencido de SIESA' + (detOrigenSiesa[origen] ? ' (origen: ' + detOrigenSiesa[origen] + ')' : '') + '. '
        + (d !== 0
            ? 'Factoring: ' + detMoneda.format(f) + '. Diferencia SIESA − factoring: '
                + (d > 0 ? '+' : '−') + detMoneda.format(Math.abs(d)) + '.'
            : 'Coincide con factoring.')
        + (Number(o.suspendida) === 1 && o.origen_base === 'SIESA' ? ' La base usa este valor.' : ' La base se calcula con factoring.');
    return '<td class="num" data-order="' + s + '">' + detPesos(s)
        + '<span class="det-fuente" data-bs-toggle="tooltip" title="' + texto + '"><b>SIESA</b>'
        + (d !== 0 ? ' · fact. ' + detMoneda.format(f) : '') + '</span></td>';
};

const verCuotas = async function (idOperacion, prorroga) {
    const datos = new FormData();
    datos.append('idCorte', detIdCorte);
    datos.append('idOperacion', idOperacion);

    const res = await makeOptionsFetch(`${globalUrl}/deterioro-cuotas-operacion`, datos, 'post', detTokenD());
    if (res.res !== 'ok') { detError('No se pudo cargar', 'No fue posible traer las cuotas.'); return; }

    // El detalle es copia fiel del origen: las repetidas se muestran en su orden
    // y sin atenuar, pero fuera de los totales de la operación.
    const total = res.cuotas.length;
    const repetidas = res.cuotas.filter(c => c.duplicada_de).length;
    document.getElementById('tituloCuotas').textContent =
        'Cuotas de la operación ' + idOperacion + ' · ' + total + ' registros'
        + (repetidas ? ', ' + (total - repetidas) + ' en los totales' : '');
    // La prórroga no está en estas cuotas: sin el aviso, la suma de lo vencido
    // no da la base y el descenso parece descuadrado.
    const pr = Number(prorroga || 0);
    const avisoProrroga = pr <= 0 ? ''
        : '<div class="det-aviso info"><i class="fas fa-circle-info mt-1"></i>'
        + '<div>Esta operación tiene <strong>' + detMoneda.format(pr) + ' de interés de prórroga</strong> '
        + 'que reporta SIESA y que <strong>no está en estas cuotas</strong>: viene de otra fuente. '
        + 'La base de deterioro es la suma de lo vencido de estas cuotas <strong>más</strong> ese valor.'
        + '</div></div>';

    document.getElementById('avisoCuotas').innerHTML = (detEvaluadas === false
        ? detAvisoSinEvaluar()
        : repetidas
            ? '<div class="det-aviso info"><i class="fas fa-circle-info mt-1"></i>'
            + '<div>El detalle es copia fiel del sistema de factoring. <strong>' + repetidas
            + ' de estas ' + total + ' cuotas llegan repetidas</strong> y por eso se ven aquí, pero '
            + '<strong>no están contadas en los totales de la operación</strong>: el saldo se calcula con '
            + (total - repetidas) + ' cuotas. Cada repetida indica a qué cuota repite.</div></div>'
            : '') + avisoProrroga;

    let html = '';
    res.cuotas.forEach(function (c) {
        html += '<tr' + (c.duplicada_de ? ' class="det-repetida"' : '') + '>'
            + '<td class="num">' + c.id_detalle_operacion + '</td>'
            + '<td class="num">' + c.id_cuota + '</td>'
            + '<td>' + detFecha(c.fec_inicial_corriente) + '</td>'
            + '<td>' + detFecha(c.fec_final_corriente) + '</td>'
            + '<td class="num">' + c.dias_mora_cuota + '</td>'
            + '<td>' + (c.estado_cuota === 'VENCIDA'
                ? '<span class="det-badge det-rango-f">Vencida</span>'
                : '<span class="det-badge det-rango-corriente">Corriente</span>')
            + (c.duplicada_de
                ? ' <span class="det-badge det-ambar" data-bs-toggle="tooltip" title="Mismas fechas y '
                + 'saldos que la cuota ' + c.duplicada_de + '. Viene repetida del sistema de factoring '
                + 'y no se cuenta en los totales de la operación.">Repite la ' + c.duplicada_de
                + '</span>' : '') + '</td>'
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

    detTooltips('#modalCuotas');
    new bootstrap.Modal(document.getElementById('modalCuotas')).show();
};
