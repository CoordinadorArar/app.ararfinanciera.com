/** Utilidades compartidas por las pantallas de deterioro de cartera. */

const detMoneda = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
const detMoneda2 = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Pesos sin decimales. Los ceros se atenúan para que la vista respire. */
const detPesos = function (valor, conDecimales) {
    const n = Number(valor || 0);
    if (n === 0) return '<span class="det-cero">0</span>';
    return (conDecimales ? detMoneda2 : detMoneda).format(n);
};

/** Dato que no existe. No es cero: det-cero es para ceros confirmados. */
const detAusente = function (motivo) {
    return '<span class="det-ausente" data-bs-toggle="tooltip" title="' + motivo + '">&mdash;</span>';
};

const detPorcentaje = function (valor) {
    return (Number(valor || 0) * 100).toFixed(0) + '%';
};

/** Porcentaje con dos decimales: en el puente el redondeo a cero decimales falsea la tarifa. */
const detPorcentaje2 = function (valor) {
    return (Number(valor || 0) * 100).toFixed(2) + ' %';
};

/** Conteos: operaciones y cuotas no son moneda. */
const detEntero = function (valor) {
    return detMoneda.format(Math.round(Number(valor || 0)));
};

const detFecha = function (valor) {
    if (!valor) return '';
    return String(valor).substring(0, 10);
};

/**
 * DataTables para volúmenes reales.
 *
 * No se reutiliza crearTablaResponsiva() de funciones-globales.js porque fija
 * bSort:false, searching:false y 5 filas por página: sobre 2.100 operaciones
 * daría una tabla sin orden ni búsqueda.
 */
const crearTablaDeterioro = function (idTabla, opciones) {
    if ($.fn.dataTable.isDataTable('#' + idTabla)) {
        $('#' + idTabla).DataTable().destroy();
    }
    return $('#' + idTabla).DataTable(Object.assign({
        paging: true,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        searching: true,
        deferRender: true,
        autoWidth: false,
        language: {
            emptyTable: 'No hay información',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Sin registros',
            infoFiltered: '(filtrado de _MAX_ en total)',
            lengthMenu: 'Mostrar _MENU_',
            loadingRecords: 'Cargando...',
            processing: 'Procesando...',
            search: 'Buscar:',
            zeroRecords: 'Sin resultados',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        }
    }, opciones || {}));
};

/** Orden de presentación de los rangos de mora. */
const detOrdenRango = { 'Corriente': 0, 'A': 1, 'B': 2, 'C': 3, 'D': 4, 'E': 5, 'F': 6 };

/** Tarjeta de cifra con pie de contexto. */
const detTarjetaCifra = function (etiqueta, valor, pie, clases) {
    return '<div class="det-tarjeta' + (clases ? ' ' + clases : '') + '">'
        + '<span class="et">' + etiqueta + '</span>'
        + '<span class="vl">' + valor + '</span>'
        + (pie ? '<span class="pie">' + pie + '</span>' : '') + '</div>';
};

/** Las definiciones legales van en tooltip de Bootstrap, no en title nativo. */
const detTooltips = function (selector) {
    document.querySelectorAll((selector || '') + ' [data-bs-toggle="tooltip"]').forEach(function (el) {
        bootstrap.Tooltip.getInstance(el) || new bootstrap.Tooltip(el);
    });
};

/**
 * Prórroga de SIESA. Nulo no es cero: el corte se calculó antes de que el saldo
 * de prórroga vencido entrara a la base y afirmar un cero sería falso.
 */
const detTextoProrroga = 'Saldo de prórroga vencido que reporta SIESA. Se trata como interés y entra a la '
    + 'base de deterioro (RN-03). No es la prórroga del control C-1, que traslada cuotas al final. '
    + 'En rango F se deteriora al 100 %.';

const detAvisoSinProrroga = function (estado) {
    const accion = estado && estado !== 'CERRADO'
        ? ' Vuelva a calcular el corte para medirla. '
        + '<a href="' + globalUrl + '/deterioro-cortes">Ir a Cortes</a>'
        : '';
    return '<div class="det-aviso"><i class="fas fa-triangle-exclamation mt-1"></i>'
        + '<div>Este corte se calculó antes de que el saldo de prórroga vencido de SIESA entrara a la base '
        + 'de deterioro. Su base no lo incluye y por eso no se muestra la cifra.' + accion + '</div></div>';
};

