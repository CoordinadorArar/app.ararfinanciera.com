/** Evolución: gasto contable del período, descomposición del mes y serie histórica. */

const detTokenE = () => $('meta[name="csrf-token-deterioro"]').attr('content');

const evNum = v => Number(v || 0);
const evGuion = detAusente('El corte no tiene esta cifra determinada');

let evDatos = null;
let evTablaBajas = null;

window.addEventListener('load', function () {
    const idCorte = detCorteDeUrl();
    if (!idCorte) {
        window.location.href = `${globalUrl}/deterioro-cortes`;
        return;
    }
    document.getElementById('migaResumen').href = `${globalUrl}/deterioro-resumen?corte=${idCorte}`;
    document.getElementById('btnResumenEv').href = `${globalUrl}/deterioro-resumen?corte=${idCorte}`;
    document.getElementById('btnDetalleEv').href = `${globalUrl}/deterioro-detalle-operaciones?corte=${idCorte}`;
    document.getElementById('btnComparativoEv').href = `${globalUrl}/deterioro-contable-fiscal?corte=${idCorte}`;
    document.getElementById('btnSuspensionesEv').href = `${globalUrl}/deterioro-suspensiones?corte=${idCorte}`;
    document.getElementById('btnConciliacionEv').href = `${globalUrl}/deterioro-conciliacion?corte=${idCorte}`;
    document.getElementById('btnControlesEv').href = `${globalUrl}/deterioro-controles?corte=${idCorte}`;
    cargarEvolucion(idCorte);
});

const cargarEvolucion = async function (idCorte) {
    $('#divEvolucion').preloader();
    const datos = new FormData();
    datos.append('idCorte', idCorte);
    const res = await makeOptionsFetch(`${globalUrl}/deterioro-evolucion-datos`, datos, 'post', detTokenE());
    $('#divEvolucion').preloader('remove');

    if (res.res !== 'ok') { detError('No se pudo cargar', res.text); return; }

    evDatos = res;
    document.getElementById('migaFecha').textContent = detFecha(res.corte.fecha_corte);
    document.getElementById('badgeEstado').innerHTML = detEstadoCorte(res.corte.estado);
    document.getElementById('avisoFiscalEvolucion').innerHTML = detAvisoFiscal(res.corte.fecha_corte);

    pintarFranjaGasto();
    pintarCascada();
    pintarConciliacion();
    pintarComparativo();
    pintarSerie();
    pintarBajas();
    pintarCuadresEv();
    detMenuExportar(res);
};

/* --- Utilidades de signo --- */

/** El aumento va en azul y la liberación en terracota: el verde y el rojo dirían bueno o malo. */
const evSigno = function (valor) {
    const n = evNum(valor);
    if (!n) return '<span class="det-cero">0,00</span>';
    return '<span class="det-var ' + (n > 0 ? 'aumenta' : 'baja') + '">'
        + (n > 0 ? '+' : '&minus;') + detMoneda2.format(Math.abs(n)) + '</span>';
};

const evCeldaSigno = valor =>
    '<td class="num" data-order="' + evNum(valor) + '">' + evSigno(valor) + '</td>';

const evVacio = (icono, titulo, frase) =>
    '<div class="det-vacio"><i class="fas ' + icono + '"></i>'
    + '<div class="tit">' + titulo + '</div>'
    + '<p class="det-subtitulo mb-0">' + frase + '</p></div>';

const evLeyenda = () =>
    '<span><i class="aumenta"></i>Aumento del deterioro</span>'
    + '<span><i class="libera"></i>Liberación de deterioro</span>';

/** Enumera en castellano: «B, C, D y F». */
const evLista = function (valores) {
    if (valores.length < 2) return valores.join('');
    return valores.slice(0, -1).join(', ') + ' y ' + valores[valores.length - 1];
};

const evDescomposicion = () => evDatos.descomposicion || {};
const evHayAnterior = () => !!evDescomposicion().id_corte_anterior;

/* --- Franja de tres términos --- */

const evOp = signo => '<span class="op"><i>' + signo + '</i><b>&darr;</b></span>';

