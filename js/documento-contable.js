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
    try{
        if(tipoDocumento == 'NCR'){
            res = await makeOptionsFetch(`${globalUrl}/nota-credito-contable-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-contable"]').attr('content'));
        }else{
            res = await makeOptionsFetch(`${globalUrl}/documento-contable-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-documento-contable"]').attr('content'));
        }
    }catch(e){
        $('.content-contable').preloader('remove');
        Swal.fire({title:'Oh no!',text:'Error de comunicación con el servidor, el documento no fue confirmado.',icon:'error',confirmButtonText:'Entendido',confirmButtonColor:'rgb(65,110,195)'});
        return;
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
        let msg = res && res.errores ? Object.values(res.errores).join('\n') : (res && res.error ? res.error : (typeof res == 'string' ? res : JSON.stringify(res)));
        Swal.fire({title:'Oh no!',text:msg,icon:'error',confirmButtonText:'Entendido',confirmButtonColor:'rgb(65,110,195)'});
    }
}

const EnviarTodos = async function(facturas){
    let btn = document.querySelector('#btn_enviar');
    if(btn){
        if(btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>&nbsp;&nbsp;Enviando...';
    }
    $('[data-toggle="tooltip"]').tooltip('hide');
    const esc = t => $('<div>').text(String(t)).html();
    const token = $('meta[name="csrf-token-documento-contable"]').attr('content');
    let tipoDocumento = document.querySelector('#tipoDocumento').value;
    let url = `${globalUrl}/${tipoDocumento == 'NCR' ? 'nota-credito-contable-envio-siesa' : 'documento-contable-envio-siesa'}`;
    let ids = facturas.split(',').map(id => id.trim()).filter(id => id);
    let total = ids.length, correctos = 0, fallidos = [];
    const progreso = enCurso => {
        let procesados = correctos + fallidos.length;
        return `<div class="font-weight-bold mb-3">Enviando ${Math.min(procesados + enCurso, total)} de ${total}</div>
            <div class="progress mb-3" style="height:10px"><div class="progress-bar" role="progressbar" style="width:${total ? procesados * 100 / total : 0}%;background-color:rgb(65,110,195)"></div></div>
            <div class="small text-muted mb-2">Esto puede tardar varios minutos. No cierre ni recargue la página.</div>
            <div>Correctos: ${correctos} &middot; <span class="${fallidos.length ? 'text-danger' : ''}">Fallidos: ${fallidos.length}</span></div>`;
    };
    Swal.fire({title:'Enviando documentos a SIESA',html:progreso(0),showConfirmButton:false,allowOutsideClick:false,allowEscapeKey:false});
    for(let i = 0; i < total; i += 5){
        let lote = ids.slice(i, i + 5);
        Swal.getHtmlContainer().innerHTML = progreso(lote.length);
        let dataToSend = new FormData();
        dataToSend.append('idOperacion',lote.join(',')); dataToSend.append('tipoDocumento',tipoDocumento);
        let res;
        try{
            res = await makeOptionsFetch(url,dataToSend,'post',token);
        }catch(e){
            res = {error:'Error de comunicación con el servidor, el documento no fue confirmado.'};
        }
        if(res == 'ok'){
            correctos += lote.length;
        }else if(res && res.errores){
            lote.forEach(id => res.errores[id] !== undefined ? fallidos.push([id, res.errores[id]]) : correctos++);
        }else{
            let msg = res && res.error ? res.error : (typeof res == 'string' ? res : JSON.stringify(res));
            lote.forEach(id => fallidos.push([id, msg]));
        }
    }
    Swal.getHtmlContainer().innerHTML = progreso(0);
    let opciones = {confirmButtonText:'Entendido',confirmButtonColor:'rgb(65,110,195)',allowOutsideClick:false,allowEscapeKey:false};
    if(!fallidos.length){
        Object.assign(opciones, {icon:'success',title:'¡Perfecto!',text:`Se enviaron ${total} documentos a SIESA con éxito`});
    }else{
        let lista = fallidos.map(([id, msg], i) => `<div style="padding:6px 10px;${i < fallidos.length - 1 ? 'border-bottom:1px solid #dee2e6' : ''}"><div class="font-weight-bold">Doc. ${esc(id)}</div><div class="text-muted" style="white-space:pre-line;word-break:break-word">${esc(msg)}</div></div>`).join('');
        Object.assign(opciones, {icon:'warning',title:'Envío finalizado con errores',width:'40rem',html:`<div class="font-weight-bold mb-3"><span class="text-success">Enviados correctamente: ${correctos}</span><br><span class="text-danger">Fallidos: ${fallidos.length}</span></div><div style="max-height:280px;overflow-y:auto;text-align:left;font-size:.85rem;border:1px solid #dee2e6;border-radius:4px">${lista}</div>`});
    }
    Swal.fire(opciones).then(value => { if(value.isConfirmed) location.reload(); });
}