/** Etiqueta de color por rango de mora. */
const detBadgeRango = function (rango) {
    const clases = {
        'Corriente': 'det-rango-corriente',
        'A': 'det-rango-a', 'B': 'det-rango-b', 'C': 'det-rango-c',
        'D': 'det-rango-d', 'E': 'det-rango-e', 'F': 'det-rango-f'
    };
    return '<span class="det-badge ' + (clases[rango] || '') + '">' + rango + '</span>';
};

/** La salvedad no es un estado del corte: es un atributo de su cierre. */
const detBadgeSalvedad = function () {
    return ' <span class="det-badge det-salvedad" data-bs-toggle="tooltip" '
        + 'title="El cierre se registró con requisitos sin resolver">Con salvedades</span>';
};

/** El segundo parámetro es opcional para no romper las llamadas existentes. */
const detEstadoCorte = function (estado, conSalvedades) {
    const clases = { 'ABIERTO': 'det-estado-abierto', 'CALCULADO': 'det-estado-calculado', 'CERRADO': 'det-estado-cerrado' };
    return '<span class="det-badge ' + (clases[estado] || '') + '">' + estado + '</span>'
        + (conSalvedades ? detBadgeSalvedad() : '');
};

/* --- Fiscal --- */

/**
 * El año gravable y la periodicidad salen del corte, no se digitan: la
 * deducción solo queda en firme con el corte de diciembre.
 */
const detEsDiciembre = fechaCorte => detFecha(fechaCorte).substring(5, 7) === '12';
const detAnoGravable = fechaCorte => detFecha(fechaCorte).substring(0, 4);

const detBadgeFiscal = function (fechaCorte) {
    return detEsDiciembre(fechaCorte)
        ? '<span class="det-badge det-estado-cerrado">Definitivo</span>'
        : '<span class="det-badge det-estado-abierto">Estimado</span>';
};

const detAvisoFiscal = function (fechaCorte, alcance) {
    const ano = detAnoGravable(fechaCorte);
    const extra = alcance === 'diferido'
        ? ' El impuesto diferido calculado sobre esta base es preliminar y no debe registrarse en libros.'
        : '';
    return detEsDiciembre(fechaCorte)
        ? '<div class="det-aviso info"><i class="fas fa-circle-check mt-1"></i>'
        + '<div>Corte de diciembre: cifra definitiva del año gravable ' + ano + '.</div></div>'
        : '<div class="det-aviso"><i class="fas fa-triangle-exclamation mt-1"></i>'
        + '<div>Cifra estimada. La deducción se determina sobre las obligaciones que subsistan al 31 de '
        + 'diciembre; solo el corte de diciembre produce la cifra definitiva del año gravable ' + ano + '.'
        + extra + '</div></div>';
};

/** Celda numérica ordenable: el formato con separador local no lo entiende DataTables. */
const detCeldaNum = function (valor) {
    return '<td class="num" data-order="' + Number(valor || 0) + '">' + detPesos(valor, true) + '</td>';
};

/** Celda de deducción del año. El recorte por tope se marca dentro de la celda. */
const detCeldaDeduccion = function (deduccion, individual, topado) {
    const ded = Number(deduccion || 0);
    const ind = Number(individual || 0);
    if (ded >= ind) return detCeldaNum(ded);

    return '<td class="num det-topado" data-order="' + ded + '">' + detPesos(ded, true)
        + '<span class="det-recorte" title="Recortado por el tope: individual ' + detMoneda2.format(ind)
        + ' − saldo topado ' + detMoneda2.format(Number(topado || 0)) + '">−'
        + detMoneda2.format(ind - ded) + '</span></td>';
};

