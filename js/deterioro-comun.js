/** Utilidades compartidas por las pantallas de deterioro de cartera. */

const detMoneda = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
const detMoneda2 = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Pesos sin decimales. Los ceros se atenúan para que la vista respire. */
const detPesos = function (valor, conDecimales) {
    const n = Number(valor || 0);
    if (n === 0) return '<span class="det-cero">0</span>';
    return (conDecimales ? detMoneda2 : detMoneda).format(n);
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

/** Etiqueta de color por rango de mora. */
const detBadgeRango = function (rango) {
    const clases = {
        'Corriente': 'det-rango-corriente',
        'A': 'det-rango-a', 'B': 'det-rango-b', 'C': 'det-rango-c',
        'D': 'det-rango-d', 'E': 'det-rango-e', 'F': 'det-rango-f'
    };
    return '<span class="det-badge ' + (clases[rango] || '') + '">' + rango + '</span>';
};

const detEstadoCorte = function (estado) {
    const clases = { 'ABIERTO': 'det-estado-abierto', 'CALCULADO': 'det-estado-calculado', 'CERRADO': 'det-estado-cerrado' };
    return '<span class="det-badge ' + (clases[estado] || '') + '">' + estado + '</span>';
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

const detError = function (titulo, texto) {
    Swal.fire({ title: titulo, text: texto, icon: 'error', confirmButtonText: 'Entendido', confirmButtonColor: 'rgb(65,110,195)' });
};

/** Lee el id de corte de la barra de direcciones. */
const detCorteDeUrl = function () {
    return new URLSearchParams(window.location.search).get('corte');
};