const evOperacionesCorte = function () {
    const fila = (evDatos.serie || []).find(s => Number(s.id_corte) === Number(evDatos.corte.id_corte));
    return fila ? evNum(fila.operaciones) : 0;
};

const pintarFranjaGasto = function () {
    const d = evDescomposicion();
    const franja = document.getElementById('franjaGasto');
    const sin = document.getElementById('sinAnterior');

    if (!evHayAnterior()) {
        franja.innerHTML = '';
        sin.innerHTML = evVacio('fa-scale-balanced', 'Primer corte de la serie',
            'El gasto del período resulta de comparar contra un corte anterior, y este corte no lo tiene.');
        return;
    }
    sin.innerHTML = '';

    const gasto = evNum(d.gasto);
    const pie = detEntero(evOperacionesCorte()) + ' operaciones al corte &middot; '
        + detEntero(d.operaciones_alta) + ' nuevas &middot; ' + detEntero(d.operaciones_baja) + ' bajas';

    franja.innerHTML =
        detTarjetaCifra('Deterioro al ' + detFecha(d.fecha_anterior), detPesos(d.anterior, true),
            'Cierre calculado del corte anterior')
        + evOp('+')
        + detTarjetaCifra('Gasto del período',
            (gasto < 0 ? '&minus;' : '+') + detMoneda2.format(Math.abs(gasto)), pie, 'entregable')
        + evOp('=')
        + detTarjetaCifra('Deterioro al ' + detFecha(evDatos.corte.fecha_corte), detPesos(d.actual, true),
            'Saldo de este corte');
};

/* --- Cascada --- */

const pintarCascada = function () {
    const d = evDescomposicion();
    const cascada = document.getElementById('cascada');
    const cierre = document.getElementById('cierreCascada');
    const fecha = detFecha(evDatos.corte.fecha_corte);

    if (!evHayAnterior()) {
        document.getElementById('leyendaCascada').innerHTML = '';
        cascada.innerHTML = evVacio('fa-diagram-project', 'Sin movimiento del mes',
            'La descomposición del movimiento se arma contra el corte anterior.');
        cierre.innerHTML = '';
        document.getElementById('tbodyDescomposicion').innerHTML = '';
        return;
    }

    const filas = [
        { eti: 'Deterioro al ' + detFecha(d.fecha_anterior), valor: evNum(d.anterior), ancla: true },
        { eti: 'Altas', pie: detEntero(d.operaciones_alta) + ' operaciones nuevas', valor: evNum(d.altas) },
        {
            eti: 'Variación de las que continúan',
            pie: detEntero(d.operaciones_variacion) + ' operaciones en los dos cortes',
            valor: evNum(d.variacion)
        },
        { eti: 'Bajas', pie: detEntero(d.operaciones_baja) + ' operaciones que salieron', valor: -evNum(d.bajas) },
        { eti: 'Deterioro al ' + fecha, valor: evNum(d.actual), ancla: true }
    ];

    // Los movimientos se escalan entre sí: contra el saldo quedarían ilegibles.
    const maximo = Math.max.apply(null, filas.filter(f => !f.ancla).map(f => Math.abs(f.valor)).concat([0]));

    let html = '';
    filas.forEach(function (f) {
        const ancho = maximo ? Math.abs(f.valor) / maximo * 50 : 0;
        const tramo = f.ancla || !f.valor ? '' :
            '<span class="tramo ' + (f.valor > 0 ? 'aumenta' : 'libera')
            + '" style="width:max(2px,' + ancho.toFixed(2) + '%)"></span>';

        html += '<div class="fila' + (f.ancla ? ' ancla' : '') + '">'
            + '<span class="eti">' + f.eti
            + (f.pie ? '<span class="det-subtitulo">' + f.pie + '</span>' : '') + '</span>'
            + '<span class="pista">' + (f.ancla ? '' : '<span class="cero"></span>' + tramo) + '</span>'
            + '<span class="cifra">' + (f.ancla ? detPesos(f.valor, true) : evSigno(f.valor)) + '</span>'
            + '</div>';
    });

    document.getElementById('leyendaCascada').innerHTML = evLeyenda();
    cascada.innerHTML = html;

    const control = d.control === undefined || d.control === null
        ? evNum(d.anterior) + evNum(d.altas) + evNum(d.variacion) - evNum(d.bajas)
        : evNum(d.control);
    const diferencia = control - evNum(d.actual);
    const cierra = Math.abs(diferencia) <= 0.01;

    cierre.innerHTML = '<div class="det-cierre"><div class="det-cuadre">'
        + '<span class="det-punto ' + (cierra ? 'ok' : 'falla') + '"></span>'
        + '<span>' + (cierra ? 'La descomposición cierra' : 'La descomposición no cierra') + '</span>'
        + '<span class="dif">diferencia ' + detMoneda2.format(diferencia) + '</span></div></div>';

    const fila = (etiqueta, ops, valor, ancla) =>
        '<tr' + (ancla ? ' class="det-total"' : '') + '><td>' + etiqueta + '</td>'
        + '<td class="num">' + (ops === null ? evGuion : detEntero(ops)) + '</td>'
        + (ancla ? '<td class="num">' + detPesos(valor, true) + '</td>' : evCeldaSigno(valor)) + '</tr>';

    document.getElementById('tbodyDescomposicion').innerHTML =
        fila('Deterioro al ' + detFecha(d.fecha_anterior), null, evNum(d.anterior), true)
        + fila('Altas', d.operaciones_alta, evNum(d.altas))
        + fila('Variación de las que continúan', d.operaciones_variacion, evNum(d.variacion))
        + fila('Bajas', d.operaciones_baja, -evNum(d.bajas))
        + fila('Deterioro al ' + fecha, evOperacionesCorte(), evNum(d.actual), true);
};

