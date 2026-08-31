$(document).ready(function () {
    $('#formConsultaFolios').on('submit', function (e) {
        e.preventDefault();
        var tipoBusqueda = $('input[name="tipoBusqueda"]:checked').val();
        var valorBusqueda = $('#valorBusqueda').val().trim();

        if (!valorBusqueda) {
            return;
        }

        $('#divResultadosFolios').html('<p class="text-center">Consultando...</p>');

        axios.post('/consultar-folios', {
            tipoBusqueda: tipoBusqueda,
            valorBusqueda: valorBusqueda
        }).then(function (response) {
            pintarResultadosFolios(response.data.resultados);
        }).catch(function (error) {
            $('#divResultadosFolios').html('<p class="text-center text-danger">Ocurrió un error al consultar los folios.</p>');
            console.error(error);
        });
    });
});

function pintarResultadosFolios(resultados) {
    var contenedor = $('#divResultadosFolios');
    contenedor.empty();

    if (!resultados || resultados.length === 0) {
        contenedor.html('<p class="text-center">No se encontraron folios con ese criterio.</p>');
        return;
    }

    resultados.forEach(function (resultado) {
        var folio = resultado.folio;
        var clientes = resultado.clientes || [];
        var facturas = resultado.facturas || [];

        var html = '<div class="card mb-3">';
        html += '<div class="card-header bg-primary text-white">Folio: ' + (folio ? folio.folio : '') + ' (ID ' + (folio ? folio.id : '') + ')</div>';
        html += '<div class="card-body">';

        // Información de clientes
        html += '<h6><i class="fas fa-user"></i> Información del cliente</h6>';
        if (clientes.length === 0) {
            html += '<p class="text-muted">Sin clientes asociados a este folio.</p>';
        } else {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
            html += '<thead><tr><th>Documento</th><th>Nombre</th><th>Teléfono</th><th>Email</th><th>Dirección</th><th>Registrado por</th></tr></thead><tbody>';
            clientes.forEach(function (c) {
                var nombre = c.NombresTercero ? (c.NombresTercero + ' ' + (c.ApellidosTercero || '')).trim() : '<span class="text-muted">No encontrado en SIESA</span>';
                html += '<tr>' +
                    '<td>' + (c.DocumentoTercero || c.cedula || '') + '</td>' +
                    '<td>' + nombre + '</td>' +
                    '<td>' + (c.TelefonoTercero || '') + '</td>' +
                    '<td>' + (c.EmailTercero || '') + '</td>' +
                    '<td>' + (c.DireccionDomicilioTercero || '') + '</td>' +
                    '<td>' + (c.UsuarioRegistro || '') + '</td>' +
                    '</tr>';
            });
            html += '</tbody></table></div>';
        }

        // Información de facturas + ubicación física de la caja (folio -> caja -> ubicación, igual para todas las facturas del folio)
        var ubicacion = resultado.ubicacion;
        var celdasUbicacion = ubicacion
            ? '<td>' + (ubicacion.NombreCaja || '') + ' (' + (ubicacion.idCaja || '') + ')</td>' +
              '<td>' + (ubicacion.id_piso ?? '') + '</td>' +
              '<td>' + (ubicacion.id_pasillo ?? '') + '</td>' +
              '<td>' + (ubicacion.id_estante ?? '') + '</td>' +
              '<td>' + (ubicacion.id_posicion ?? '') + '</td>' +
              '<td>' + (ubicacion.id_columna ?? '') + '</td>' +
              '<td>' + (ubicacion.id_fila ?? '') + '</td>'
            : '<td colspan="7" class="text-muted">Sin caja asignada</td>';

        html += '<h6 class="mt-3"><i class="fas fa-file-invoice-dollar"></i> Facturas del folio</h6>';
        if (facturas.length === 0) {
            html += '<p class="text-muted">Sin facturas asociadas a este folio.</p>';
        } else {
            html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
            html += '<thead><tr>' +
                '<th>Factura</th><th>Fecha</th><th>Hora</th><th>Registrado por</th><th>Documento</th>' +
                '<th>Caja</th><th>Piso</th><th>Pasillo</th><th>Estante</th><th>Posición</th><th>Columna</th><th>Fila</th>' +
                '</tr></thead><tbody>';
            facturas.forEach(function (f) {
                var documentoEscaneado = (f.documento && f.documento.img)
                    ? '<a href="' + f.documento.img + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-pdf"></i> Ver PDF</a>'
                    : '<span class="text-muted">Sin documento</span>';
                html += '<tr>' +
                    '<td>' + (f.factura || '') + '</td>' +
                    '<td>' + (f.fecha || '') + '</td>' +
                    '<td>' + (f.hora || '') + '</td>' +
                    '<td>' + (f.UsuarioRegistro || '') + '</td>' +
                    '<td>' + documentoEscaneado + '</td>' +
                    celdasUbicacion +
                    '</tr>';
            });
            html += '</tbody></table></div>';
        }

        html += '</div></div>';
        contenedor.append(html);
    });
}
