window.onload = function(){
    $('[data-toggle="tooltip"]').tooltip();
    $(function(){
        $('#desde, #hasta').datetimepicker({
            format:'Y/m/d',
            datepicker:true,
            timepicker:false
        });
    });
    //EnviarOperacion();
}
/**Mostrar datos de facturas en tabla */
const cargarDatos = async function(tipoDocumento){
    $('.content-contable').preloader();
    let fechaInicial = document.querySelector('#desde').value;
    let fechaFinal = document.querySelector('#hasta').value;
    let dataToSend = new FormData();
    dataToSend.append('fechaInicial',fechaInicial); dataToSend.append('fechaFinal',fechaFinal); dataToSend.append('tipoDocumento',tipoDocumento);
    let res = await makeOptionsFetch(`${globalUrl}/documento-contable-filtros`,dataToSend,'post',$('meta[name="csrf-token-documento-contable"]').attr('content'));
    if(res){
        $('.content-contable').preloader('remove');
        console.log(res);
        document.getElementById('tbodyFactoringsiesa').innerHTML = res.tabla;
        document.getElementById('containerEnvios').innerHTML = res.boton;
    }
}
/**envio de datos de las operaciones */
const EnviarOperacion = async function(idOperacion){
    $('.content-contable').preloader();
    if(document.querySelector('#btn_enviar')){
        document.querySelector('#btn_enviar').setAttribute('disabled', 'disabled');
    }
    let tipoDocumento = document.querySelector('#tipoDocumento').value;
    let dataToSend = new FormData();
    dataToSend.append('idOperacion',idOperacion); dataToSend.append('tipoDocumento',tipoDocumento);
    if(tipoDocumento == 'NCR'){
        res = await makeOptionsFetch(`${globalUrl}/nota-credito-contable-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-contable"]').attr('content'));
    }else{
        res = await makeOptionsFetch(`${globalUrl}/documento-contable-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-contable"]').attr('content'));
    }    
    if(res == 'ok'){
        if(document.querySelector('#btn_enviar')){
            document.querySelector('#btn_enviar').setAttribute('disabled', 'disabled');
        }
        $('.content-contable').preloader('remove');
        Swal.fire({
            title:'Perfecto!',
            text:'Datos de operación enviados a SIESA con exito',
            icon:'success',
            confirmButtonText:'Entendido',
            confirmButtonColor:'rgb(65,110,195)',
            allowOutsideClick:false,
            allowEscapeKey:false,
        }).then((value)=>{
            if (value.isConfirmed) location.reload();
        })
    }else{
        if(document.querySelector('#btn_enviar')){
            document.querySelector('#btn_enviar').setAttribute('disabled', 'disabled');
        }
        $('.content-contable').preloader('remove');
        Swal.fire({title:'Oh no!',text:res,icon:'error',confirmButtonText:'Entendido',confirmButtonColor:'rgb(65,110,195)'});
    }
}

const EnviarTodos = async function(facturas){
    $('.content-contable').preloader();
    let tipoDocumento = document.querySelector('#tipoDocumento').value;
    let dataToSend = new FormData();
    let arrayDocumentos = facturas.split(',');

    //tomar solo los 10 primeros datos
    let lote = arrayDocumentos.splice(0,10);
    // Convertir el lote en un string separado por comas
    let loteCadena = lote.join(',');

    dataToSend.append('idOperacion',loteCadena); dataToSend.append('tipoDocumento',tipoDocumento);
    if(tipoDocumento == 'NCR'){
        res = await makeOptionsFetch(`${globalUrl}/nota-credito-contable-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-contable"]').attr('content'));
    }else{
        res = await makeOptionsFetch(`${globalUrl}/documento-contable-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-contable"]').attr('content'));
    }
    console.log(arrayDocumentos);
    if(res == 'ok'){
        $('.content-contable').preloader('remove');
        // for (let i = 0; i < lote.length; i++) {
        //     const index = arrayDocumentos.indexOf(lote[i]);
        //     if (index != -1) {
        //         arrayDocumentos.splice(index, 1);
        //     }
        // }

        arrayDocumentos.splice(0, 10);
        
        if(arrayDocumentos.length > 0){
            Swal.fire({
                title:'Perfecto!',
                text:'Enviando documentos, esto puede tardar unos minutos, documentos restantes: '+arrayDocumentos.length+'.',
                icon:'success',
                // confirmButtonText:'Entendido',
                // confirmButtonColor:'rgb(65,110,195)',
                showConfirmButton:false,
                allowOutsideClick:false,
                allowEscapeKey:false,
                timer: 5000
            }).then((value)=>{
                let cadenaDocumentos = arrayDocumentos.join(',');
                EnviarTodos(cadenaDocumentos);
            })
        }else{
            Swal.fire({
                title:'Perfecto!',
                text:'Documentos enviados a SIESA con exito',
                icon:'success',
                confirmButtonText:'Entendido',
                confirmButtonColor:'rgb(65,110,195)',
                allowOutsideClick:false,
                allowEscapeKey:false,
            }).then((value)=>{
                if (value.isConfirmed) location.reload();
            })
        }
    }else{
        $('.content-contable').preloader('remove');
        Swal.fire({title:'Oh no!',text:res,icon:'error',confirmButtonText:'Entendido',confirmButtonColor:'rgb(65,110,195)'})
    }

    /*dataToSend.append('idOperacion',facturas); dataToSend.append('tipoDocumento',tipoDocumento);
    if(tipoDocumento == 'NCR'){
        res = await makeOptionsFetch(`${globalUrl}/nota-credito-contable-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-contable"]').attr('content'));
    }else{
        res = await makeOptionsFetch(`${globalUrl}/documento-contable-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-contable"]').attr('content'));
    }  
    // let res = await makeOptionsFetch(`${globalUrl}/documento-contable-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-contable"]').attr('content'));
    console.log(res);
    if(res == 'ok'){
        $('.content-contable').preloader('remove');
        Swal.fire({
            title:'Perfecto!',
            text:'Facturas enviadas a SIESA con exito',
            icon:'success',
            confirmButtonText:'Entendido',
            confirmButtonColor:'rgb(65,110,195)',
            allowOutsideClick:false,
            allowEscapeKey:false,
        }).then((value)=>{
            if (value.isConfirmed) location.reload();
        })
    }else{
        $('.content-contable').preloader('remove');
        Swal.fire({title:'Oh no!',text:res,icon:'error',confirmButtonText:'Entendido',confirmButtonColor:'rgb(65,110,195)'})
    }*/
}