/* --- Conciliación con el libro --- */

const evCeldaLibro = function (valor, digitada) {
    if (valor === undefined || valor === null) return '<td class="num">' + evGuion + '</td>';
    if (!digitada) return '<td class="num">' + detPesos(valor, true) + '</td>';
    return '<td class="num det-topado">' + detPesos(valor, true)
        + '<span class="det-recorte">Digitada en el libro</span></td>';
};

const evFilaConciliacion = function (f) {
    const modulo = evNum(f.modulo);
    const tieneLibro = f.libro !== undefined && f.libro !== null;
    return '<tr><td>' + (f.concepto || f.rango || '') + '</td>'
        + '<td class="num">' + detPesos(modulo, true) + '</td>'
        + evCeldaLibro(f.libro, Number(f.digitada) === 1)
        + (tieneLibro ? evCeldaSigno(modulo - evNum(f.libro)) : '<td class="num">' + evGuion + '</td>')
        + '</tr>';
};

const pintarConciliacion = function () {
    const c = evDatos.conciliacion;
    const filas = Array.isArray(c) ? c : ((c && c.filas) || []);
    const rangos = (c && c.rangos) || [];
    const destino = document.getElementById('conciliacion');

    if (!filas.length && !rangos.length) {
        destino.innerHTML = evVacio('fa-book', 'Sin cifras del libro',
            'La conciliación se presenta cuando el corte tiene registradas las cifras del libro.');
        return;
    }

    const tabla = (titulo, primera, lista) =>
        '<div class="det-scroll' + (titulo ? ' mt-3' : '') + '">'
        + (titulo ? '<p class="det-subtitulo mb-2">' + titulo + '</p>' : '')
        + '<table class="det-tabla"><thead><tr><th>' + primera + '</th>'
        + '<th class="num">Módulo</th><th class="num">Libro</th><th class="num">Diferencia</th>'
        + '</tr></thead><tbody>' + lista.map(evFilaConciliacion).join('') + '</tbody></table></div>';

    const cuadran = rangos
        .filter(r => r.libro !== undefined && r.libro !== null
            && Math.abs(evNum(r.modulo) - evNum(r.libro)) <= 0.01)
        .map(r => r.rango || r.concepto);

    const total = filas.reduce(function (a, f) {
        const dif = f.libro === undefined || f.libro === null ? 0 : evNum(f.modulo) - evNum(f.libro);
        return Math.abs(dif) > Math.abs(a) ? dif : a;
    }, 0);

    const anterior = detFecha(evDescomposicion().fecha_anterior);
    const aviso = '<div class="det-aviso info"><i class="fas fa-circle-info mt-1"></i><div>'
        + 'La diferencia de ' + detMoneda2.format(Math.abs(total)) + ' no proviene del cálculo de este corte. '
        + 'El libro toma la cifra del mes anterior digitada a mano; el módulo toma el cierre calculado'
        + (anterior ? ' de ' + anterior : ' del corte anterior') + '. '
        + (cuadran.length ? 'En los rangos ' + evLista(cuadran) + ' ambos coinciden también en el mes anterior. ' : '')
        + 'El corte anterior del módulo se calculó desde un origen distinto al de este corte, de modo que la '
        + 'diferencia está pendiente de confirmar contra un cierre reproducible del mes anterior.'
        + '</div></div>';

    destino.innerHTML = (filas.length ? tabla('', 'Concepto', filas) : '')
        + (rangos.length ? tabla('Desglose del mes anterior por rango', 'Rango', rangos) : '')
        + '<div class="mt-3">' + aviso + '</div>';
};

