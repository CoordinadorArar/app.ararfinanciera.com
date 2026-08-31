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

const detError = function (titulo, texto) {
    Swal.fire({ title: titulo, text: texto, icon: 'error', confirmButtonText: 'Entendido', confirmButtonColor: 'rgb(65,110,195)' });
};

/** Lee el id de corte de la barra de direcciones. */
const detCorteDeUrl = function () {
    return new URLSearchParams(window.location.search).get('corte');
};