/** Celda con signo: el negativo se muestra en rojo y con su etiqueta, nunca neteado. */
const detCeldaSigno = function (valor, titulo) {
    const n = Number(valor || 0);
    if (n >= 0) return detCeldaNum(n);
    return '<td class="num" data-order="' + n + '" title="' + titulo + '">'
        + '<span class="det-var baja">&minus;' + detMoneda2.format(Math.abs(n)) + '</span></td>';
};

/** Línea de tendencia sin ejes: la tabla que la acompaña es la fuente. */
const detSpark = function (valores) {
    const maximo = Math.max.apply(null, valores);
    const minimo = Math.min.apply(null, valores.concat([0]));
    const rango = (maximo - minimo) || 1;
    const x = i => (i * 100 / (valores.length - 1)).toFixed(2);
    const y = v => (44 - ((v - minimo) / rango) * 40).toFixed(2);

    const puntos = valores.map((v, i) => x(i) + ',' + y(v)).join(' ');
    const dots = valores.map(function (v, i) {
        const ultimo = i === valores.length - 1;
        return '<line x1="' + x(i) + '" y1="' + y(v) + '" x2="' + x(i) + '" y2="' + y(v) + '" '
            + 'stroke="' + (ultimo ? 'rgb(45,85,165)' : '#7d9fd8') + '" stroke-width="' + (ultimo ? 6 : 3)
            + '" stroke-linecap="round" vector-effect="non-scaling-stroke"></line>';
    }).join('');

    return '<svg class="det-spark" viewBox="0 0 100 48" preserveAspectRatio="none">'
        + '<polyline points="' + puntos + '" fill="none" stroke="rgb(45,85,165)" stroke-width="1.5" '
        + 'vector-effect="non-scaling-stroke"></polyline>' + dots + '</svg>';
};

/* --- Controles de cuadre --- */

/** Tres estados: OK cuadra, FALLA descuadra y N/A no aplica al corte. */
const detClaseCuadre = function (estado) {
    if (estado === 'OK') return 'ok';
    return estado === 'FALLA' ? 'falla' : 'nota';
};

/** Sin diferencia calculada va un guion: un 0,00 se leeria como cuadre en cero. */
const detDifCuadre = function (diferencia) {
    if (diferencia === null || diferencia === undefined || diferencia === '') {
        return '<span class="dif na">&mdash;</span>';
    }
    return '<span class="dif">' + detMoneda2.format(Number(diferencia)) + '</span>';
};

/** El motivo se rotula cuando la fila solo informa, para no leerse como falla. */
const detMotivoCuadre = function (c) {
    if (!c.motivo) return '';
    return '<span class="det-motivo">' + (c.informativo ? '<b>Informativo:</b> ' : '') + c.motivo + '</span>';
};

/**
 * Dos ejes independientes: el estado decide el punto y el badge, y la bandera
 * informativa distingue la fila que solo muestra la cifra de la que no aplica.
 */
const detFilaCuadre = function (c) {
    const noAplica = c.estado === 'N/A' && !c.informativo;
    return '<div class="det-cuadre"><span class="det-punto ' + detClaseCuadre(c.estado) + '"'
        + (noAplica ? ' title="No aplica a este corte"' : (c.informativo ? ' title="Cifra informativa"' : ''))
        + '></span>'
        + '<span>' + c.descripcion
        + (noAplica ? ' <span class="det-na">no aplica</span>' : '')
        + detMotivoCuadre(c) + '</span>'
        + detDifCuadre(c.diferencia) + '</div>';
};

/** Estado vacío: una tabla sin filas no dice por qué está vacía. */
const detVacio = function (icono, titulo, frase) {
    return '<div class="det-vacio"><i class="fas ' + icono + '"></i>'
        + '<div class="tit">' + titulo + '</div>'
        + '<p class="det-subtitulo mb-0">' + frase + '</p></div>';
};

const detError = function (titulo, texto) {
    Swal.fire({ title: titulo, text: texto, icon: 'error', confirmButtonText: 'Entendido', confirmButtonColor: 'rgb(65,110,195)' });
};

/** Lee el id de corte de la barra de direcciones. */
const detCorteDeUrl = function () {
    return new URLSearchParams(window.location.search).get('corte');
};
