/**Mostrar formulario de terceros para empezar a ingresar los datos */
window.onload = function(){
    mostrarPagadurias();
    mostrarTiposDocumentos();
    mostrarDepartmentos();
    $(function(){
        $('#fechaNacimiento, #fechaExpedicion').datetimepicker({
            format:'Y/m/d',
            timepicker:false,
            datepicker:true
        });
    });
    /**Verificar fecha de nacimiento del cliente para evitar registrar menores de edad o años mayores al actual */
    document.getElementById('fechaNacimiento').addEventListener('blur', function(){
        if(this.value != ''){
            let fechaNacimiento = this.value;
            let anioNacimiento = fechaNacimiento.split('/')[0];
            let fechaHoy = new Date();
            let anio = fechaHoy.getFullYear();
            let edad = (parseInt(anio) - parseInt(anioNacimiento));
            console.log(anioNacimiento);
            if(edad >= 0 && edad <= 18){
                Swal.fire({
                    title:'Oops!',
                    text:'Esta fecha indica que la persona es menor de edad. ¿Cancelar registro?',
                    icon:'warning',
                    confirmButtonText:'Si, cancelar',
                    showCancelButton:true,
                    cancelButtonText:'No, modificar',
                    allowEscapeKey: false,
                    allowOutsideClick: false
                }).then((value)=>{
                    if(value.isConfirmed){
                        window.location = globalUrl+'/';
                    }else{
                        this.value = '';
                        this.focus();
                    }
                });
            }else if(anioNacimiento > anio){
                Swal.fire({
                    title:'Oops!',
                    text:'El año de nacimiento no puede ser mayor al actual',
                    icon:'info',
                    confirmButtonText:'Entendido',
                    allowEscapeKey: false,
                    allowOutsideClick: false
                }).then((value)=>{
                    if(value.isConfirmed){
                        this.value = '';
                        this.focus();
                    }
                });
            }
        }
    });
}
/**Regresar al formulario anterior */
const volverFormularioPersonal = async function(toForm,actualForm){
    document.querySelector(`#${toForm}`).style = 'display:block;';
    document.querySelector(`#${actualForm}`).style = "display:none;";
}
/**Mostrar Pagadurias en select */
const mostrarPagadurias = async function(){
    let dataToSend = new FormData();
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-pagadurias`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    html = '<option value="">Elija una pagaduría...</option>';
    res.pagadurias.forEach(item=>{
        html += `<option value="${item.IdPagaduria}">${item.NombrePagaduria}</option>`;
    })
    document.querySelector('#pagaduriaTercero').innerHTML = html;
}
/**Mostrar tipos de documentos en select */
const mostrarTiposDocumentos = async function(){
    let dataToSend = new FormData();
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-tipos-documentos`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    html = '<option value="">Tipo de documento...</option>';
    res.tipos.forEach(item=>{
        html += `<option value="${item.IdTipoDocumento}">${item.NombreTipoDocumento}</option>`;
    })
    document.querySelector('#tipoDocumento').innerHTML = html;
}
/**Mostrar departamentos en select */
const mostrarDepartmentos = async function(){
    let dataToSend = new FormData();
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-departamentos`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    html = '<option value="">Seleccione Departamento...</option>';
    res.departamentos.forEach(item=>{
        html += `<option value="${item.IdDepartamento}">${item.NombreDepartamento}</option>`;
    });
    document.querySelector('#departamentoResidencia').innerHTML = html;
}
/**Mostra ciudades en select según departamento */
const mostrarCiudades = async function(idDepartment){
    let dataToSend = new FormData();
    dataToSend.append('idDepartamento',idDepartment);
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-ciudades`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    html = '<option value="">Seleccione Municipio...</option>';
    res.municipios.forEach(item=>{
        html += `<option value="${item.IdMunicipio}">${item.NombreMunicipio}</option>`;
    })
    document.querySelector('#ciudadResidencia').innerHTML = html;
}
/**Validar si el tercero ya tiene un procesos iniciado */
const validarDocumento = async function(documento){
    let dataToSend = new FormData();
    dataToSend.append('documento',documento);
    let res = await makeOptionsFetch(`${globalUrl}/validar-documento`,dataToSend,'post',$('meta[name="csrf-token-menus"]').prop('content'));
    if(res.Tercero && !res.DocumentosCargados && !res.Proceso){
        let textoAlerta = '';
        textoAlerta += (res.Proceso)? 'Esta persona ya tiene un proceso iniciado' : 'Esta persona ya estába siendo registrada, trayendo datos...' ;
        Swal.fire({
            title:'Aviso!',
            text:textoAlerta,
            icon:'info',
            confirmButtonText:'Entendido',
            allowOutsideClick:false,
            allowEscapeKey:false
        }).then((value)=>{
            if(value.isConfirmed){
                $('#form-datos-personales').preloader();
                res.Tercero.forEach(element=>{
                    $('#tipoDocumento option[value="'+element.IdTipoDocumento+'"]');
                    document.getElementById('nombres').value = element.NombresTercero;
                    document.getElementById('apellidos').value = element.ApellidosTercero;
                    document.getElementById('fechaExpedicion').value = element.FechaExpedicionDocumento;
                    document.getElementById('lugarExpedicion').value = element.LugarExpedicionDocumento;
                    document.getElementById('fechaNacimiento').value = element.FechaNacimientoTercero;
                    document.getElementById('telefonoTercero').value = element.TelefonoTercero;
                    document.getElementById('emailTercero').value = element.EmailTercero;
                    document.getElementById('direccionTercero').value = element.DireccionDomicilioTercero;
                    $('#departamentoResidencia option[value="'+element.DepartamentoDomicilioTercero+'"]').prop('selected',true);
                    mostrarCiudades(element.DepartamentoDomicilioTercero);
                    $('#ciudadResidencia option[value="'+res.Tercero[0].CiudadDomicilioTercero+'"]').prop('selected',true);
                    document.getElementById('idTercero').value = element.IdTercero;
                });
                $('#form-datos-personales').preloader('remove');
                if(res.Proceso){
                    res.Proceso.forEach(element=>{
                        document.getElementById('ingresosMensuales').value = res.Tercero[0].IngresosTercero;
                        $('#pagaduriaTercero option[value="'+res.Tercero[0].IdPagaduria+'"]').prop('selected', true);
                        document.getElementById('valorSolicitado').value = element.ValorCreditoSolicitado;
                        $('#numeroCuotas option[value="'+element.NumeroCuotas+'"]').prop('selected', true);
                        document.getElementById('idProcesoHidden').value = element.IdProceso;
                    });
                }
            }
        });
    }else if(res.Tercero && res.Proceso && res.Estado == 1){
        Swal.fire({
            title:'Aviso!',
            text:'Se ha iniciado un proceso para esta persona, datos financieros pendientes ...',
            icon:'info',
            confirmButtonText:'Entendido',
            allowOutsideClick:false,
            allowEscapeKey:false
        }).then((value)=>{
            if(value.isConfirmed){
                document.getElementById('idTercero').value = res.Tercero[0].IdTercero;
                mostrarCuotas();
                res.Proceso.forEach(element=>{
                    document.getElementById('ingresosMensuales').value = res.Tercero[0].IngresosTercero;
                    $('#pagaduriaTercero option[value="'+res.Tercero[0].IdPagaduria+'"]').prop('selected', true);
                    document.getElementById('valorSolicitado').value = element.ValorCreditoSolicitado;
                    $('#numeroCuotas option[value="'+element.NumeroCuotas+'"]').prop('selected', true);
                    document.getElementById('idProcesoHidden').value = element.IdProceso;
                });
                document.getElementById('form-datos-personales').style.display = 'none';
                document.getElementById('form-datos-financieros').style.display = 'block';
            }
        });
    }else if(res.DocumentosCargados == 0){
        let textoAlerta = '';
        textoAlerta += 'Esta persona ya ha sido registrada, pero no se ha aceptado el tratamiento de datos...';
        Swal.fire({
            title:'Aviso!',
            text:textoAlerta,
            icon:'info',
            confirmButtonText:'Entendido',
            allowOutsideClick:false,
            allowEscapeKey:false
        }).then((value)=>{
            if(value.isConfirmed){
                $('#form-datos-personales').preloader();
                res.Tercero.forEach(element=>{
                    $('#tipoDocumento option[value="'+element.IdTipoDocumento+'"]').prop('selected', true);
                    document.getElementById('nombres').value = element.NombresTercero;
                    document.getElementById('apellidos').value = element.ApellidosTercero;
                    document.getElementById('fechaExpedicion').value = element.FechaExpedicionDocumento;
                    document.getElementById('lugarExpedicion').value = element.LugarExpedicionDocumento;
                    document.getElementById('fechaNacimiento').value = element.FechaNacimientoTercero;
                    document.getElementById('telefonoTercero').value = element.TelefonoTercero;
                    document.getElementById('emailTercero').value = element.EmailTercero;
                    document.getElementById('direccionTercero').value = element.DireccionDomicilioTercero;
                    $('#departamentoResidencia option[value="'+element.DepartamentoDomicilioTercero+'"]').prop('selected',true);
                    mostrarCiudades(element.DepartamentoDomicilioTercero);
                    $('#ciudadResidencia option[value="'+res.Tercero[0].CiudadDomicilioTercero+'"]').prop('selected',true);
                    document.getElementById('idTercero').value = element.IdTercero;
                });
                $('#form-datos-personales').preloader('remove');
                if(res.Proceso){
                    res.Proceso.forEach(element=>{
                        document.getElementById('ingresosMensuales').value = res.Tercero[0].IngresosTercero;
                        $('#pagaduriaTercero option[value="'+res.Tercero[0].IdPagaduria+'"]').prop('selected', true);
                        document.getElementById('valorSolicitado').value = element.ValorCreditoSolicitado;
                        $('#numeroCuotas option[value="'+element.NumeroCuotas+'"]').prop('selected', true);
                        document.getElementById('idProcesoHidden').value = element.IdProceso;
                    });
                }
                document.getElementById('form-datos-personales').style.display = 'none';
                document.getElementById('form-tratamiento-datos').style.display = 'block';
            }
        });
    }else if(res.Estado == 2){
        Swal.fire({
            title:'Aviso!',
            text:'El usuario tiene un proceso iniciado, y se encuentra en estado 2 (Consulta Centrales de Riesgo)',
            icon:'info',
            confirmButtonText:'Entendido',
            allowOutsideClick:false,
            allowEscapeKey:false
        }).then((value)=>{
            if(value.isConfirmed){
                window.location = globalUrl+'/lista-procesos';
            }
        });
    }else if(res.Estado == 3 || res.Estado == 4){
        Swal.fire({
            title:'Aviso!',
            text:'El usuario tiene un proceso iniciado, y se encuentra en subida y aprobación de documentos',
            icon:'info',
            confirmButtonText:'Entendido',
            allowOutsideClick:false,
            allowEscapeKey:false
        }).then((value)=>{
            if(value.isConfirmed){
                window.location = globalUrl+'/lista-procesos';
            }
        });
    }
}
/**Enviar datos personales y si se guardan accede al siguiente formulario */
const enviarDatosPersonales = async function(e){
    e.preventDefault();
    $('#form-datos-personales').preloader();
    let fechaInputExpedicion = document.querySelector('#fechaExpedicion').value.split('/');
    let fechaInputNacimiento = document.querySelector('#fechaNacimiento').value.split('/');
    let fechaExpedicion = fechaInputExpedicion[0]+'-'+fechaInputExpedicion[1]+'-'+fechaInputExpedicion[2];
    let fechaNacimiento = fechaInputNacimiento[0]+'-'+fechaInputNacimiento[1]+'-'+fechaInputNacimiento[2];
    errorElements = document.getElementsByClassName('invalid-feedback');
    for(let i =0; i < errorElements.length; i++){
        errorElements[i].style.display = 'none';
    }
    idTercero = (document.querySelector('#idTercero').value)? document.querySelector('#idTercero').value : '' ;
    let form = document.getElementById('form-datos');
    let dataToSend = new FormData(form);
    dataToSend.append('idTercero',idTercero);
    dataToSend.append('fechaNacimiento',fechaNacimiento); dataToSend.append('fechaExpedicion',fechaExpedicion);
    let res = await makeOptionsFetch(`${globalUrl}/guardar-datos-personales`,dataToSend,'post',$('meta[name="csrf-token-form-personal-data"]').attr('content'))
    if(res.errors){
        $('#form-datos-personales').preloader('remove');
        showErrors(res);
    }else{
        $('#form-datos-personales').preloader('remove');
        Swal.fire('Correcto!','Información guardada correctamente','success')
        .then((value)=>{
            if(value.isConfirmed){
                document.querySelector('#idTercero').value = res.data['id'];
                document.querySelector('#form-datos-personales').style = 'display:none;';
                document.querySelector('#form-datos-financieros').style = "display:block;";
                mostrarCuotas();
            }
        });
    }
}
/**Mostrar periodo de credito según edad */
const mostrarCuotas = async function(idProceso=''){
    let dataToSend = new FormData();
    if(document.querySelector('#idTercero').value != ''){
        dataToSend.append('idTercero',document.querySelector('#idTercero').value);
    }
    let res = await makeOptionsFetch(`${globalUrl}/verificar-edad-tercero`,dataToSend,'post',$('meta[name="csrf-token-form-financial-data"]').attr('content'));
    let longitudSelect = 120;
    if(res.y > 70){
        longitudSelect = 48;
    }else if(res.y < 18){
        document.getElementById('show-inputs-btn').setAttribute('disabled',true);
        longitudSelect = 0;
        Swal.fire({title:'Oops!',text:'Esta persona no es mayor de edad',icon:'error',allowEscapeKey:false,allowOutsideClick:false})
        .then((value)=>{
            if(value.isConfirmed){
                window.location = globalUrl+'/';
            }
        });
    }
    let html = '<option value="0">Elija número de cuotas...</option>';
    for(let i=1; i <= longitudSelect; i++){
        html += `<option value="${i}">${i}</option>`;
    }
    document.getElementById('numeroCuotas').innerHTML = html;
}
/**Mostrar inputs según pagaduria y configuración de calculo de cupo */
const mostrarInputs = async function(){
    if(document.querySelector('#ingresosMensuales').value == '' || document.querySelector('#pagaduriaTercero').value == ''){
        Swal.fire({
            title:'Oops!',
            text:'Necesitamos el valor de los ingresos y la pagaduria',
            icon:'info',
            confirmButtonText:'Entendido',
            allowEscapeKey: false,
            allowOutsideClick: false
        }).then((value)=>{
            if(value.isConfirmed){
                document.querySelector('#ingresosMensuales').focus();
            }
        });
    }else if(document.getElementById('numeroCuotas').value == '0'){
        Swal.fire({
            title:'Oops!',
            text:'No se ha seleccionado un número de cuotas',
            icon:'info',
            confirmButtonText:'Entendido',
            allowEscapeKey: false,
            allowOutsideClick: false
        }).then((value)=>{
            if(value.isConfirmed){
                document.querySelector('#numeroCuotas').focus();
            }
        });
    }else{
        $('#form-datos-financieros').preloader();
        idTercero = (document.querySelector('#idTercero').value)? document.querySelector('#idTercero').value : '' ;
        let form = document.getElementById('form-financiero');
        let dataToSend = new FormData(form);
        dataToSend.append('idTercero',idTercero);
        let res = await makeOptionsFetch(`${globalUrl}/mostrar-config-inputs`,dataToSend,'post',$('meta[name="csrf-token-form-financial-data"]').attr('content'));
        let arrayConfig = [];
        res.forEach(element=>{
            element.Configuracion.split('|').forEach(item2=>{
                if(item2.length > 1){
                    arrayConfig.push(item2);
                }
            });
        });
        let html = `<p class="lead text-start">Deducciones y otros ingresos</p>`;
        document.querySelector('#inputs-config').innerHTML = html;
        /**Eliminar duplicados de la operacion para mostrar solo un input para cada valor*/
        let result = arrayConfig.filter((item,index)=>{
            return arrayConfig.indexOf(item) === index;
        });
        result.forEach(element=>{
            if(element != 'ingresos' && element != 'salarioMinimoMensual'){
                let input = document.createElement('input');
                input.className = 'form-control inputs-values-config';
                if(isNaN(element)){
                    arrayPalabra = element.split('');
                    let textInput = '';
                    arrayPalabra.forEach(letra=>{
                        if(letra === letra.toUpperCase()){
                            textInput += ' '+letra;
                        }else{
                            textInput += letra;
                        }
                    });
                    /**Creación de inputs e inserción en el DOM */
                    input.setAttribute('type','number');
                    input.setAttribute('id',element);
                    input.setAttribute('name',element);
                    input.setAttribute('placeholder','Digita '+textInput);
                    input.setAttribute('title',textInput);
                    document.querySelector('#inputs-config').appendChild(input);
                    document.querySelector('#btnSendValues').disabled = false;
                }
            }
        });
        $('#form-datos-financieros').preloader('remove');
    }
}
/**Enviar datos de contacto y si se guardan accede al siguiente formulario */
const enviarDatosFinancieros = async function(e){
    e.preventDefault();
    errorElements = document.getElementsByClassName('invalid-feedback');
    for(let i =0; i < errorElements.length; i++){
        errorElements[i].style.display = 'none';
    }
    let id = document.querySelector('#idTercero').value;
    let inputs = document.getElementsByClassName('inputs-values-config');
    let arrayValores = [];
    let arrayNombres = [];
    arrayNombres.push('ingresos');
    arrayValores.push(document.querySelector('#ingresosMensuales').value);
    for(let i = 0; i < inputs.length; i++){
        arrayNombres.push(inputs[i].getAttribute('id'));
        arrayValores.push(inputs[i].value);
    }
    let dataToSend = {
        idTercero:id,
        idPagaduria:document.querySelector('#pagaduriaTercero').value,
        idProceso:document.querySelector('#idProcesoHidden').value,
        ingresos:document.querySelector('#ingresosMensuales').value,
        valorSolicitado:document.querySelector('#valorSolicitado').value,
        numeroCuotas:document.querySelector('#numeroCuotas').value,
        nombres:arrayNombres,
        valores:arrayValores
    };
    $('#form-datos-financieros').preloader();
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token-form-financial-data"]').attr('content')
        }
    });
    $.ajax({
        url:`${globalUrl}/guardar-datos-financieros`,
        type:'post',
        dataType:'json',
        data:dataToSend,
        success:function(response){
            $('#form-datos-financieros').preloader('remove');
            document.querySelector('#btnSendValues').style = "display:none;";
            document.querySelector('#btnEditState').style = "display:inline-block;";
            document.querySelector('#idProcesoHidden').value = response.idProceso;
            let formatoMoneda = response.cupo.toLocaleString('en',{style:'currency',currency:'COP'});
            html = `<br>`;
            if(response.cupo > 0){
                html += `<h3 class="text-success">Cupo: $ ${formatoMoneda}</h3>`;
            }else{
                html += `<h3 class="text-danger">Cupo: $ ${formatoMoneda}</h3>`;
            }
            document.querySelector('#cupoDisponible').innerHTML = html;
            if(response.cupo > 0){
                Swal.fire({title:'Perfecto!',text:'El cliente tiene cupo disponible',icon:'success',allowEscapeKey: false,allowOutsideClick: false})
            }else{
                Swal.fire({title:'Oh no!',text:'El cliente no cuenta con cupo disponible',icon:'warning',allowEscapeKey: false,allowOutsideClick: false})
                .then((value)=>{
                    if(value.isConfirmed){
                        editarEstadoProceso(0);
                    }
                })
            }
        },
        error:function(xhr){
            console.log(xhr);
        }
    })
}
/**Cambiar la opción del select de pagaduria elimina los datos ingresados */
const cambiarPagaduria = function(){
    document.querySelector('#inputs-config').innerHTML = '';
    document.querySelector('#cupoDisponible').innerHTML = '';
    document.querySelector('#btnSendValues').style = "display:inline-block;";
    document.querySelector('#btnEditState').style = "display:none;";
}
/**En caso de que el cupo no sea positivo se pasará el proceso a estado 0=finalizado  */
const editarEstadoProceso = async function(state=''){
    $('#form-datos-financieros').preloader();
    idProceso = document.querySelector('#idProcesoHidden').value;
    let dataToSend = new FormData();
    dataToSend.append('idProceso',idProceso);
    if(state != ''){
        dataToSend.append('estado',state);
    }else{
        dataToSend.append('estado','pendiente');
    }
    let res = await makeOptionsFetch(`${globalUrl}/editar-estado-proceso`,dataToSend,'post',$('meta[name="csrf-token-form-financial-data"]').attr('content'));
    if(res.res == 'edited'){
        $('#form-datos-financieros').preloader('remove');
        Swal.fire({
            title:'Correcto!',
            text:'Información guardada correctamente.',
            icon:'success',
            confirmButtonText:'Entendido',
            allowEscapeKey: false,
            allowOutsideClick: false
        })
        .then((value)=>{
            if(value.isConfirmed){
                document.querySelector('#form-datos-financieros').style = 'display:none';
                document.querySelector('#form-tratamiento-datos').style = 'display:block';
            }
        });
    }
}
/**Manejo del tratamiento de datos */
const prepararAprobacionDatos = async function(type){
    let id = document.querySelector('#idTercero').value;
    let dataToSend = new FormData();
    dataToSend.append('idTercero',id);
    if(type == 'pdf'){
        document.querySelector('#form-upload-file').style = 'display:block;';
    }else if('email'){
        $('#modalCorreoTratamiento').modal('show');
        document.querySelector('#form-upload-file').style = 'display:none;';
    }
}
/**Descargar pdf del formato para imprimir */
const descargarPdf = function(){
    window.open(`${globalUrl}/docs/formato_tratamiento.PDF`,'_blank');
}
/**Subir archivo escaneado */
const subirArchivoTratamientoDatos = async function(e){
    e.preventDefault();

    // Obtener el archivo seleccionado
    const fileInput = document.querySelector('#form-file-tratamiento input[type="file"]');

    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        Swal.fire({
            title: 'Error!',
            text: 'Por favor selecciona un archivo antes de continuar.',
            icon: 'error',
            confirmButtonText: 'Ok'
        });
        return;
    }

    const file = fileInput.files[0];
    
    // Validar extensión del archivo
    const allowedExtensions = ['.pdf'];
    const fileName = file.name.toLowerCase();
    const fileExtension = fileName.substring(fileName.lastIndexOf('.'));

    if (!allowedExtensions.includes(fileExtension)) {
        Swal.fire({
            title: 'Formato no válido!',
            text: 'Solo se permiten archivos en formato PDF (.pdf)',
            icon: 'error',
            confirmButtonText: 'Ok'
        });
        // Limpiar el input file
        fileInput.value = '';
        return;
    }

    // Validar tipo MIME del archivo (validación adicional)
    if (file.type !== 'application/pdf') {
        Swal.fire({
            title: 'Formato no válido!',
            text: 'El archivo seleccionado no es un PDF válido.',
            icon: 'error',
            confirmButtonText: 'Ok'
        });
        // Limpiar el input file
        fileInput.value = '';
        return;
    }

    // Validar tamaño del archivo (máximo 2MB)
    const maxSize = 2 * 1024 * 1024; // 2MB en bytes
    if (file.size > maxSize) {
        Swal.fire({
            title: 'Archivo muy grande!',
            text: 'El archivo debe ser menor a 2MB.',
            icon: 'error',
            confirmButtonText: 'Ok'
        });
        // Limpiar el input file
        fileInput.value = '';
        return;
    }

    $('#form-tratamiento-datos').preloader();

    let idProceso = document.getElementById('idProcesoHidden').value;
    let form = document.getElementById('form-file-tratamiento');
    let dataToSend = new FormData(form);
    let id = document.querySelector('#idTercero').value;
    dataToSend.append('idTercero',id); dataToSend.append('idProceso',idProceso);
    let res = await makeOptionsFetch(`${globalUrl}/subir-archivo-tratamiento-datos`,dataToSend,'post',$('meta[name="csrf-token-form-approve-data"]').attr('content'));
    if(res.errors){
        $('#form-tratamiento-datos').preloader('remove');
        showErrors(res);
    }else{
        $('#form-tratamiento-datos').preloader('remove');
        Swal.fire({title:'Correcto!',text:res.success,icon:'success',allowEscapeKey: false,allowOutsideClick: false})
        .then((value)=>{
            if(value.isConfirmed){
                document.querySelector('#form-tratamiento-datos').style = 'display:none;';
                document.querySelector('#data-finished').style = "display:block;";
            }
        });
    }
}
/**Enviar datos de aceptación de tratamiento de datos */
const enviarEmail = async function(){
    $('#form-tratamiento-datos').preloader();
    let idProceso = document.getElementById('idProcesoHidden').value;
    let dataToSend = new FormData();
    dataToSend.append('idProceso', idProceso);
    let res = await makeOptionsFetch(`${globalUrl}/enviar-email-aprobacion-datos`,dataToSend,'post',$('meta[name="csrf-token-form-approve-data"]').attr('content'));
    if(res == 'ok'){
        $('#form-tratamiento-datos').preloader('remove');
        Swal.fire({
            title:'Perfecto!',
            text:'Correo enviado al tercero, verifica que sea aceptado el tratamiento de sus datos',
            icon:'success',
            confirmButtonText:'Entendido',
            allowEscapeKey: false,
            allowOutsideClick: false
        });
        $('#modalCorreoTratamiento').modal('hide');
        document.getElementById('form-tratamiento-datos').style.display = 'none';
        document.querySelector('#data-finished').style = "display:block;";
    }
}
/**Confirmar recarga de la página */
window.onbeforeunload = function(e){
    return true;
}