/** Pantalla de cortes: listar, crear, ejecutar y eliminar. */

const detToken = () => $('meta[name="csrf-token-deterioro"]').attr('content');

window.addEventListener('load', function () {
    cargarPeriodoOrigen();
    cargarCortes();
});

const cargarPeriodoOrigen = async function () {
    const res = await makeOptionsFetch(`${globalUrl}/deterioro-periodo-origen`, new FormData(), 'post', detToken());
    if (!res || !res.corte) return;
    const p = res.corte;
    const c = res.comparacion;
    document.getElementById('textoOrigen').innerHTML =
        'El origen tiene cargado el periodo <strong>' + p.IdAno + '-' + String(p.IdPeriodo).padStart(2, '0') +
        '</strong> con ' + detPesos(p.filas) + ' cuotas' +
        (c ? ', y el comparativo el <strong>' + c.IdAno + '-' + String(c.IdPeriodo).padStart(2, '0') + '</strong>.' : '.') +
        ' Estas tablas se sobrescriben cada mes; el corte guarda una copia propia.';
    document.getElementById('avisoOrigen').style.display = 'flex';
};

const cargarCortes = async function () {
    $('#divCortes').preloader();
    const res = await makeOptionsFetch(`${globalUrl}/deterioro-listar-cortes`, new FormData(), 'post', detToken());
    $('#divCortes').preloader('remove');
    if (!res || !res.cortes) return;

    let html = '';
    res.cortes.forEach(function (c) {
        const total = Number(c.cuadres_total);
        const falla = Number(c.cuadres_falla);
        const na = Number(c.cuadres_na);
        const enCero = total - falla - na;
        const naTexto = na ? '<span class="det-badge det-inactivo ms-1" title="Controles que no aplican a este corte">' + na + ' n/a</span>' : '';

        let cuadres;
        if (total === 0) {
            cuadres = '<span class="text-muted">—</span>';
        } else if (falla > 0) {
            cuadres = '<span class="det-badge det-rango-f">' + falla + ' de ' + (total - na) + ' con diferencia</span>' + naTexto;
        } else {
            cuadres = '<span class="det-badge det-rango-a">' + enCero + ' en cero</span>' + naTexto;
        }

        let acciones = '';
        if (c.estado !== 'CERRADO') {
            acciones += '<button class="btn btn-primary btn-sm py-0 px-2 me-1" title="Calcular" onclick="ejecutarCorte(' + c.id_corte + ')"><i class="fas fa-play"></i></button>';
            acciones += '<button class="btn btn-outline-danger btn-sm py-0 px-2 me-1" title="Eliminar" onclick="eliminarCorte(' + c.id_corte + ",'" + c.fecha_corte.substring(0, 10) + '\')"><i class="fas fa-trash"></i></button>';
        }
        if (c.estado !== 'ABIERTO') {
            acciones += '<a class="btn btn-light btn-sm py-0 px-2 me-1" title="Resumen" href="' + globalUrl + '/deterioro-resumen?corte=' + c.id_corte + '"><i class="fas fa-table"></i></a>';
            acciones += '<a class="btn btn-light btn-sm py-0 px-2 me-1" title="Detalle" href="' + globalUrl + '/deterioro-detalle-operaciones?corte=' + c.id_corte + '"><i class="fas fa-list"></i></a>';
            acciones += '<a class="btn btn-light btn-sm py-0 px-2" title="Controles y cierre" href="' + globalUrl + '/deterioro-controles?corte=' + c.id_corte + '"><i class="fas fa-lock"></i></a>';
        }

        // El badge por sí solo se pierde al escanear la grilla: la fila se
        // marca sobre su primera celda, que es donde el navegador la pinta.
        html += '<tr' + (c.cerrado_con_salvedad ? ' class="det-atencion"' : '') + '>'
            + '<td><strong>' + detFecha(c.fecha_corte) + '</strong></td>'
            + '<td>' + detEstadoCorte(c.estado, c.cerrado_con_salvedad) + '</td>'
            + '<td class="num">' + detPesos(c.filas_origen) + '</td>'
            + '<td class="num">' + detPesos(c.operaciones) + '</td>'
            + '<td class="num">' + detPesos(c.suma_capital_origen) + '</td>'
            + '<td class="num">' + detPesos(c.deterioro) + '</td>'
            + '<td>' + cuadres + '</td>'
            + '<td>' + (c.fecha_ejecucion ? String(c.fecha_ejecucion).substring(0, 16).replace('T', ' ') : '<span class="text-muted">—</span>') + '</td>'
            + '<td class="num">' + (c.duracion_ms ? (c.duracion_ms / 1000).toFixed(1) + ' s' : '') + '</td>'
            + '<td class="text-end">' + acciones + '</td>'
            + '</tr>';
    });
    document.getElementById('tbodyCortes').innerHTML = html
        || '<tr><td colspan="10" class="text-center text-muted py-4">Todavía no hay cortes. Crea el primero.</td></tr>';
    detTooltips('#tablaCortes');
};