/* --- Gasto del mes por rango o producto --- */

const evChipLibera = valor => evNum(valor) < 0
    ? '<span class="det-chip libera"><i class="fas fa-arrow-down"></i>Liberación</span>'
    : '';

const pintarComparativo = function () {
    if (!evDatos) return;

    // Sin corte anterior no hay gasto del mes: mostrarlo daría el saldo completo
    // como si fuera movimiento del período.
    if (!evHayAnterior()) {
        document.getElementById('vistasGasto').style.display = 'none';
        document.getElementById('scrollComparativo').style.display = 'none';
        document.getElementById('leyendaComparativo').innerHTML = '';
        document.getElementById('tbodyComparativo').innerHTML = '';
        document.getElementById('tfootComparativo').innerHTML = '';
        document.getElementById('notaComparativo').innerHTML = '';
        document.getElementById('vacioComparativo').innerHTML = evVacio('fa-scale-balanced',
            'Sin gasto del mes',
            'El gasto del mes se detalla contra un corte anterior, y este corte no lo tiene.');
        return;
    }
    document.getElementById('vacioComparativo').innerHTML = '';
    document.getElementById('vistasGasto').style.display = '';
    document.getElementById('scrollComparativo').style.display = '';

    const comparativo = evDatos.comparativo || {};
    const porProducto = document.getElementById('gastoProducto').checked;
    const filas = (porProducto ? comparativo.producto : comparativo.rango) || [];
    const dimension = porProducto ? 'Producto' : 'Rango';

    document.getElementById('thDimension').textContent = dimension;
    document.getElementById('leyendaComparativo').innerHTML = filas.length ? evLeyenda() : '';
    document.getElementById('thDeterioroAnt').textContent =
        'Deterioro al ' + detFecha(evDescomposicion().fecha_anterior);
    document.getElementById('thDeterioroAct').textContent = 'Deterioro al ' + detFecha(evDatos.corte.fecha_corte);

    if (!filas.length) {
        document.getElementById('tbodyComparativo').innerHTML =
            '<tr><td colspan="6">' + evVacio('fa-table-columns', 'Sin comparativo',
                'El corte no tiene resultados con los cuales comparar el período.') + '</td></tr>';
        document.getElementById('tfootComparativo').innerHTML = '';
        document.getElementById('notaComparativo').innerHTML = '';
        return;
    }

    const total = { operaciones: 0, deterioro_ant: 0, deterioro: 0, variacion: 0 };
    let html = '';
    filas.forEach(function (f) {
        Object.keys(total).forEach(k => { total[k] += evNum(f[k]); });
        html += '<tr><td class="det-fija">'
            + (porProducto ? (f.dimension || '') : detBadgeRango(f.dimension)) + '</td>'
            + '<td class="num">' + detEntero(f.operaciones) + '</td>'
            + '<td class="num">' + detPesos(f.deterioro_ant, true) + '</td>'
            + '<td class="num">' + detPesos(f.deterioro, true) + '</td>'
            + evCeldaSigno(f.variacion)
            + '<td>' + evChipLibera(f.variacion) + '</td></tr>';
    });

    document.getElementById('tbodyComparativo').innerHTML = html;
    document.getElementById('tfootComparativo').innerHTML =
        '<tr><td class="det-fija">TOTAL GENERAL</td>'
        + '<td class="num">' + detEntero(total.operaciones) + '</td>'
        + '<td class="num">' + detPesos(total.deterioro_ant, true) + '</td>'
        + '<td class="num">' + detPesos(total.deterioro, true) + '</td>'
        + evCeldaSigno(total.variacion) + '<td></td></tr>';

    const liberan = filas.filter(f => evNum(f.variacion) < 0);
    const aumentan = filas.filter(f => evNum(f.variacion) > 0);
    const etiqueta = (lista, plural) => porProducto
        ? evLista(lista.map(f => f.dimension || ''))
        : (plural ? 'Los rangos ' : 'El rango ') + evLista(lista.map(f => f.dimension));

    let nota = '';
    liberan.forEach(function (f) {
        nota += (porProducto ? f.dimension : 'El rango ' + f.dimension) + ' liberó '
            + detMoneda2.format(Math.abs(evNum(f.variacion))) + '. ';
    });
    if (aumentan.length) {
        nota += etiqueta(aumentan, aumentan.length > 1)
            + (aumentan.length > 1 ? ' aumentaron' : ' aumentó') + ' el deterioro.';
    }
    document.getElementById('notaComparativo').textContent = nota;
};

