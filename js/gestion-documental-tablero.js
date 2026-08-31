var COLOR_BARRA = '#2a78d6';
var COLOR_EJE = '#898781';
var COLOR_GRID = '#e1e0d9';
var COLOR_TEXTO = '#0b0b0b';

$(document).ready(function () {
    axios.post('/estadisticas-digitalizar').then(function (response) {
        pintarTablero(response.data.porAsesor || [], response.data.porMes || []);
    }).catch(function (error) {
        $('#divTableroDigitalizacion .container-fluid').prepend('<p class="text-center text-danger">Error al cargar las estadísticas.</p>');
        console.error(error);
    });
});

function pintarTablero(porAsesor, porMes) {
    // El driver de SQL Server devuelve COUNT(*) como texto, no como número: hay que convertirlo
    // explícitamente, si no "+" concatena strings en vez de sumar.
    var total = porAsesor.reduce(function (acc, item) { return acc + Number(item.total); }, 0);
    $('#totalPendientes').text(total);

    dibujarBarras('chartPorAsesor', porAsesor.map(function (i) { return i.Asesor || 'Sin asesor'; }), porAsesor.map(function (i) { return Number(i.total); }));
    dibujarBarras('chartPorMes', porMes.map(function (i) { return i.mes; }), porMes.map(function (i) { return Number(i.total); }));

    var tbody = $('#tbodyPorAsesor');
    tbody.empty();
    if (porAsesor.length === 0) {
        tbody.html('<tr><td colspan="2" class="text-center">No hay facturas pendientes de digitalizar.</td></tr>');
        return;
    }
    porAsesor.forEach(function (item) {
        tbody.append('<tr><td>' + (item.Asesor || 'Sin asesor') + '</td><td>' + Number(item.total) + '</td></tr>');
    });
}

function dibujarBarras(idCanvas, etiquetas, valores) {
    var ctx = document.getElementById(idCanvas).getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: etiquetas,
            datasets: [{
                data: valores,
                backgroundColor: COLOR_BARRA,
                borderRadius: 4,
                maxBarThickness: 40
            }]
        },
        options: {
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true }
            },
            scales: {
                x: {
                    ticks: { color: COLOR_EJE },
                    grid: { display: false }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: COLOR_EJE, precision: 0 },
                    grid: { color: COLOR_GRID }
                }
            }
        }
    });
}