const abrirNuevoCorte = function () {
    document.getElementById('error-fechaCorte').innerHTML = '';
    new bootstrap.Modal(document.getElementById('modalNuevoCorte')).show();
};

const crearCorte = async function () {
    const datos = new FormData();
    datos.append('fechaCorte', document.getElementById('fechaCorte').value);
    datos.append('fechaComparacion', document.getElementById('fechaComparacion').value);

    const res = await makeOptionsFetch(`${globalUrl}/deterioro-crear-corte`, datos, 'post', detToken());
    if (res.errors) { showErrors(res); return; }

    bootstrap.Modal.getInstance(document.getElementById('modalNuevoCorte')).hide();
    if (res.res === 'ok') {
        Swal.fire({ title: res.title, text: res.text, icon: 'success', confirmButtonText: 'Calcular ahora', showCancelButton: true, cancelButtonText: 'Después', confirmButtonColor: 'rgb(65,110,195)' })
            .then(v => v.isConfirmed ? ejecutarCorte(res.idCorte) : cargarCortes());
    } else {
        detError(res.title, res.text);
    }
};

const ejecutarCorte = async function (idCorte) {
    $('#divCortes').preloader();
    const datos = new FormData();
    datos.append('idCorte', idCorte);

    const res = await makeOptionsFetch(`${globalUrl}/deterioro-ejecutar-corte`, datos, 'post', detToken());
    $('#divCortes').preloader('remove');

    if (res.res !== 'ok') { detError(res.title || 'Error', res.text); return; }

    let pasos = '';
    res.pasos.forEach(p => {
        pasos += '<li><span>' + p.paso + '</span>'
            + (p.filas ? '<span class="text-muted">' + detPesos(p.filas) + ' filas</span>' : '')
            + '<span class="ms">' + detPesos(p.ms) + ' ms</span></li>';
    });
    const iconos = { ok: 'fa-check text-success', falla: 'fa-xmark text-danger', nota: 'fa-minus text-muted' };
    const conteo = { ok: 0, falla: 0, nota: 0 };
    res.cuadres.forEach(c => {
        const clase = detClaseCuadre(c.estado);
        conteo[clase]++;
        pasos += '<li><span><i class="fas ' + iconos[clase] + '"></i> ' + c.descripcion + detMotivoCuadre(c) + '</span>'
            + '<span class="ms">' + (c.diferencia === null || c.diferencia === undefined ? '—' : detMoneda2.format(c.diferencia)) + '</span></li>';
    });
    document.getElementById('listaPasos').innerHTML = pasos;
    document.getElementById('panelPasos').style.display = 'block';

    const plural = (n, singular, sufijo) => n + ' ' + (n === 1 ? singular : singular + 's') + ' ' + sufijo;
    const texto = 'Terminó en ' + (res.duracion_ms / 1000).toFixed(1) + ' segundos. '
        + (conteo.falla ? plural(conteo.falla, 'cuadre', 'con diferencia') : plural(conteo.ok, 'cuadre', 'en cero'))
        + (conteo.nota ? ' y ' + conteo.nota + (conteo.nota === 1 ? ' que no aplica' : ' que no aplican')
            + ' a este corte' : '') + '.';

    Swal.fire({ title: res.title, text: texto, icon: 'success', confirmButtonText: 'Ver resumen', showCancelButton: true, cancelButtonText: 'Cerrar', confirmButtonColor: 'rgb(65,110,195)' })
        .then(v => {
            if (v.isConfirmed) window.location.href = `${globalUrl}/deterioro-resumen?corte=${idCorte}`;
            else cargarCortes();
        });
};

const eliminarCorte = function (idCorte, fecha) {
    Swal.fire({
        title: '¿Eliminar el corte del ' + fecha + '?',
        text: 'Se borra el corte y todo su detalle. Esta acción no se puede deshacer.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d34848'
    }).then(async v => {
        if (!v.isConfirmed) return;
        const datos = new FormData();
        datos.append('idCorte', idCorte);
        const res = await makeOptionsFetch(`${globalUrl}/deterioro-eliminar-corte`, datos, 'post', detToken());
        if (res.res === 'ok') {
            document.getElementById('panelPasos').style.display = 'none';
            cargarCortes();
        } else {
            detError(res.title, res.text);
        }
    });
};