/* --- Serie histórica --- */

/** Un corte previo a la fase 3 tiene deterioro contable, pero no bloque fiscal. */
const evSinFase3 = s => evNum(s.contable) > 0 && !evNum(s.fiscal);

const evSerie = () => (evDatos.serie || []).slice()
    .sort((a, b) => detFecha(a.fecha_corte).localeCompare(detFecha(b.fecha_corte)));

const pintarSerie = function () {
    const serie = evSerie();
    const grafico = document.getElementById('serieGrafico');

    if (serie.length >= 3) {
        grafico.innerHTML = detSpark(serie.map(s => evNum(s.contable)));
    } else if (serie.length === 2) {
        // Con dos cortes hay comparación, no tendencia: se pintan las dos barras.
        const maximo = Math.max.apply(null, serie.map(s => evNum(s.contable)).concat([0]));
        const barras = serie.map(function (s) {
            const ancho = maximo ? evNum(s.contable) / maximo * 100 : 0;
            return '<div class="det-barra"><span class="eti">' + detFecha(s.fecha_corte) + '</span>'
                + '<span class="pista"><span class="relleno corriente" style="width:max(2px,'
                + ancho.toFixed(2) + '%)"></span></span>'
                + '<span class="cifra">' + detPesos(s.contable, true) + '</span></div>';
        }).join('');
        grafico.innerHTML = barras
            + '<p class="det-subtitulo mt-2 mb-3">Variación entre los dos cortes: '
            + evSigno(evNum(serie[1].contable) - evNum(serie[0].contable))
            + '. La línea de tendencia se habilita a partir del tercer corte.</p>';
    } else {
        grafico.innerHTML = evVacio('fa-chart-line', 'Aún no hay serie',
            'La evolución se construye a partir del segundo corte calculado.');
    }

    let html = '';
    serie.forEach(function (s) {
        const sin = evSinFase3(s);
        const gasto = s.gasto === null || s.gasto === undefined;
        html += '<tr' + (sin ? ' class="det-sin-fiscal"' : '') + '>'
            + '<td class="det-fija">' + detFecha(s.fecha_corte)
            + (sin ? ' <span class="det-badge det-inactivo" data-bs-toggle="tooltip" '
                + 'title="Corte anterior a la fase 3: no tiene bloque fiscal calculado">Sin fase 3</span>' : '')
            + '</td>'
            + '<td class="num">' + detEntero(s.operaciones) + '</td>'
            + '<td class="num">' + detPesos(s.base, true) + '</td>'
            + '<td class="num">' + detPesos(s.contable, true) + '</td>'
            + (gasto ? '<td class="num">' + evGuion + '</td>' : evCeldaSigno(s.gasto))
            + (sin ? '<td class="num">' + evGuion + '</td><td class="num">' + evGuion + '</td>'
                + '<td class="num">' + evGuion + '</td>'
                : '<td class="num">' + detPesos(s.fiscal, true) + '</td>'
                + '<td class="num">' + detPesos(Math.abs(evNum(s.temporaria)), true) + '</td>'
                + '<td class="num">' + detPesos(Math.abs(evNum(s.diferido)), true) + '</td>')
            + '</tr>';
    });

    document.getElementById('tbodySerie').innerHTML = html
        || '<tr><td colspan="8">' + evVacio('fa-chart-line', 'Aún no hay serie',
            'La evolución se construye a partir del segundo corte calculado.') + '</td></tr>';

    detTooltips('#panelSerie');
};

