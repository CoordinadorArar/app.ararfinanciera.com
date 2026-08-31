var archivoEscaneadoUrl = null;
var URL_SCANNER_LOCAL = 'http://localhost:5050/scan';

$(document).ready(function () {
    cargarFacturasPendientes();
    cargarCajasEnProceso();

    $('#tbodyFacturasPendientes').on('click', '.btn-digitalizar', function () {
        var factura = $(this).data('factura');
        var idCliente = $(this).data('idcliente');

        $('#formDigitalizar')[0].reset();
        limpiarArchivoEscaneado();
        $('#inputFactura').val(factura);
        $('#inputIdCliente').val(idCliente);
        $('#spanFacturaModal').text('#' + factura + ' - Cliente ' + idCliente);

        cargarCajasEnProceso();
        // El modal se abre solo, vía data-bs-toggle/data-bs-target en el propio botón.
    });

    $('#btnEscanearPdf').on('click', function () {
        escanearPdf();
    });

    $('#btnVerEscaneado').on('click', function () {
        if (archivoEscaneadoUrl) {
            window.open(archivoEscaneadoUrl, '_blank');
        }
    });

    $('#btnEliminarEscaneado').on('click', function () {
        limpiarArchivoEscaneado();
    });

    $('#formDigitalizar').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var $boton = $(this).find('button[type="submit"]');
        $boton.prop('disabled', true).text('Guardando...');

        axios.post('/digitalizar-factura', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        }).then(function (response) {
            $('#modalDigitalizar [data-bs-dismiss="modal"]').first().trigger('click');
            Swal.fire({ title: 'Listo', text: 'Factura digitalizada correctamente en el folio ' + response.data.folio, icon: 'success' });
            cargarFacturasPendientes();
            cargarCajasEnProceso();
        }).catch(function (error) {
            var mensaje = (error.response && error.response.data && error.response.data.message)
                ? error.response.data.message
                : 'Ocurrió un error al digitalizar la factura.';
            Swal.fire({ title: 'Error', text: mensaje, icon: 'error' });
            console.error(error);
        }).then(function () {
            $boton.prop('disabled', false).html('<i class="fas fa-save"></i> Guardar');
        });
    });
});

function cargarFacturasPendientes() {
    axios.post('/facturas-pendientes-digitalizar').then(function (response) {
        pintarFacturasPendientes(response.data.facturas);
    }).catch(function (error) {
        $('#tbodyFacturasPendientes').html('<tr><td colspan="8" class="text-center text-danger">Error al cargar las facturas pendientes.</td></tr>');
        console.error(error);
    });
}

function pintarFacturasPendientes(facturas) {
    var tbody = $('#tbodyFacturasPendientes');
    tbody.empty();

    if (!facturas || facturas.length === 0) {
        tbody.html('<tr><td colspan="8" class="text-center">No hay facturas pendientes de digitalizar.</td></tr>');
        return;
    }

    facturas.forEach(function (f) {
        tbody.append(
            '<tr>' +
            '<td>' + (f.TipoDocumento || '') + '</td>' +
            '<td>' + (f.IdOperacion || '') + '</td>' +
            '<td>' + (f.Cuota || '') + '</td>' +
            '<td>' + (f.IdCliente || '') + '</td>' +
            '<td>' + (f.FecOperacion || '') + '</td>' +
            '<td>' + (f.Asesor || '') + '</td>' +
            '<td>' + (f.Nota || '') + '</td>' +
            '<td><button type="button" class="btn btn-sm btn-primary btn-digitalizar" data-bs-toggle="modal" data-bs-target="#modalDigitalizar" data-factura="' + f.IdOperacion + '" data-idcliente="' + f.IdCliente + '"><i class="fas fa-file-upload"></i> Digitalizar</button></td>' +
            '</tr>'
        );
    });
}

function cargarCajasEnProceso() {
    axios.post('/cajas-en-proceso').then(function (response) {
        var select = $('#selectCaja');
        select.find('option:not(:first)').remove();
        (response.data.cajas || []).forEach(function (c) {
            select.append('<option value="' + c.id + '">' + c.nombre + ' (ID ' + c.id + ') - ' + c.totalFacturas + ' facturas</option>');
        });
    }).catch(function (error) {
        console.error(error);
    });
}

/**Pedir el PDF al Local Bridge (escáner conectado al computador del usuario) y dejarlo
 * seleccionado por defecto en el input de archivo del formulario. */
function escanearPdf() {
    var $boton = $('#btnEscanearPdf');
    var $error = $('#spanErrorEscaneo');
    $error.addClass('d-none').text('');
    $boton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Escaneando...');

    fetch(URL_SCANNER_LOCAL)
        .then(function (response) {
            if (!response.ok) {
                return response.text().then(function (errText) {
                    throw new Error(errText || ('El escáner respondió con error ' + response.status));
                });
            }
            return response.blob();
        })
        .then(function (blob) {
            var archivo = new File([blob], 'escaneo.pdf', { type: 'application/pdf' });
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(archivo);
            document.getElementById('inputArchivo').files = dataTransfer.files;

            if (archivoEscaneadoUrl) {
                URL.revokeObjectURL(archivoEscaneadoUrl);
            }
            archivoEscaneadoUrl = URL.createObjectURL(blob);

            $('#spanArchivoEscaneado').removeClass('d-none');
        })
        .catch(function (err) {
            $error.removeClass('d-none').text('No se pudo escanear: ' + err.message);
        })
        .then(function () {
            $boton.prop('disabled', false).html('<i class="fas fa-print"></i> Escanear PDF');
        });
}

/**Quitar el PDF escaneado (del input y de la vista previa) para volver a escanear o subir uno manual */
function limpiarArchivoEscaneado() {
    document.getElementById('inputArchivo').value = '';
    if (archivoEscaneadoUrl) {
        URL.revokeObjectURL(archivoEscaneadoUrl);
        archivoEscaneadoUrl = null;
    }
    $('#spanArchivoEscaneado').addClass('d-none');
    $('#spanErrorEscaneo').addClass('d-none').text('');
}
