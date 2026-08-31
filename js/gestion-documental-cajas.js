$(document).ready(function () {
    cargarCajasPendienteUbicacion();

    // Guardar ubicación física: Pendiente ubicación -> Ubicada
    $('#tbodyCajasPendienteUbicacion').on('click', '.btn-guardar-ubicacion', function () {
        var $fila = $(this).closest('tr');
        var idCaja = $fila.data('idcaja');

        var datos = {
            idCaja: idCaja,
            idPiso: $fila.find('[name="idPiso"]').val(),
            idPasillo: $fila.find('[name="idPasillo"]').val(),
            idEstante: $fila.find('[name="idEstante"]').val(),
            idPosicion: $fila.find('[name="idPosicion"]').val(),
            idColumna: $fila.find('[name="idColumna"]').val(),
            idFila: $fila.find('[name="idFila"]').val()
        };

        if (!datos.idPiso || !datos.idPasillo || !datos.idEstante || !datos.idPosicion || !datos.idColumna || !datos.idFila) {
            Swal.fire({ title: 'Faltan datos', text: 'Completa piso, pasillo, estante, posición, columna y fila.', icon: 'warning' });
            return;
        }

        axios.post('/ubicar-caja', datos).then(function () {
            Swal.fire({ title: 'Listo', text: 'La caja quedó ubicada.', icon: 'success' });
            cargarCajasPendienteUbicacion();
        }).catch(function (error) {
            Swal.fire({ title: 'Error', text: 'No se pudo guardar la ubicación.', icon: 'error' });
            console.error(error);
        });
    });
});

function cargarCajasPendienteUbicacion() {
    axios.post('/cajas-pendientes-ubicacion').then(function (response) {
        pintarCajasPendienteUbicacion(response.data.cajas);
    }).catch(function (error) {
        $('#tbodyCajasPendienteUbicacion').html('<tr><td colspan="10" class="text-center text-danger">Error al cargar las cajas.</td></tr>');
        console.error(error);
    });
}

function pintarCajasPendienteUbicacion(cajas) {
    var tbody = $('#tbodyCajasPendienteUbicacion');
    tbody.empty();

    if (!cajas || cajas.length === 0) {
        tbody.html('<tr><td colspan="10" class="text-center">No hay cajas pendientes de ubicación.</td></tr>');
        return;
    }

    cajas.forEach(function (c) {
        var campos = ['idPiso', 'idPasillo', 'idEstante', 'idPosicion', 'idColumna', 'idFila'];
        var celdasInput = campos.map(function (campo) {
            return '<td><input type="number" min="1" class="form-control form-control-sm" name="' + campo + '"></td>';
        }).join('');

        tbody.append(
            '<tr data-idcaja="' + c.id + '">' +
            '<td>' + c.id + '</td>' +
            '<td>' + (c.nombre || '') + '</td>' +
            '<td>' + c.totalFacturas + '</td>' +
            celdasInput +
            '<td><button type="button" class="btn btn-sm btn-success btn-guardar-ubicacion"><i class="fas fa-map-marker-alt"></i> Guardar</button></td>' +
            '</tr>'
        );
    });
}