/* --- Bajas del período --- */

const pintarBajas = function () {
    const bajas = evDatos.bajas || [];
    const d = evDescomposicion();
    const contenido = document.getElementById('contenidoBajas');
    const vacio = document.getElementById('vacioBajas');

    document.getElementById('subtituloBajas').textContent = evHayAnterior()
        ? 'Operaciones presentes en el corte del ' + detFecha(d.fecha_anterior) + ' que ya no están en este corte'
        : 'Operaciones presentes en el corte anterior que ya no están en este corte';

    const liberado = bajas.reduce((a, b) => a + evNum(b.deterioro_contable), 0);
    document.getElementById('cifrasBajas').innerHTML = !bajas.length ? '' :
        '<div class="text-end"><span class="det-subtitulo d-block">Operaciones</span>'
        + '<span class="det-spark-cifra">' + detEntero(bajas.length) + '</span></div>'
        + '<div class="text-end"><span class="det-subtitulo d-block">Deterioro liberado</span>'
        + '<span class="det-spark-cifra">' + detMoneda2.format(liberado) + '</span></div>';

    if (evTablaBajas) { evTablaBajas.destroy(); evTablaBajas = null; }

    if (!bajas.length) {
        contenido.style.display = 'none';
        document.getElementById('tbodyBajas').innerHTML = '';
        vacio.innerHTML = evVacio('fa-inbox', 'Sin bajas en el período', evHayAnterior()
            ? 'Todas las operaciones del corte anterior siguen presentes en este corte.'
            : 'Las bajas se identifican contra un corte anterior, y este corte no lo tiene.');
        return;
    }
    contenido.style.display = '';
    vacio.innerHTML = '';

    document.getElementById('tbodyBajas').innerHTML = bajas.map(b =>
        '<tr><td><strong>' + b.id_operacion + '</strong></td>'
        + '<td title="' + (b.id_cliente || '') + '">' + (b.cliente || '') + '</td>'
        + '<td>' + (b.producto || '') + '</td>'
        + '<td data-order="' + (detOrdenRango[b.rango] ?? 9) + '">' + detBadgeRango(b.rango) + '</td>'
        + '<td class="num" data-order="' + evNum(b.dias_mora_operacion) + '">'
        + detEntero(b.dias_mora_operacion) + '</td>'
        + detCeldaNum(b.base_deterioro)
        + detCeldaNum(b.deterioro_contable)
        + '<td><span class="det-badge det-inactivo">Sin clasificar</span></td></tr>').join('');

    evTablaBajas = crearTablaDeterioro('tablaBajas', {
        order: [[6, 'desc']],
        columnDefs: [{ targets: [7], orderable: false }]
    });

    const diferencia = liberado - evNum(d.bajas);
    const cuadra = Math.abs(diferencia) <= 0.01;
    document.getElementById('cuadreBajas').innerHTML = '<div class="det-cierre"><div class="det-cuadre">'
        + '<span class="det-punto ' + (cuadra ? 'ok' : 'falla') + '"></span>'
        + '<span>Estas ' + detEntero(bajas.length) + ' operaciones suman el término Bajas de la '
        + 'descomposición: ' + detMoneda2.format(liberado) + '</span>'
        + '<span class="dif">diferencia ' + detMoneda2.format(diferencia) + '</span></div></div>';
};

/* --- Controles de cuadre --- */

const pintarCuadresEv = function () {
    const html = (evDatos.cuadres || []).map(detFilaCuadre).join('');

    document.getElementById('listaCuadresEv').innerHTML = html
        || '<p class="text-muted mb-0">Sin controles registrados.</p>';
};
