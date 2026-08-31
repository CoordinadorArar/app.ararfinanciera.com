let arrayDocumentosNombres = [], arrayDocumentosId = [];
window.onload = function(){
    //const pdf = new jsPDF();
    // pdf.text("Hello world!", 10, 10);
    // pdf.save("a4.pdf");
    crearTablaResponsiva('tablaGestionProcesos');
}
// const changeStateprocesos = async function(){
//     let dataToSend = new FormData();
//     let res = await makeOptionsFetch(`${globalUrl}/verify-documents-all-procesos`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
//     console.log(res);
// }();
/**Filtrar lista de procesos segun estado o documento del tercero */
const filtrarProcesos = async function(estado=''){
    let dataToSend = new FormData();
    dataToSend.append('filtro',estado);
    dataToSend.append('busqueda',document.getElementById('busquedaProceso').value);
    $('#tablaGestionProcesos').preloader();
    let res = await makeOptionsFetch(`${globalUrl}/lista-procesos-filtro`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
    if(res){
        $('#tablaGestionProcesos').preloader('remove');
        document.getElementById('tablaGestionProcesos').innerHTML = res;
    }
}
const mostrarVistas = function(idProceso,vista){
    //$('#modalAcciones').modal('show');
    let divTablaProcesos = document.getElementById('div-tabla-procesos');
    switch(vista){
        case 'centrales':
            divTablaProcesos.style.display = 'none';
            document.getElementById('consulta-centrales').style.display = 'block';
            centralesRiesgo(idProceso);
        break;
        case 'docs soporte':
            divTablaProcesos.style.display = 'none';
            document.getElementById('documentos-soporte').style.display = 'block';
            subirDocumentosSoporte(idProceso);
        break;
        case 'docs aprobar':
            divTablaProcesos.style.display = 'none';
            document.getElementById('revision-documentos').style.display = 'block';
            revisarDocumentosSubidos(idProceso);
        break;
        case 'credito aprobar':
            divTablaProcesos.style.display = 'none';
            document.getElementById('aprobacion-creditos').style.display = 'block';
            mostrarInfoCredito(idProceso);
        break;
    }
}
const backToTable = function(vista){
    document.getElementById('div-tabla-procesos').style.display = 'block';
    document.getElementById(vista).style.display = 'none';
}
/**---Vista consulta en centrales de riesgo */
/** */
const centralesRiesgo = async function(idProceso){
    $('#consulta-centrales').preloader();
    let dataToSend = new FormData();
    dataToSend.append('idProceso', idProceso);
    let res = await makeOptionsFetch(`${globalUrl}/consulta-centrales-riesgo`,dataToSend,'post',$('meta[name="csrf-token-consulta-centrales"]').attr('content'));
    console.log(res.xml);
    if(res.res == 'ok'){
        Tercero = res.xml.Tercero;
        let DatosPersonales = {
            TipoIdentificacion: Tercero.TipoIdentificacion,
            NumeroIdentificacion: Tercero.NumeroIdentificacion,
            NombreTitular: Tercero.NombreTitular,
            CIIU: Tercero.CodigoCiiu,
            EstadoDocumento: Tercero.Estado,
            FechaExpedicion: Tercero.FechaExpedicion,
            LugarExpedicion: Tercero.LugarExpedicion,
            RangoEdad: Tercero.RangoEdad,
            Fecha: Tercero.Fecha,
            Hora: Tercero.Hora,
            Usuario: Tercero.Entidad,
            NumeroInforme: Tercero.NumeroInforme
        }
        let ResumenEndeudamiento = Tercero.Consolidado.ResumenPrincipal.Registro;
        let InformeDetallado = Tercero.SectorFinancieroAlDia.Obligacion;
        console.log(ResumenEndeudamiento);
        construirHtmlConsulta(DatosPersonales,ResumenEndeudamiento,InformeDetallado);
        $('#consulta-centrales').preloader('remove');
    }
}

const construirHtmlConsulta = function(DatosPersonales,ResumenEndeudamiento,InformeDetallado){
    let htmlInfoBasica = `<tbody>
                            <tr>
                                <td>
                                    <th>TipoIdentificacion: </th>
                                    <td>${DatosPersonales.TipoIdentificacion}</td>
                                </td>
                                <td>
                                    <th>EstadoDocumento: </th>
                                    <td>${DatosPersonales.EstadoDocumento}</td>
                                </td>
                                <td>
                                    <th>Fecha: </th>
                                    <td>${DatosPersonales.Fecha}</td>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <th>NumeroIdentificacion: </th>
                                    <td>${DatosPersonales.NumeroIdentificacion}</td>
                                </td>
                                <td>
                                    <th>FechaExpedicion: </th>
                                    <td>${DatosPersonales.FechaExpedicion}</td>
                                </td>
                                <td>
                                    <th>Hora: </th>
                                    <td>${DatosPersonales.Hora}</td>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <th>NombreTitular: </th>
                                    <td>${DatosPersonales.NombreTitular}</td>
                                </td>
                                <td>
                                    <th>LugarExpedicion: </th>
                                    <td>${DatosPersonales.LugarExpedicion}</td>
                                </td>
                                <td>
                                    <th>Usuario: </th>
                                    <td>${DatosPersonales.Usuario}</td>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <th>Actividad Económica - CIIU: </th>
                                    <td>${DatosPersonales.CIIU}</td>
                                </td>
                                <td>
                                    <th>RangoEdad: </th>
                                    <td>${DatosPersonales.RangoEdad}</td>
                                </td>
                                <td>
                                    <th>NumeroInforme: </th>
                                    <td>${DatosPersonales.NumeroInforme}</td>
                                </td>
                            </tr>
                        </tbody>`;
    let htmlResumenEndeudamiento = htmlInfoDetallado = ``;
    let saldoTotal = saldoTotalDia = saldoTotalMora = 0;
    let cantidadTotal = cantidadDia = cantidadMora = 0;
    let pade = cuotaDia = cuotaMora = 0;
    let valorMora = 0;
    ResumenEndeudamiento.forEach((item)=>{
        cantidadTotal += (item.NumeroObligaciones != null && item.NumeroObligaciones != undefined)? parseInt(item.NumeroObligaciones) : 0;
        cantidadDia += (item.NumeroObligacionesDia != null && item.NumeroObligacionesDia != undefined)? parseInt(item.NumeroObligacionesDia) : 0;
        cantidadMora += (item.CantidadObligacionesMora != null && item.CantidadObligacionesMora != undefined)? parseInt(item.CantidadObligacionesMora) : 0;
        saldoTotal += (item.TotalSaldo != null && item.TotalSaldo != undefined)? parseInt(item.TotalSaldo) : 0;
        saldoTotalDia += (item.SaldoObligacionesDia != null && item.SaldoObligacionesDia != undefined)? parseInt(item.SaldoObligacionesDia) : 0;
        saldoTotalMora += (item.SaldoObligacionesMora != null && item.SaldoObligacionesMora != undefined)? parseInt(item.SaldoObligacionesMora) : 0;
        pade += (item.ParticipacionDeuda != null && item.ParticipacionDeuda != undefined)? parseInt(item.ParticipacionDeuda) : 0;
        cuotaDia += (item.CuotaObligacionesDia != null && item.CuotaObligacionesDia != undefined)? parseInt(item.CuotaObligacionesDia) : 0;
        cuotaMora += (item.CuotaObligacionesMora != null && item.CuotaObligacionesMora != undefined)? parseInt(item.CuotaObligacionesMora) : 0;
        valorMora += (item.ValorMora != null && item.ValorMora != undefined)? parseInt(item.ValorMora) : 0;
        htmlResumenEndeudamiento += `<tr>
                                        <th>${item.PaqueteInformacion}</th>
                                        <td>${item.NumeroObligaciones}</td>
                                        <td>${item.TotalSaldo}</td>
                                        <td class="border-r">${item.ParticipacionDeuda}</td>
                                        <td>${item.NumeroObligacionesDia}</td>
                                        <td>${item.SaldoObligacionesDia}</td>
                                        <td class="border-r">${item.CuotaObligacionesDia}</td>
                                        <td>${item.CantidadObligacionesMora}</td>
                                        <td>${item.SaldoObligacionesMora}</td>
                                        <td>${item.CuotaObligacionesMora}</td>
                                        <td>${item.ValorMora}</td>
                                    </tr>`;
    });
    htmlResumenEndeudamiento += `<tr style="background-color: rgb(65 110 195 / 50%);">
                                    <th>SUBTOTAL PRINCIPAL</th>
                                    <td>${cantidadTotal}</td>
                                    <td>${saldoTotal}</td>
                                    <td class="border-r">${pade}</td>
                                    <td>${cantidadDia}</td>
                                    <td>${saldoTotalDia}</td>
                                    <td class="border-r">${cuotaDia}</td>
                                    <td>${cantidadMora}</td>
                                    <td>${saldoTotalMora}</td>
                                    <td>${cuotaMora}</td>
                                    <td>${valorMora}</td>
                                </tr>`;
    htmlResumenEndeudamiento += `<tr><th colspan="11" class="text-center">RESUMEN TOTAL DE OBLIGACIONES</th></tr>`;
    htmlResumenEndeudamiento += `<tr style="background-color: rgb(65 110 195 / 50%);">
                                    <th>TOTAL</th>
                                    <td>${cantidadTotal}</td>
                                    <td>${saldoTotal}</td>
                                    <td class="border-r">${pade}</td>
                                    <td>${cantidadDia}</td>
                                    <td>${saldoTotalDia}</td>
                                    <td class="border-r">${cuotaDia}</td>
                                    <td>${cantidadMora}</td>
                                    <td>${saldoTotalMora}</td>
                                    <td>${cuotaMora}</td>
                                    <td>${valorMora}</td>
                                </tr>`;
    InformeDetallado.forEach((item)=>{
        let FechaCorte = (item.FechaCorte != null && item.FechaCorte != {})? item.FechaCorte : '';
        htmlInfoDetallado += `<tr>
                                <td colspan="2">${item.FechaCorte}</td>
                                <td>${item.ModalidadCredito}</td>
                                <td>${item.NumeroObligacion}</td>
                                <td>${item.TipoEntidad}</td>
                                <td>${item.NombreEntidad}</td>
                                <td>${item.Ciudad}</td>
                                <td>${item.Calidad}</td>
                                <td>${'MRC'}</td>
                                <td>${item.TipoGarantia}</td>
                                <td>${item.FechaApertura}</td>
                                <td style="width:10%;"><table><tbody><tr><td style="width:33%;">${item.NumeroCuotasPactadas}</td><td style="width:33%;">${item.CuotasCanceladas}</td><td style="width:33%;">${item.NumeroCuotasMora}</td></tr></tbody></table></td>
                                <td>${item.ValorInicial}</td>
                                <td>${item.ValorCuota}</td>
                                <td>${item.EstadoObligacion}</td>
                                <td>${item.NaturalezaReestructuracion}</td>
                                <td>${item.NumeroReestructuraciones}</td>
                                <td>${item.TipoPago}</td>
                                <td>${item.FechaPago}</td>
                            </tr>`;
                
    });
    document.getElementById('tabla-info-basica').innerHTML = htmlInfoBasica;
    document.getElementById('resumen-endeudamiento-body').innerHTML = htmlResumenEndeudamiento;
    document.getElementById('informe-detallado-body').innerHTML = htmlInfoDetallado;
}

/**---vista documentos de soporte subida */
/**Mostrar vista e informacion para subir los documentos de soporte */
const subirDocumentosSoporte = async function(idProceso){
    const formatter = new Intl.NumberFormat('en-US',{
        style:'currency',
        currency:'USD',
        minimumFractionDigits: 0
    });
    let dataToSend = new FormData();
    dataToSend.append('idProceso', idProceso); dataToSend.append('accion','uploadDocs');
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-proceso`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
    let htmlProceso = '<table class="table table-sm">';
    let htmlArchivos = `<input type="hidden" id="idTercero" name="idTercero" value="${res.proceso[0].IdTercero}">
                        <div class="row">`;
    let htmlIndividual;
    res.documentos.forEach((item,index)=>{
        let separarNombre = item.NombreDocumento.split(' ');
        let valor = '';
        separarNombre.forEach((item2,index2)=>{
            if(index2 > 0){
                let palabra = item2.split('');
                let toMayus = '';
                palabra.forEach((item3,index3)=>{
                    if(index3 == 0){
                        toMayus += item3.toUpperCase();
                    }else{
                        toMayus += item3;
                    }
                });
                valor += toMayus;
            }else{
                valor += item2;
            }
        });
        htmlIndividual = `<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 form-group mb-2">
                                <label>${item.NombreDocumento}</label>
                                <div class="input-group">
                                    <input type="file" class="form-control form-control-sm input-file-support" id="${valor}" name="${valor}" accept=".pdf">
                                    <button class="btn btn-primary input-group-text" id="enviar-${item.NombreDocumento}" onclick="enviarArchivos(${idProceso},this,event)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="id_${valor}" name="id_${valor}" value="${item.IdDocumentoSolicitado}">
                                <span class="invalid-feedback" role="alert" id="error-${valor}">
                            </div>`;
        arrayDocumentosNombres.push(valor); arrayDocumentosId.push(item.IdDocumentoSolicitado);
        let documentosCargados = res.proceso[0].DocumentosCargados.split(',');
        documentosCargados.forEach(element=>{
            if(element == item.IdDocumentoSolicitado){
                htmlIndividual = `<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 form-group mb-2">
                                    <label>${item.NombreDocumento}</label>
                                    <div class="alert alert-success text-center">Documento cargado <i class="fas fa-check"></i></div>
                                </div>`;
            }
        });
        htmlArchivos += htmlIndividual;
    });
    res.proceso.forEach(item=>{ //impresion de informacion sobre el proceso
        htmlProceso += `<tr>
                            <th>Documento: </th>
                            <td>${item.DocumentoTercero}</td>
                        </tr>
                        <tr>
                            <th>Tercero: </th>
                            <td>${item.NombresTercero} ${item.ApellidosTercero}</td>
                        </tr>
                        <tr>
                            <th>Valor solicitado: </th>
                            <td>${formatter.format(item.ValorCreditoSolicitado)}</td>
                        </tr>
                        <tr>
                            <th>Pagaduria: </th>
                            <td>${item.NombrePagaduria}</td>
                        </tr>
                        <tr>
                            <th>Cupo disponible: </th>
                            <td>${formatter.format(item.CupoDisponible)}</td>
                        </tr>`;
    });
    let divBoton = document.createElement('div');
    divBoton.className = 'text-center';
    //divBoton.innerHTML = `<button class="btn btn-primary" id="btnEnviarDocs" onclick="enviarArchivos(${idProceso},event)">Cargar documentos</button>`;
    document.querySelector('#form-documentos').innerHTML = htmlArchivos;
    document.querySelector('#form-documentos').appendChild(divBoton);
    htmlProceso += '</table>';
    document.querySelector('#infoProceso').innerHTML = htmlProceso;
    verificarDocumentos(idProceso);
}
/**Verificar si han sido subidos todos los documentos */
const verificarDocumentos = async function(idProceso){
    let dataToSend = new FormData();
    dataToSend.append('idProceso', idProceso);
    let res = await makeOptionsFetch(`${globalUrl}/verificar-todos-los-documentos`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
    if(res == 'subidos'){
        document.getElementById('btnEnviarDocs').disabled = true;
    }
}
/**Subir documentos */
const enviarArchivos = async function(idProceso,elemento,e){
    e.preventDefault();
    $('#form-documentos').preloader();
    let nombreArchivo = elemento.getAttribute('id').split('-')[1];
    let inputArchivo = document.getElementById(nombreArchivo);
    if(inputArchivo.value == null || inputArchivo.value == '' || inputArchivo.value == undefined){ 
        Swal.fire('Oops!','Hace falta el documento')
        .then((value)=>{
            $('#form-documentos').preloader('remove');
        })
    }else{
        let dataToSend = new FormData();
        dataToSend.append('idProceso',idProceso); 
        dataToSend.append('idTercero',document.getElementById('idTercero').value);
        let archivo = document.getElementById(inputArchivo.getAttribute('id')).files[0];
        dataToSend.append('nombre',inputArchivo.getAttribute('id'));
        dataToSend.append(inputArchivo.getAttribute('id'),archivo);
        dataToSend.append('idDocumento',document.getElementById('id_'+inputArchivo.getAttribute('id')).value);
        let res = await makeOptionsFetch(`${globalUrl}/subir-documentos-soporte`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
        console.log(res);
        if(res.success){
            $('#form-documentos').preloader('remove');
            let padre = document.getElementById(inputArchivo.getAttribute('id')).parentNode;
            let alert = document.createElement('div');
            alert.innerHTML = 'Documento cargado <i class="fas fa-check"></i>'; alert.className = 'alert alert-success text-center';
            padre.appendChild(alert);
            padre.removeChild(document.getElementById(inputArchivo.getAttribute('id')));
            mostrarVistas(idProceso,'docs soporte');
            return true;
        }else{
            $('#form-documentos').preloader('remove');
            Swal.fire('Oops!','No se ha cargado el documento','error')
        }
    }
}
/**---------------------------------------------------------------- */
/**---vista documentos de soporte aprobación */
/**vista verificar documentos de soporte */
const revisarDocumentosSubidos = async function(idProceso){
    const formatter = new Intl.NumberFormat('en-US',{
        style:'currency',
        currency:'USD',
        minimumFractionDigits: 0
    });
    let dataToSend = new FormData();
    dataToSend.append('idProceso', idProceso); dataToSend.append('accion','checkDocs');
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-proceso`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
    console.log(res);
    let htmlInfo = ''; htmlDocs = '';
    res.proceso.forEach(item=>{
        htmlInfo += `<div class="table-responsive">
                        <table class="table table-sm" id="tablaInfoProceso">
                        <thead>
                            <tr>
                                <th>Proceso #</th>
                                <th>Documento</th>
                                <th>Tercero</th>
                                <th>Valor solicitado</th>
                                <th>Fecha solicitud</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>${item.IdProceso}</td>
                                <td>${item.DocumentoTercero}</td>
                                <td>${item.NombresTercero} ${item.ApellidosTercero}</td>
                                <td>${formatter.format(item.ValorCreditoSolicitado)}</td>
                                <td>${item.FechaCreacion}</td>
                            </tr>
                        </tbody>
                        </table>
                    </div>`;
    });
    document.getElementById('infoProcesoCheckDocs').innerHTML = htmlInfo;
    if(res.proceso[0].DocumentosCargados == null || res.proceso[0].DocumentosCargados == ''){
        htmlDocs += '<h5 class="display-5">No se han cargado documentos aún</h5>';
    }else{
        let cargadosYnocargados = await verificarDocumentosAceptados(idProceso);
        htmlDocs += `<div class="row">`;
        res.documentos.forEach((item,index)=>{
            let nombreArchivoSepararado = item.NombreDocumento.split(' ');
            let valor = '';
            nombreArchivoSepararado.forEach((item2,index2)=>{
                if(index2 > 0){
                    let palabra = item2.split('');
                    let toMayus = '';
                    palabra.forEach((item3,index3)=>{
                        if(index3 == 0){
                            toMayus += item3.toUpperCase();
                        }else{
                            toMayus += item3;
                        }
                    });
                    valor += toMayus;
                }else{
                    valor += item2;
                }
            });
            let ruta = `${globalUrl}/ver-documento-soporte/${item.NombreDocumento}/${idProceso}`;
            // if(item.IdDocumentoSolicitado == 2){
            //     //ruta = `${globalUrl}/${res.tratamiento[0].RutaFormato}`;
            //     ruta = `${globalUrl}/tratamiento_datos/`;
            // }else{
            //     ruta = `${globalUrl}/${item.NombreDocumento}`;
            //     //ruta = `${globalUrl}/storage/documentos-soporte-${res.proceso[0].DocumentoTercero}-${res.proceso[0].IdProceso}/${res.proceso[0].DocumentoTercero}-${idProceso}-${valor}.pdf`;
            // }
            let propiedadBoton = false;
            let botonAceptar = `<button class="btn btn-success btn-acept-docs" onclick="aceptarDocumentos(${idProceso},${item.IdDocumentoSolicitado})" title="Aprobar documento">
                            <i class="fas fa-check"></i>
                        </button>`;
            let botonCancelar = `<button class="btn btn-danger btn-acept-docs" onclick="rechazarDocumentos(${idProceso},${item.IdDocumentoSolicitado})" title="Rechazar documento" disabled>
                                    <i class="fas fa-cancel"></i>
                                </button>`;
            cargadosYnocargados.aprobados.forEach(itemDoc=>{
                if(item.IdDocumentoSolicitado == itemDoc){
                    botonAceptar = `<button class="btn btn-success btn-acept-docs" onclick="aceptarDocumentos(${idProceso},${item.IdDocumentoSolicitado})" title="Aprobar documento" disabled>
                                    <i class="fas fa-check"></i>
                                </button>`;
                    botonCancelar = `<button class="btn btn-danger btn-acept-docs" onclick="rechazarDocumentos(${idProceso},${item.IdDocumentoSolicitado})" title="Rechazar documento">
                                        <i class="fas fa-cancel"></i>
                                    </button>`;
                }
            });
            let botonRevisarDoc = `<a class="btn btn-primary" onclick="Swal.fire('Oops!','Este documento no ha sido cargado','warning');">
                                        <i class="fas fa-file-pdf"></i> Revisar documento
                                    </a>`;
            cargadosYnocargados.cargados.forEach(itemDoc=>{
                if(item.IdDocumentoSolicitado == itemDoc){
                    botonRevisarDoc = `<a class="btn btn-primary" href="${ruta}" target=_blank">
                                            <i class="fas fa-file-pdf"></i> Revisar documento
                                        </a>`;
                }
            });
            htmlDocs += `<div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 mb-2">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>${ item.NombreDocumento }</label>
                                            <div class="d-grid mb-1">
                                                ${botonRevisarDoc}
                                            </div>
                                            <div class="text-center">
                                                ${botonAceptar}
                                                ${botonCancelar}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
        });
        htmlDocs += `</div>`;    
    }
    document.getElementById('listaDocumentosCheckDocs').innerHTML = htmlDocs;
}
//verificar que documentos ya han sido aprobados
const verificarDocumentosAceptados = async function(idProceso){
    let dataToSend = new FormData();
    dataToSend.append('idProceso',idProceso); dataToSend.append('action','verify');
    let response = await makeOptionsFetch(`${globalUrl}/gestion-documentos-proceso`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
    return response;
}
//aprobar el documento
const aceptarDocumentos = async function(idProceso,idDocumento){
    let dataToSend = new FormData();
    dataToSend.append('idProceso',idProceso); dataToSend.append('idDocumento',idDocumento); dataToSend.append('action','aceptar');
    let res = await makeOptionsFetch(`${globalUrl}/gestion-documentos-proceso`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
    if(res == 'success'){
        Swal.fire('Correcto!','El documento ha sido aprobado','success')
        .then((value)=>{
            if(value.isConfirmed){
                let data = {
                    idProceso:idProceso,
                    action:'verify'
                };
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token-lista-procesos"]').attr('content')
                    }
                });
                $.ajax({url:`${globalUrl}/gestion-documentos-proceso`,type:'post',dataType:'json',data:data,
                    success:function(response){
                        if(response.respuesta == 'faltan'){
                            revisarDocumentosSubidos(idProceso);
                        }else{
                            let data = {
                                idProceso:idProceso,
                                estado:4
                            };
                            $.ajaxSetup({
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token-lista-procesos"]').attr('content')
                                }
                            });
                            $.ajax({
                                url:`${globalUrl}/editar-estado-proceso`,
                                type:'post',
                                dataTYPE:'JSON',
                                data:data,
                                success:function(response){
                                    if(response.res == 'edited'){
                                        Swal.fire({
                                            title:'Perfecto!',
                                            text:'Todos los documentos han sido cargados y aprobados se pasará a espera de aprobación de crédito',
                                            icon:'success',
                                            confirmButtonText:'Entendido'
                                        }).then((value)=>{
                                            location.reload();
                                        })
                                    }
                                },
                                error:function(xhr){
                                    console.log(xhr);
                                }
                            });
                        }
                    },
                    error:function(xhr){
                        console.log(xhr);
                    }
                })
            }
        })
    }
}
//rechazar el documento
const rechazarDocumentos = async function(idProceso,idDocumento){
    let dataToSend = new FormData();
    dataToSend.append('idProceso',idProceso); dataToSend.append('idDocumento',idDocumento); dataToSend.append('action','rechazar');
    let res = await makeOptionsFetch(`${globalUrl}/gestion-documentos-proceso`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
    if(res == 'success'){
        Swal.fire('Correcto!','El documento ha sido rechazado, se deberá subir nuevamente','success')
        .then((value)=>{
            if(value.isConfirmed){
                revisarDocumentosSubidos(idProceso);
            }
        })
    }
}
/**Mostrar periodo de credito según edad */
const mostrarCuotas = async function(idTercero,cuotas){
    let dataToSend = new FormData();
    if(idTercero != ''){
        dataToSend.append('idTercero',idTercero);
    }
    let res = await makeOptionsFetch(`${globalUrl}/verificar-edad-tercero`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
    let longitudSelect = 120;
    if(res.y >= 75 && res.y <= 80){
        longitudSelect = 84;
    }else if(res.y > 80 && res.y <= 85){
        longitudSelect = 72;
    }else if(res.y > 85){
        longitudSelect = 48;
    }
    let html = '<option value="0">Elija número de cuotas...</option>';
    for(let i=1; i <= longitudSelect; i++){
        if(cuotas == i){
            html += `<option value="${i}" selected>${i}</option>`;
        }else{
            html += `<option value="${i}">${i}</option>`;
        }
    }
    return html;
}
/**---------------------------------------------------------------- */
/**---vista aprobacion de credito */
const mostrarInfoCredito = async function(idProceso){
    let dataToSend = new FormData();
    dataToSend.append('idProceso',idProceso); dataToSend.append('accion','approveCredit');
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-proceso`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
    document.getElementById('btnAprobarCredito').style.display = 'inline-block';
    document.getElementById('btnRechazarCredito').style.display = 'inline-block';
    document.getElementById('btnEditarCredito').style.display = 'inline-block';
    document.getElementById('btnDescargarHistorial').style.display = 'inline-block';
    //document.getElementById('textoEstado').style.display = 'none';
    //idProceso para los botones
    document.getElementById('btnAprobarCredito').setAttribute('onclick',`accionCredito(${idProceso},'aprobar')`);
    document.getElementById('btnRechazarCredito').setAttribute('onclick',`accionCredito(${idProceso},'rechazar')`);
    document.getElementById('btnEditarCredito').setAttribute('onclick',`accionCredito(${idProceso},'editar')`);
    document.getElementById('btnDescargarHistorial').setAttribute('onclick',`accionCredito(${idProceso},'download')`);
    //inhabilitar botones si el proceso ya ha sido rechazado o aprobado
    if(res.proceso[0].EstadoProceso == 0 || res.proceso[0].EstadoProceso == 5){
        document.getElementById('btnAprobarCredito').setAttribute('disabled', 'disabled');
        document.getElementById('btnRechazarCredito').setAttribute('disabled', 'disabled');
        // document.getElementById('btnAprobarCredito').style.display = 'none';
        // document.getElementById('btnRechazarCredito').style.display = 'none';
        // document.getElementById('btnEditarCredito').style.display = 'none';
        // document.getElementById('textoEstado').style.display = 'block';
    }
    let htmlInfoPersonal = '<table class="table table-sm table-striped">';
    let htmlInfoFinanciera = '<div class="row">';
    let htmlInfoDocuments = '<table class="table table-sm table-striped">';
    let htmlTablaInfo = '';
    let optionSelect = await mostrarCuotas(res.proceso[0].IdTercero,res.proceso[0].NumeroCuotas);
    res.proceso.forEach(element=>{
        htmlInfoPersonal += `<tr><th>Documento : </th><td>${element.DocumentoTercero}</td></tr>
                            <tr><th>Nombre : </th><td>${element.NombresTercero} ${element.ApellidosTercero}</td></tr>
                            <tr><th>Fecha Nacimiento : </th><td>${element.FechaNacimientoTercero}</td></tr>
                            <tr><th>Teléfono : </th><td>${element.TelefonoTercero}</td></tr>
                            <tr><th>Correo electrónico : </th><td>${element.EmailTercero}</td></tr>
                            <tr><th>Dirección : </th><td>${element.DireccionDomicilioTercero}</td></tr>
                            <tr><th>Ciudad : </th><td>${element.NombreMunicipio}, ${element.NombreDepartamento}</td></tr>`;
        htmlInfoFinanciera += `<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 form-group">
                                <label>Pagaduría</label>
                                <input class="form-control form-control-sm mb-1" id="pagaduriaNombre" name="pagaduriaNombre" placeholder="" value="${element.NombrePagaduria}" disabled>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 form-group">
                                <label>Ingresos Básicos</label>
                                <input class="form-control form-control-sm mb-1" id="ingresosTercero" name="ingresosTercero" placeholder="" value="${element.IngresosTercero}" disabled onkeypress="return noStrangeCharacters(event)">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 form-group">
                                <label>Crédito solicitado</label>
                                <input class="form-control form-control-sm mb-1" id="solicitadoProceso" name="solicitadoProceso" placeholder="" value="${element.ValorCreditoSolicitado}" disabled onkeypress="return noStrangeCharacters(event)">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 form-group">
                                <label>Cupo Aprobado</label>
                                <input class="form-control form-control-sm mb-1" id="cupoProceso" name="cupoProceso" placeholder="" value="${element.CupoDisponible}" disabled onkeypress="return noStrangeCharacters(event)">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 form-group">
                                <label>Plazo del crédito</label>
                                <select class="form-select form-select-sm" name="plazoProceso" id="plazoProceso" disabled>
                                    ${optionSelect}
                                </select>
                            </div>`;
        htmlTablaInfo += `<tr>
                            <td><strong>Cálculo del cupo :</strong>  $ ${element.ValoresOperacion}</td>
                        </tr>
                        <tr>
                            <td><strong>Valor Crédito :</strong> $ ${parseFloat(element.ValorCreditoSolicitado).toLocaleString('en',{style:'currency',currency:'COP'})}</td>
                        </tr>
                        <tr>
                            <td><strong>Valor Cuota Mensual :</strong> $ ${parseFloat(element.ValorCuota).toLocaleString('en',{style:'currency',currency:'COP'})}</td>
                        </tr>
                        <tr>
                            <td><strong>Número Cuotas :</strong> ${element.NumeroCuotas}</td>
                        </tr>
                        <tr>
                            <td><strong>Tasa Mensual :</strong> ${element.TasaInteres} %</td>
                        </tr>`;
    });
    res.documentos.forEach((item,index)=>{
        let nombreArchivoSepararado = item.NombreDocumento.split(' ');
        let valor = '';
        nombreArchivoSepararado.forEach((item2,index2)=>{
            if(index2 > 0){
                let palabra = item2.split('');
                let toMayus = '';
                palabra.forEach((item3,index3)=>{
                    toMayus += (index3 == 0)? item3.toUpperCase() : item3 ;
                });
                valor += toMayus;
            }else{
                valor += item2;
            }
        });
        let ruta = `${globalUrl}/ver-documento-soporte/${item.NombreDocumento}/${idProceso}`;
        // if(item.IdDocumentoSolicitado == 2){
        //     ruta = `${globalUrl}/storage/tratamiento_datos/${res.tratamiento[0].RutaFormato}`;
        // }else{
        //     ruta = `${globalUrl}/storage/documentos-soporte-${res.proceso[0].DocumentoTercero}-${idProceso}/${res.proceso[0].DocumentoTercero}-${idProceso}-${valor}.pdf`;
        // }
        htmlInfoDocuments += `<tr>
                                <th>${item.NombreDocumento} :</th>
                                <td>
                                    <a class="btn btn-primary btn-sm" href="${ruta}" target="_blank" title="${item.NombreDocumento}">
                                        <i class="fas fa-search"></i>
                                    </a>
                                </td>
                            </tr>`;
    });
    htmlInfoPersonal += '</table>';
    htmlInfoDocuments += '</table>';
    htmlInfoFinanciera += `</div>
                            <div class="text-center" id="btn-guardar-datos-financieros">
                                <button class="btn btn-success btn-sm" onclick="guardarDatos(${idProceso},'editar')">Actualizar</button>
                                <button class="btn btn-danger btn-sm" onclick="guardarDatos('','cancelar')">Cancelar</button>
                            </div>`;
    $('#plazoProceso option[value="'+res.proceso[0].NumeroCuotas+'"]').prop('selected', true);
    document.getElementById('personal-data').innerHTML = htmlInfoPersonal;
    document.getElementById('financial-data').innerHTML = htmlInfoFinanciera;
    document.getElementById('document-data').innerHTML = htmlInfoDocuments;
    document.getElementById('tablaInformacion').innerHTML = htmlTablaInfo;
}
/**Acciones de los botones de la vista de aprobación de crédito */
const accionCredito = async function(idProceso,accion){
    $.ajaxSetup({headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token-lista-procesos"]').attr('content')}});
    let token = $('meta[name="csrf-token-lista-procesos"]').attr('content');
    switch(accion){
        case 'aprobar':
            Swal.fire({
                title:'Espera!',
                text:'¿Se cambiará el estado del proceso a aprobado?',
                icon:'info',
                showCancelButton:true,
                confirmButtonText:'Sí, aprobar',
                cancelButtonText:'Cancelar',
            }).then(async function(value){
                if(value.isConfirmed){
                    let dataToSend = new FormData();
                    dataToSend.append('idProceso',idProceso); dataToSend.append('accion','approveCredit');
                    let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-proceso`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
                    $('#modalCuerpoCorreo').modal('show');
                    document.getElementById('idProcesoCorreo').value = idProceso;
                    document.getElementById('accionCorreo').value = 'aprobar';
                    res['proceso'].forEach(element=>{
                        document.getElementById('asuntoCorreo').value = `Libranza aprobada: ${element.NombresTercero} ${element.ApellidosTercero} (${element.NombrePagaduria})`;
                        document.getElementById('textoCorreo').value = `Se ha aprobado LIBRANZA ${element.NombrePagaduria} a nombre de ${element.NombresTercero} ${element.ApellidosTercero}, identificado con C.C. ${element.DocumentoTercero} de la siguiente manera:`;
                    });
                }
            });
        break;
        case 'rechazar':
            Swal.fire({
                title:'Espera!',
                text:'¿Deseas cancelar el proceso?',
                icon:'warning',
                showCancelButton:true,
                confirmButtonText:'Sí, cancelar',
                cancelButtonText:'No, salir',
            }).then(async function(value){
                if(value.isConfirmed){
                    let dataToSend = new FormData();
                    dataToSend.append('idProceso',idProceso); dataToSend.append('accion','approveCredit');
                    let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-proceso`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
                    $('#modalCuerpoCorreo').modal('show');
                    document.getElementById('idProcesoCorreo').value = idProceso;
                    document.getElementById('accionCorreo').value = 'rechazar';
                    $('#motivosRechazo').parent().css('display','inline-block !important');
                    res['proceso'].forEach(element=>{
                        document.getElementById('asuntoCorreo').value = `Libranza rechazada: ${element.NombresTercero} ${element.ApellidosTercero} (${element.NombrePagaduria})`;
                        document.getElementById('textoCorreo').value = `Se ha rechazado LIBRANZA ${element.NombrePagaduria} a nombre de ${element.NombresTercero} ${element.ApellidosTercero}, identificado con C.C. ${element.DocumentoTercero}, motivos:`;
                    });
                }
            });
        break;
        case 'editar':
            document.getElementById('solicitadoProceso').disabled = false;
            document.getElementById('cupoProceso').disabled = false; document.getElementById('cupoProceso').focus();
            document.getElementById('plazoProceso').disabled = false;
            document.getElementById('btn-guardar-datos-financieros').style.display = 'block';
        break;
        case 'download':
            window.open(`${globalUrl}/descargar-info-credito/${idProceso}`);
        break;
    }
}
/**Enviar correo con mensaje de aprobacion o cancelacion de credito segun calculo y revisiones */
const enviarEmail = async function(e){
    e.preventDefault();
    let dataToSend = new FormData();
    dataToSend.append('idProceso',document.getElementById('idProcesoCorreo').value);
    dataToSend.append('accion',document.getElementById('accionCorreo').value);
    dataToSend.append('asunto',document.getElementById('asuntoCorreo').value);
    dataToSend.append('texto',document.getElementById('textoCorreo').value);
    $('#modalCuerpoCorreo').preloader();
    let res = await makeOptionsFetch(`${globalUrl}/enviar-email-credito`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
    if(res == 'ok'){
        $('#modalCuerpoCorreo').preloader('remove');
        Swal.fire({title:'Perfecto!',text:'Correo enviado',icon:'success',confirmButtonText:'Entendido'})
        .then(async function(value){
            Swal.fire({text:'Actualizando estado de proceso...',timer:2000})
            $('#modalCuerpoCorreo').modal('hide');
            $('#aprobacion-creditos').preloader();
            if(document.getElementById('accionCorreo').value == 'aprobar'){
                dataToSend.append('estado',5);
            }else if(document.getElementById('accionCorreo').value == 'rechazar'){
                dataToSend.append('estado',0);
            }
            let res = await makeOptionsFetch(`${globalUrl}/editar-estado-proceso`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
            if(res.res == 'edited'){
                $('#aprobacion-creditos').preloader('remove');
                Swal.fire({title:'Listo!',text:'Proceso actualizado',icon:'success',confirmButtonText:'Entendido'})
                .then((value)=>{
                    if(value.isConfirmed){
                        location.reload();
                    }
                })
            }
        })
    }
}
const guardarDatos = async function(idProceso='',action=''){
    switch(action){
        case 'editar':
            let dataToSend = new FormData();
            dataToSend.append('periodoCredito',document.getElementById('plazoProceso').value);
            dataToSend.append('nuevoCupo',document.getElementById('cupoProceso').value);
            dataToSend.append('idProceso',idProceso); 
            dataToSend.append('valorCredito',document.getElementById('solicitadoProceso').value);
            if(document.getElementById('plazoProceso').value == '0'){
                Swal.fire('Oops!','No se ha seleccionado un periodo para el crédito','info')
            }else if(document.getElementById('cupoProceso').value == '' || document.getElementById('cupoProceso').value == '0'){
                Swal.fire('Oops!','Hace falta el valor del cupo','info')
            }else if(document.getElementById('solicitadoProceso').value == '' || document.getElementById('solicitadoProceso').value == '0'){
                Swal.fire('Oops!','Hace falta el valor solicitado','info')
            }else{
                let res = await makeOptionsFetch(`${globalUrl}/editar-proceso`,dataToSend,'post',$('meta[name="csrf-token-lista-procesos"]').attr('content'));
                if(res == 'ok'){
                    Swal.fire({title:'Perfecto!',text:'Se han editado los datos del proceso',icon:'success',confirmButtonText:'Entendido'})
                    .then((value)=>{
                        if(value.isConfirmed){
                            guardarDatos(idProceso,'cancelar');
                        }
                    });
                }
            }
        break;
        case 'cancelar':
            document.getElementById('solicitadoProceso').disabled = true;
            document.getElementById('cupoProceso').disabled = true;
            document.getElementById('plazoProceso').disabled = true;
            document.getElementById('btn-guardar-datos-financieros').style.display = 'none';
        break;
    }
}
function formatNumber(n) {
    // format number 1000000 to 1,234,567
    return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",")
}