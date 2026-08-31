window.onload = function(){
    $('[data-toggle="tooltip"]').tooltip();
    $(function(){
        $('#fechaOperacion').datetimepicker({
            format:'Y/m/d',
            datepicker:true,
            timepicker:false
        });
    });
}

const BusquedaOperacion = async function(){
    $('.content-contable').preloader();
    let operacion = document.querySelector('#operacion').value;
    let dataToSend = new FormData();
    /*dataToSend.append('fechaInicial',fechaInicial); dataToSend.append('fechaFinal',fechaFinal);*/ 
    dataToSend.append('operacion',operacion); //dataToSend.append('fechaOperacion',);
    let res = await makeOptionsFetch(`${globalUrl}/reclasificar-filtros`,dataToSend,'post',$('meta[name="csrf-token-reclasificar"]').attr('content'));
    console.log(res);
    if(res.status == 'ok'){
        $('.content-contable').preloader('remove');
        document.getElementById('tbodyOperaciones').innerHTML = res.tabla;
    }
}

const EnviarOperacion = async function(idOperacion){
    let fechaOp = document.querySelector('#fechaOperacion').value;
    if(fechaOp == ''){
        Swal.fire({title:'Oops!',text:'Hace falta una fecha para el envio',icon:'warning',confirmButttonText:'Entendido',confirmButtonColor:'rgb(65,110,195)'});
    }else{
        $(".content-contable").preloader();
        let dataToSend = new FormData();
        dataToSend.append('idOperacion',idOperacion); dataToSend.append('fechaOperacion',fechaOp);
        let res = await makeOptionsFetch(`${globalUrl}/reclasificar-op-envio-siesa`,dataToSend,'post',$('meta[name="csrf-token-reclasificar"]').attr('content'));
        console.log(res);
        if(res.status == 'ok'){
            $(".content-contable").preloader('remove');
            Swal.fire({title:'Perfecto!',text:'Importación completada con exito',icon:'success',confirmButttonText:'Entendido',confirmButtonColor:'rgb(65,110,195)'})
            .then((value)=>{
                if (value.isConfirmed) location.reload();
            })
        }else if(res.error){
            $(".content-contable").preloader('remove');
            Swal.fire({title:'Oops!',text:res.error,icon:'error',confirmButttonText:'Entendido',confirmButtonColor:'rgb(65,110,195)'});
        }else{
            $(".content-contable").preloader('remove');
            Swal.fire({title:'Oh no!',text:res,icon:'error',confirmButttonText:'Entendido',confirmButtonColor:'rgb(65,110,195)'});
        }
    }
}

function ValorID(id){
    idOp = id, fechaOp = $('#fechaOperacion').val();
    $("#btn_enviar").prop('disabled',true);
    if(fechaOp == ''){
        swal("","Selecciona la fecha para envío","error");
    }else{
        $('.content-contable').preloader();
        $("#div_loader").css('display','block');
        $.ajax({
            url:'php/inicio/siesas_ReclasificarOperaciones.php',
            datatype:'HTML',
            type:'POST',
            data:{idoperacion:idOp,fechaOperacion:fechaOp},
            success:function(data){
                $('.content-contable').preloader('remove');
                console.log(data);
                if(data == 1){
                    location.reload();
                $("#div_loader").css('display','none');
                $("#btn_enviar").prop('disabled',false);
                }
                else{
                    swal("", ""+data+"", "error");
                    $("#div_loader").css('display','none');
                    $("#btn_enviar").prop('disabled',false);
                }
            }
        });
    }
}