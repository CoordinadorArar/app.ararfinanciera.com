window.onload = function(){
    $('[data-toggle="tooltip"]').tooltip();
    $(function(){
        $('#fechaInicial, #fechaFinal').datetimepicker({
            format:'Y/m/d',
            datepicker:true,
            timepicker:false,
        });
    });
}
const BusquedaFecha = async function(){
    $('#divDocuementoOperacines').preloader();
    let fechaInicial = document.getElementById('fechaInicial').value;
    let fechaFinal = document.getElementById('fechaFinal').value;
    let dataToSend = new FormData();
    dataToSend.append('fechaInicial',fechaInicial); dataToSend.append('fechaFinal',fechaFinal);
    let res = await makeOptionsFetch(`${globalUrl}/documento-operaciones-filtros`,dataToSend,'post',$('meta[name="csrf-token-documento-operaciones"]').attr('content'))
    if(res){
        $('#divDocuementoOperacines').preloader('remove');
        console.log(res);
        document.getElementById('tbodyOperaciones').innerHTML = res.tabla;
        document.getElementById('containerBtnOperacion').innerHTML = res.boton;
    }
}
const EnviarOperacion = async function(idOperacion){
    $('#divDocuementoOperacines').preloader();
    let dataToSend = new FormData();
    dataToSend.append('idOperacion',idOperacion);
    let res = await makeOptionsFetch(`${globalUrl}/documento-operaciones-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-operaciones"]').attr('content'))
    console.log(res);
    if(res == 'ok'){
        $('#divDocuementoOperacines').preloader('remove');
        Swal.fire({
            title:'Perfecto!',
            text:'Datos de operaciones enviados con exito',
            icon:'success',
            confirmButtonText:'Entendido',
            confirmButtonColor:'rgb(65,110,195)',
            allowOutsideClick:false,
            allowEscapeKey:false,
        }).then((value)=>{
            if(value.isConfirmed){location.reload();}
        })
    }else{
        $('#divDocuementoOperacines').preloader('remove');
        Swal.fire({
            title:'Error encontrado',
            html: construirHtmlErrores(res),
            icon:'error',
            confirmButtonText:'Entendido',
            confirmButtonColor:'rgb(65,110,195)'
        });
    }
}
const EnviarTodos = async function(operaciones){
    $('#divDocuementoOperacines').preloader();
    $("#btn_enviar").prop('disabled',true);
    let dataToSend = new FormData();
    dataToSend.append('idOperacion',operaciones);
    let res = await makeOptionsFetch(`${globalUrl}/documento-operaciones-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-operaciones"]').attr('content'))
    console.log(res);
    if(res == 'ok'){
        $('#divDocuementoOperacines').preloader('remove');
        Swal.fire({
            title:'Perfecto!',
            text:'Datos de operaciones enviados con exito',
            icon:'success',
            confirmButtonText:'Entendido',
            confirmButtonColor:'rgb(65,110,195)',
            allowOutsideClick:false,
            allowEscapeKey:false,
        }).then((value)=>{
            if(value.isConfirmed){location.reload();}
        })
    }else{
        $('#divDocuementoOperacines').preloader('remove');
        Swal.fire({
            title:'Error encontrado',
            html: construirHtmlErrores(res),
            icon:'error',
            confirmButtonText:'Entendido',
            confirmButtonColor:'rgb(65,110,195)'
        });
    }
}

// Arma un HTML legible a partir del array de errores del backend
const construirHtmlErrores = function(errores){
    if(!Array.isArray(errores)){
        return `<p>${errores}</p>`;
    }
    let items = errores.map(err => {
        return `<li style="text-align:left; margin-bottom:6px;">
                    <b>Línea ${err.f_nro_linea}</b> (Valor: ${err.f_valor.trim()})<br>
                    ${err.f_detalle.trim()}
                </li>`;
    }).join('');
    return `<ul style="padding-left:18px; max-height:300px; overflow-y:auto;">${items}</ul>`;
}