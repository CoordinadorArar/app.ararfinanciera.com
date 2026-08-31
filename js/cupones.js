window.onload = function(){
    crearTablaResponsiva('TableHistoryCupones');
    $(function(){
        $('#form-tercero-cupon [name=FechaLimiteCupon]').datetimepicker({
            format:'Y/m/d',
            timepicker:false,
            datepicker:true
        });
    });
    /** */
    $('#btnGenerarArchivo').click(async function(element){
        this.style.display = '';
        this.setAttribute('disabled', 'disabled');
        // $(this).addClass('display');
        // $(this).attr('disabled', true);
        var data = [];
        var contador = 0;
        $('#TableContent tbody tr').each(function(response){
            contador++;
            data.push({
                Consecutivo: contador,
                IdOperacion: $(this).find('td').eq(0).html(),
                IdCliente: $(this).find('td').eq(1).html(),
                ValorCupon: formatNumber($(this).find('td').eq(3).html()),
                FechaLimiteCupon: $(this).find('td').eq(4).html(),
            });
        });
        let dataToSend = new FormData();
        dataToSend.append('Action','AddCupones'); dataToSend.append('Detail',JSON.stringify(data));
        let res = await makeOptionsFetch(`${globalUrl}/generar-cupones-archivo`,dataToSend,'post',$('meta[name="csrf-token-cupones"]').attr('content'));
        console.log(res);
        if(res.res == 'ok'){
            Swal.fire('Por favor ingresa un número de operación válido en el sistema...', 3000);
            $.redirect(`${globalUrl}/generar-cupones-archivo`, {Action: 'generateFileTxt', IdCupon: res.id_cupon }, "POST", "_blank"); 
            setTimeout(() => { location.reload(); }, 5000);
        }else{
            Swal.fire('No se ha podido registrar los cupones, por favor válida los datos de los registros agregados...', 3000,'','warning');
        }
    });

    $('#btnAgregarTercero').click(function(){
        $(this).attr('disabled', true);
        $('#divTablaCupones').preloader();
        let today = new Date();
        let date = today.getFullYear()+'-'+(today.getMonth()+1)+'-'+today.getDate();
        if(validarDatosIngresados($('#form-tercero-cupon [name=IdOperacion]').val())){
            $(this).attr('disabled', false);
            $('#divTablaCupones').preloader('remove');
            Swal.fire('Por favor ingrese el no. de operación...','','warning')
            .then((value)=>{
                if(value.isConfirmed){
                    $('#form-tercero-cupon [name=IdOperacion]').focus();
                }
            });
        }else if(validarDatosIngresados($('#form-tercero-cupon [name=DocumentoTercero]').val())){
            $(this).attr('disabled', false);
            $('#divTablaCupones').preloader('remove');
            Swal.fire('Por favor ingrese el documento del tercero...','','warning')
            .then((value)=>{
                if(value.isConfirmed){
                    $('#form-tercero-cupon [name=DocumentoTercero]').focus();
                }
            });
        }else if(validarDatosIngresados($('#form-tercero-cupon [name=NombreTercero]').val())){
            $(this).attr('disabled', false);
            $('#divTablaCupones').preloader('remove');
            Swal.fire('Por favor ingrese un tercero válido..','','warning')
            .then((value)=>{
                if(value.isConfirmed){
                    $('#form-tercero-cupon [name=NombreTercero]').focus();
                }
            });
        }else if(validarDatosIngresados($('#form-tercero-cupon [name=ValorCupon]').val())){
            $(this).attr('disabled', false);
            $('#divTablaCupones').preloader('remove');
            Swal.fire('Por favor ingrese el valor del cupón...','','warning')
            .then((value)=>{
                if(value.isConfirmed){
                    $('#form-tercero-cupon [name=ValorCupon]').focus();
                }
            });
        }else if(validarDatosIngresados($('#form-tercero-cupon [name=FechaLimiteCupon]').val())){
            $(this).attr('disabled', false);
            $('#divTablaCupones').preloader('remove');
            Swal.fire('Por favor ingrese la fecha maxima de pago para el cupón...','','warning')
            .then((value)=>{
                if(value.isConfirmed){
                    $('#form-tercero-cupon [name=FechaLimiteCupon]').focus();
                }
            });
        }else if(parseInt(date.replace("-",'')) > parseInt($('#form-tercero-cupon [name=FechaLimiteCupon]').val().replace('-', ''))){
            $(this).attr('disabled', false);
            $('#divTablaCupones').preloader('remove');
            Swal.fire('Por favor ingrese una fecha superior a la actual para el pago del cupón...','','warning')
            .then((value)=>{
                if(value.isConfirmed){
                    $('#form-tercero-cupon [name=FechaLimiteCupon]').focus();
                }
            });
        }
        
        let tr = `<tr>
                    <td>${ $('#form-tercero-cupon [name=IdOperacion]').val() }</td>
                    <td>${ $('#form-tercero-cupon [name=DocumentoTercero]').val() }</td>
                    <td>${ $('#form-tercero-cupon [name=NombreTercero]').val() }</td>
                    <td>${ $('#form-tercero-cupon [name=ValorCupon]').val() }</td>
                    <td>${ $('#form-tercero-cupon [name=FechaLimiteCupon]').val() }</td>
                    <td><button onclick="deleteRowTable(this);" class="btn btn-xs btn-sm btn-danger"><i class=" fa fa-trash"></i></button></td>
                </tr>`;
        $('#TableContent tbody').append(tr);
        $('#form-tercero-cupon')[0].reset();
        $(this).attr('disabled', false);
        verificarRegistrosGenerar();
    });
    
    $('#form-tercero-cupon [name=IdOperacion]').change(async function(){
        let dataToSend = new FormData();
        dataToSend.append('Action','SearchOperacion'); dataToSend.append('IdOperacion',$(this).val());
        let res = await makeOptionsFetch(`${globalUrl}/generar-cupones-archivo`,dataToSend,'post',$('meta[name="csrf-token-cupones"]').attr('content'));
        console.log(res);
        if(res.res == 'ok'){
            $('#form-tercero-cupon [name=DocumentoTercero]').val(res.datos.IdCliente);
            $('#form-tercero-cupon [name=NombreTercero]').val(res.datos.NomCliente.trim() + ' ' + res.datos.ApeCliente.trim());
        } else{
            Swal.fire('Por favor ingresa un numero de operación válido en el sistema...', 3000,'','warning');
        }
    });
}
const cambiarVista = function(vista){
    if(vista == 'generar'){
        document.getElementById('divTablaCupones').style.display = 'none';
        document.getElementById('divGeneracionCupon').style.display = 'block';
    }else if(vista == 'tabla'){
        document.getElementById('divGeneracionCupon').style.display = 'none';
        document.getElementById('divTablaCupones').style.display = 'block';
        document.querySelector('#divGeneracionCupon input').value = '';
    }
}
const deleteRowTable = (elm) => {
    $(elm).closest('tr').remove();
    verificarRegistrosGenerar();
}
const verificarRegistrosGenerar = () => {
    let tr = $('#TableContent tbody tr');
    if(tr.length > 0){
        $('#btnGenerarArchivo').removeClass('display');
    }else{
        $('#btnGenerarArchivo').addClass('display');
    }
}
const validarDatosIngresados = (value)=>{
    if(value === '' || value === undefined || value === null){
        return true;
    }
    return false;
}
const formatNumber = function(n){
    // format number 1000000 to 1,234,567
    return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",")
}