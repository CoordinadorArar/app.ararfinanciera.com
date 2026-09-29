/** Intereses suspendidos: marcas por causal legal y su efecto sobre la base de deterioro del corte. */

const susToken = () => $('meta[name="csrf-token-deterioro"]').attr('content');

const susNum = v => Number(v || 0);
const susGuionSinDeterminar = '&mdash;';

const susTexto = t => String(t === null || t === undefined ? '' : t)
    .replace(/[<>&"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));

const susTiposSoporte = ['application/pdf', 'image/jpeg', 'image/png'];
const susMaxSoporte = 20 * 1024 * 1024;

let susIdCorte = null;
let susDatos = null;
let susLista = null;
let susCausales = [];
let susTablaVigentes = null;
let susTablaCandidatas = null;
let susTablaHistorico = null;

window.addEventListener('load', function () {
    susIdCorte = detCorteDeUrl();
    if (!susIdCorte) {
        window.location.href = `${globalUrl}/deterioro-cortes`;
        return;
    }
    document.getElementById('migaResumen').href = `${globalUrl}/deterioro-resumen?corte=${susIdCorte}`;
    document.getElementById('btnResumenSus').href = `${globalUrl}/deterioro-resumen?corte=${susIdCorte}`;
    document.getElementById('btnDetalleSus').href = `${globalUrl}/deterioro-detalle-operaciones?corte=${susIdCorte}`;
    document.getElementById('btnComparativoSus').href = `${globalUrl}/deterioro-contable-fiscal?corte=${susIdCorte}`;
    document.getElementById('btnEvolucionSus').href = `${globalUrl}/deterioro-evolucion?corte=${susIdCorte}`;
    document.getElementById('btnConciliacionSus').href = `${globalUrl}/deterioro-conciliacion?corte=${susIdCorte}`;
    document.getElementById('btnControlesSus').href = `${globalUrl}/deterioro-controles?corte=${susIdCorte}`;
    cargarSuspensiones();
});

const cargarSuspensiones = async function () {
    $('#divSuspensiones').preloader();
    const datosCorte = new FormData();
    datosCorte.append('idCorte', susIdCorte);
    const datosLista = new FormData();
    datosLista.append('estado', '');
    datosLista.append('busqueda', '');

    const [resCorte, resLista] = await Promise.all([
        makeOptionsFetch(`${globalUrl}/deterioro-suspensiones-corte`, datosCorte, 'post', susToken()),
        makeOptionsFetch(`${globalUrl}/deterioro-suspensiones-datos`, datosLista, 'post', susToken())
    ]);
    $('#divSuspensiones').preloader('remove');

    if (resCorte.res !== 'ok') { detError('No se pudo cargar', resCorte.text); return; }
    if (resLista.res !== 'ok') { detError('No se pudo cargar', resLista.text); return; }

    susDatos = resCorte;
    susLista = resLista;
    susCausales = resLista.causales || [];

    document.getElementById('migaFecha').textContent = detFecha(resCorte.corte.fecha_corte);
    document.getElementById('badgeEstado').innerHTML = detEstadoCorte(resCorte.corte.estado);

    poblarCausales();
    pintarTarjetasEfecto();
    pintarVigentes();
    pintarCandidatas();
    pintarHistorico();
    pintarCuadresSus();
    detMenuExportar(resCorte);
};

/* --- Efecto sobre este corte --- */

const pintarTarjetasEfecto = function () {
    const filas = susDatos.suspendidas || [];
    let congelado = 0, reduccion = 0, noFacturado = 0;
    filas.forEach(function (f) {
        congelado += susNum(f.interes_congelado);
        reduccion += susNum(f.reduccion_base);
        noFacturado += susNum(f.interes_no_facturado);
    });

    document.getElementById('tarjetasEfecto').innerHTML =
        detTarjetaCifra('Operaciones suspendidas', detEntero(filas.length), 'Con evento aplicado a este corte')
        + detTarjetaCifra('Interés congelado', detMoneda2.format(congelado), 'Interés al evento; entra a la base solo sin saldo en SIESA')
        + detTarjetaCifra('Reducción de la base de deterioro', detMoneda2.format(reduccion), 'Base sin suspender menos base efectiva (puede ser negativa)')
        + detTarjetaCifra('Interés no facturado en FACTORING', detMoneda2.format(noFacturado),
            'Informativo — no forma parte de la base de deterioro');
};

/* --- Marcas vigentes --- */

const susIdsAplicadas = () => new Set((susDatos.suspendidas || []).map(f => Number(f.id_operacion)));

const susSinCongelar = valor => valor === null || valor === undefined || valor === '';

const susCeldaCongelado = function (valor) {
    if (susSinCongelar(valor)) {
        return '<td class="num det-sin-congelar" data-order="-1">' + susGuionSinDeterminar
            + '<span class="det-recorte">Sin congelar — no se pudo determinar el interés a la fecha del evento</span></td>';
    }
    return detCeldaNum(valor);
};

const susBadgeEstado = estado => estado === 'LEVANTADA'
    ? '<span class="det-badge det-inactivo">Levantada</span>'
    : '<span class="det-badge det-estado-vigente">Vigente</span>';

/** El clip sólo se pinta con soporte real: la columna soporte trae texto libre del cargue inicial. */
const susClip = function (s) {
    if (!susNum(s.tiene_soporte) || !s.soporte_url) return '';
    return '<a class="det-soporte" href="' + susTexto(s.soporte_url) + '" target="_blank" rel="noopener" '
        + 'data-bs-toggle="tooltip" title="Ver el soporte: ' + susTexto(s.soporte_nombre || 'archivo adjunto') + '">'
        + '<i class="fas fa-paperclip"></i></a>';
};

const pintarVigentes = function () {
    const vigentes = (susLista.suspensiones || []).filter(s => s.estado !== 'LEVANTADA');
    const aplicadas = susIdsAplicadas();
    const sinCongelar = vigentes.filter(s => susSinCongelar(s.interes_congelado));

    document.getElementById('chipSinCongelar').innerHTML = sinCongelar.length
        ? '<span class="det-badge det-ambar ms-2">' + sinCongelar.length + ' sin congelar</span>' : '';

    if (susTablaVigentes) { susTablaVigentes.destroy(); susTablaVigentes = null; }

    if (!vigentes.length) {
        document.getElementById('scrollVigentes').style.display = 'none';
        document.getElementById('tbodyVigentes').innerHTML = '';
        document.getElementById('vacioVigentes').innerHTML = detVacio('fa-circle-pause', 'Sin marcas vigentes',
            'Todavía no hay operaciones con interés suspendido en el sistema.');
        return;
    }
    document.getElementById('scrollVigentes').style.display = '';
    document.getElementById('vacioVigentes').innerHTML = '';

    document.getElementById('tbodyVigentes').innerHTML = vigentes.map(function (s) {
        const aplica = aplicadas.has(Number(s.id_operacion));
        return '<tr><td><strong>' + s.id_operacion + '</strong>'
            + (aplica ? '' : ' <span class="det-badge det-inactivo" data-bs-toggle="tooltip" '
                + 'title="El evento no afecta la fecha de este corte">No aplica a este corte</span>')
            + '</td>'
            + '<td title="' + (s.id_cliente || '') + '">' + (s.cliente || '') + '</td>'
            + '<td>' + (s.causal || '') + susClip(s) + '</td>'
            + '<td>' + detFecha(s.fecha_evento) + '</td>'
            + susCeldaCongelado(s.interes_congelado)
            + '<td>' + susBadgeEstado(s.estado) + '</td>'
            + '<td class="text-end"><button class="btn btn-light btn-sm py-0 px-2" '
            + 'onclick="confirmarLevantar(' + s.id_suspension + ')">Levantar</button></td>'
            + '</tr>';
    }).join('');

    susTablaVigentes = crearTablaDeterioro('tablaVigentes', {
        order: [[3, 'desc']],
        columnDefs: [{ targets: [6], orderable: false }]
    });
    detTooltips('#panelVigentes');
};

/* --- Candidatas sugeridas --- */

const pintarCandidatas = function () {
    const candidatas = susDatos.candidatas || [];
    if (susTablaCandidatas) { susTablaCandidatas.destroy(); susTablaCandidatas = null; }

    if (!candidatas.length) {
        document.getElementById('scrollCandidatas').style.display = 'none';
        document.getElementById('tbodyCandidatas').innerHTML = '';
        document.getElementById('vacioCandidatas').innerHTML = detVacio('fa-thumbs-up', 'Sin candidatas',
            'No hay operaciones en mora avanzada sin marca vigente en este corte.');
        return;
    }
    document.getElementById('scrollCandidatas').style.display = '';
    document.getElementById('vacioCandidatas').innerHTML = '';

    document.getElementById('tbodyCandidatas').innerHTML = candidatas.map(c =>
        '<tr><td><strong>' + c.id_operacion + '</strong>'
        + (c.nom_operacion ? '<span class="det-subtitulo d-block">' + c.nom_operacion + '</span>' : '') + '</td>'
        + '<td title="' + (c.id_cliente || '') + '">' + (c.cliente || '') + '</td>'
        + '<td>' + (c.producto || '') + '</td>'
        + '<td data-order="' + (detOrdenRango[c.rango] ?? 9) + '">' + detBadgeRango(c.rango) + '</td>'
        + '<td class="num" data-order="' + susNum(c.dias_mora_operacion) + '">' + detEntero(c.dias_mora_operacion) + '</td>'
        + detCeldaNum(c.capital_vencido)
        + detCeldaNum(c.interes_vencido)
        + detCeldaNum(c.base_deterioro)
        + detCeldaNum(c.deterioro_contable)
        + '<td class="text-end"><button class="btn btn-outline-primary btn-sm py-0 px-2" '
        + 'onclick="abrirMarcar(' + c.id_operacion + ')">Marcar</button></td>'
        + '</tr>').join('');

    susTablaCandidatas = crearTablaDeterioro('tablaCandidatas', {
        order: [[4, 'desc']],
        columnDefs: [{ targets: [9], orderable: false }]
    });
};

/* --- Histórico de marcas levantadas --- */

const pintarHistorico = function () {
    const historico = (susLista.suspensiones || []).filter(s => s.estado === 'LEVANTADA');
    if (susTablaHistorico) { susTablaHistorico.destroy(); susTablaHistorico = null; }

    if (!historico.length) {
        document.getElementById('scrollHistorico').style.display = 'none';
        document.getElementById('tbodyHistorico').innerHTML = '';
        document.getElementById('vacioHistorico').innerHTML = detVacio('fa-clock-rotate-left', 'Sin marcas levantadas',
            'Todavía no se ha levantado ninguna suspensión de interés.');
        return;
    }
    document.getElementById('scrollHistorico').style.display = '';
    document.getElementById('vacioHistorico').innerHTML = '';

    document.getElementById('tbodyHistorico').innerHTML = historico.map(s =>
        '<tr><td><strong>' + s.id_operacion + '</strong></td>'
        + '<td title="' + (s.id_cliente || '') + '">' + (s.cliente || '') + '</td>'
        + '<td>' + (s.causal || '') + susClip(s) + '</td>'
        + '<td>' + detFecha(s.fecha_evento) + '</td>'
        + susCeldaCongelado(s.interes_congelado)
        + '<td>' + detFecha(s.fecha_reactivacion) + '</td>'
        + '<td>' + (s.observacion_reactivacion || '') + '</td>'
        + '</tr>').join('');

    susTablaHistorico = crearTablaDeterioro('tablaHistorico', { order: [[5, 'desc']] });
    detTooltips('#panelHistorico');
};

/* --- Controles de cuadre --- */

const pintarCuadresSus = function () {
    const html = (susDatos.cuadres || []).map(detFilaCuadre).join('');
    document.getElementById('listaCuadresSus').innerHTML = html || '<p class="text-muted mb-0">Sin controles registrados.</p>';
};

/* --- Modal Marcar operación --- */

const poblarCausales = function () {
    document.getElementById('marcarCausal').innerHTML = '<option value="">Seleccione…</option>'
        + susCausales.map(c => '<option value="' + susTexto(c.codigo) + '">'
            + susTexto(c.descripcion) + '</option>').join('');
};

const abrirMarcar = function (idOperacion) {
    ['idOperacion', 'causal', 'fechaEvento', 'observacion', 'soporte'].forEach(function (campo) {
        const el = document.getElementById('error-' + campo);
        if (el) { el.innerHTML = ''; }
    });
    document.getElementById('marcarOperacion').value = idOperacion || '';
    document.getElementById('marcarCausal').value = '';
    document.getElementById('marcarFecha').value = '';
    document.getElementById('marcarObservacion').value = '';
    quitarSoporte();
    new bootstrap.Modal(document.getElementById('modalMarcarSuspension')).show();
};

const susTamano = function (bytes) {
    const mb = bytes / 1048576;
    return mb >= 1 ? mb.toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
};

const susRechazarSoporte = function (mensaje) {
    const input = document.getElementById('marcarSoporte');
    input.value = '';
    input.classList.add('is-invalid');
    document.getElementById('error-soporte').textContent = mensaje;
    document.getElementById('lineaSoporte').classList.add('d-none');
};

/** Tipo y peso se validan aquí: ningún archivo rechazado viaja al servidor. */
const validarSoporte = function () {
    const input = document.getElementById('marcarSoporte');
    const linea = document.getElementById('lineaSoporte');
    const archivo = input.files && input.files[0];
    input.classList.remove('is-invalid');
    document.getElementById('error-soporte').textContent = '';

    if (!archivo) { linea.classList.add('d-none'); linea.innerHTML = ''; return; }
    if (!susTiposSoporte.includes(archivo.type)) {
        susRechazarSoporte('Sólo se acepta PDF o imagen escaneada (JPG o PNG).');
        return;
    }
    if (archivo.size > susMaxSoporte) {
        susRechazarSoporte('El archivo pesa ' + susTamano(archivo.size) + ' y el máximo es 20 MB. '
            + 'Comprima el PDF o escanee a menor resolución.');
        return;
    }

    linea.innerHTML = '<i class="fas ' + (archivo.type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file-image') + '"></i>'
        + '<span class="nom">' + susTexto(archivo.name) + '</span>'
        + '<span>' + susTamano(archivo.size) + '</span>'
        + '<button type="button" class="btn btn-link btn-sm p-0 text-danger ms-auto" onclick="quitarSoporte()">'
        + '<i class="fas fa-trash"></i>&nbsp; Quitar</button>';
    linea.classList.remove('d-none');
};

const quitarSoporte = function () {
    document.getElementById('marcarSoporte').value = '';
    validarSoporte();
};

const confirmarMarcar = function () {
    /** El soporte es opcional, pero un archivo rechazado no puede convertirse en silencio en una marca sin evidencia. */
    const rechazado = document.getElementById('error-soporte').textContent.trim()
        && !document.getElementById('marcarSoporte').files[0];
    Swal.fire({
        title: '¿Marcar esta operación?',
        text: 'El interés reconocido a la fecha del evento queda congelado. La base pasa a tomarse de SIESA si la operación tiene saldo atribuido allí; si no, es el capital vencido de factoring más el interés congelado. '
            + 'El capital se sigue deteriorando normal. La marca se puede levantar después, pero queda en bitácora.'
            + (rechazado ? ' El archivo que eligió fue rechazado: la marca quedará sin soporte.' : ''),
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Marcar', cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d9a520'
    }).then(v => { if (v.isConfirmed) guardarMarcar(); });
};

const guardarMarcar = async function () {
    const archivo = document.getElementById('marcarSoporte').files[0];
    const boton = document.querySelector('#modalMarcarSuspension .modal-footer .btn-primary');
    const rotulo = boton.innerHTML;
    const datos = new FormData();
    datos.append('idOperacion', document.getElementById('marcarOperacion').value);
    datos.append('causal', document.getElementById('marcarCausal').value);
    datos.append('fechaEvento', document.getElementById('marcarFecha').value);
    datos.append('observacion', document.getElementById('marcarObservacion').value);
    if (archivo) { datos.append('soporte', archivo); }

    boton.disabled = true;
    boton.textContent = 'Subiendo…';
    let res;
    try {
        res = await makeOptionsFetch(`${globalUrl}/deterioro-marcar-suspension`, datos, 'post', susToken());
    } catch (e) {
        detError('No se pudo enviar el soporte', 'Se interrumpió el envío del archivo y la marca no quedó creada. '
            + 'Verifique la conexión y vuelva a intentarlo.');
        return;
    } finally {
        boton.disabled = false;
        boton.innerHTML = rotulo;
    }
    if (res.errors) { showErrors(res); return; }

    bootstrap.Modal.getInstance(document.getElementById('modalMarcarSuspension')).hide();
    if (res.res !== 'ok') { detError(res.title || 'Error', res.text); return; }

    const texto = res.congelado ? res.text : (res.text || '')
        + ' La marca quedó activa, pero el interés no quedó congelado: no fue posible determinarlo a la fecha del evento.';

    Swal.fire({
        title: res.title, text: texto,
        icon: res.congelado ? 'success' : 'warning',
        confirmButtonColor: res.congelado ? 'rgb(65,110,195)' : '#d9a520'
    }).then(() => cargarSuspensiones());
};

/* --- Levantar marca --- */

const susIdentidadMarca = function (s) {
    if (!s) return '';
    return '<p class="det-subtitulo mb-2">Operación <strong>' + susTexto(s.id_operacion) + '</strong> &middot; '
        + susTexto(s.causal || '') + ' &middot; evento del ' + detFecha(s.fecha_evento) + '</p>'
        + (susNum(s.tiene_soporte) && s.soporte_url
            ? '<p class="mb-2"><a href="' + susTexto(s.soporte_url) + '" target="_blank" rel="noopener">'
            + '<i class="fas fa-paperclip"></i>&nbsp; Ver el soporte de la marca ('
            + susTexto(s.soporte_nombre || 'archivo adjunto') + ')</a></p>'
            : '');
};

const confirmarLevantar = function (idSuspension) {
    const marca = (susLista.suspensiones || []).find(s => Number(s.id_suspension) === Number(idSuspension));
    Swal.fire({
        title: '¿Levantar esta marca?',
        html: susIdentidadMarca(marca)
            + '<p class="mb-0">La operación vuelve a acumular interés en la base de deterioro a partir de ahora.</p>',
        icon: 'info',
        input: 'textarea',
        inputPlaceholder: 'Observación de la reactivación',
        inputValidator: v => !v ? 'La observación es obligatoria' : undefined,
        showCancelButton: true,
        confirmButtonText: 'Levantar', cancelButtonText: 'Cancelar',
        confirmButtonColor: 'rgb(65,110,195)'
    }).then(async v => {
        if (!v.isConfirmed) return;
        const datos = new FormData();
        datos.append('idSuspension', idSuspension);
        datos.append('observacion', v.value);
        const res = await makeOptionsFetch(`${globalUrl}/deterioro-levantar-suspension`, datos, 'post', susToken());
        if (res.res !== 'ok') { detError(res.title || 'Error', res.text); return; }
        Swal.fire({ title: res.title, text: res.text, icon: 'success', confirmButtonColor: 'rgb(65,110,195)' })
            .then(() => cargarSuspensiones());
    });
};
