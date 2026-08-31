window.onload = function(){
    $('[data-toggle="tooltip"]').tooltip();
    //traerClientes();
    $("#btn_registrar").on('click change',function () {
        form = $("#form_creacion_cliente").serialize();
        console.log(form);
    });
}

const CrearCliente = function(p1){
    let select_cliente = p1;
    $.ajaxSetup({
        headers: {
           'X-CSRF-TOKEN': $('meta[name="csrf-token-crear-cliente"]').attr('content')
        }
    });
    $('#div-crear-cliente').preloader();
    $.ajax({
        url:`${globalUrl}/creacion-tercero-siesa`,
        type:'POST',
        datatype:'HTML',
        data:{cliente:select_cliente},
        success:function(data){
            console.log(data);
            //valida si existe tercero o si no se va a la creacion de cliente.
            if(data == 'Existe Tercero'){
                $.ajax({
                    url:`${globalUrl}/creacion-cliente-siesa`,
                    type:'POST',
                    datatype:'HTML',
                    data:{cliente:select_cliente},
                    success:function(data1){
                        console.log(data1);
                        //valida la existencia que el cliente ya exista o si no se va a la creacion de proveedores
                        if(data1 == 'Existe Cliente'){
                            $.ajax({
                                url:`${globalUrl}/creacion-proveedor`,
                                type:'POST',
                                datatype:'HTML',
                                data:{cliente:select_cliente},
                                success:function(data2){
                                    console.log(data2);
                                    $.ajax({
                                        url:`${globalUrl}/creacion-impretension`,
                                        type:'POST',
                                        datatype:'HTML',
                                        data:{cliente:select_cliente},
                                        success:function(data3){
                                            console.log(data3);
                                            if(data3 == 1){
                                                $.ajax({
                                                    url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                    type:'POST',
                                                    datatype:'HTML',
                                                    data:{cliente:select_cliente},
                                                    success:function(data4){
                                                        console.log(data4);
                                                        $.ajax({
                                                            url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                            type:'POST',
                                                            datatype:'HTML',
                                                            data:{cliente:select_cliente},
                                                            success:function (data5) {
                                                                console.log(data5);
                                                                $.ajax({
                                                                    url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                    type:'POST',
                                                                    datatype:'HTML',
                                                                    data:{cliente:select_cliente},
                                                                    success:function (data6) {
                                                                        console.log(data6);
                                                                        if(data6 == 'ok'){
                                                                            $('#div-crear-cliente').preloader('remove');
                                                                            Swal.fire({
                                                                                title: "Exitoso! Los datos se guardaron correctamente",
                                                                                icon: "success",
                                                                                confirmButtonText: "Ok",
                                                                                confirmButtonColor:'rgb(65,110,195)'
                                                                            }).then((value)=>{
                                                                                if(value.isConfirmed){location.reload();}
                                                                            })
                                                                        }else{
                                                                            $('#div-crear-cliente').preloader('remove');
                                                                            Swal.fire({
                                                                                title: "Error! Los datos no se guardaron correctamente",
                                                                                icon: "error",
                                                                                confirmButtonText: "Ok",
                                                                                confirmButtonColor:'rgb(65,110,195)'
                                                                            })
                                                                        }
                                                                    }
                                                                });
                                                            }
                                                        });
                                                    }
                                                });
                                            }
                                            else{
                                                $.ajax({
                                                    url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                    type:'POST',
                                                    datatype:'HTML',
                                                    data:{cliente:select_cliente},
                                                    success:function(data4){
                                                        console.log(data4);
                                                        $.ajax({
                                                            url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                            type:'POST',
                                                            datatype:'HTML',
                                                            data:{cliente:select_cliente},
                                                            success:function (data5) {
                                                                console.log(data5);
                                                                $.ajax({
                                                                    url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                    type:'POST',
                                                                    datatype:'HTML',
                                                                    data:{cliente:select_cliente},
                                                                    success:function (data6) {
                                                                        console.log(data6);
                                                                        if(data6 == 'ok'){
                                                                            $('#div-crear-cliente').preloader('remove');
                                                                            Swal.fire({
                                                                                title: "Exitoso! Los datos se guardaron correctamente",
                                                                                icon: "success",
                                                                                confirmButtonText: "Ok",
                                                                                confirmButtonColor:'rgb(65,110,195)'
                                                                            }).then((value)=>{
                                                                                if(value.isConfirmed){location.reload();}
                                                                            })
                                                                        }else{
                                                                            $('#div-crear-cliente').preloader('remove');
                                                                            Swal.fire({
                                                                                title: "Error! Los datos no se guardaron correctamente",
                                                                                icon: "error",
                                                                                confirmButtonText: "Ok",
                                                                                confirmButtonColor:'rgb(65,110,195)'
                                                                            })
                                                                        }
                                                                    }
                                                                });
                                                            }
                                                        });
                                                    }
                                                });
                                            }
                                        }
                                    });
                                }
                            });
                        }
                        else{
                            $.ajax({
                                url:`${globalUrl}/creacion-proveedor`,
                                type:'POST',
                                datatype:'HTML',
                                data:{cliente:select_cliente},
                                success:function(data2){
                                    console.log(data2);
                                    //valida la existencia del proveedor si no se va a la creacion de impuesto retencion
                                    if(data2 == 'Existe Proveedor'){
                                        /*$.ajax({
                                            url:`${globalUrl}/creacion-impretension`,
                                            type:'POST',
                                            datatype:'HTML',
                                            data:{cliente:select_cliente},
                                            success:function(data3){
                                                if(data3 == 1){
                                                    swal({
                                                        title: "Exitoso! Datos registrados correctamente",
                                                        icon: "success",
                                                        confirmButtonText: "Ok",
                                                        confirmButtonColor:'rgb(65,110,195)'
                                                    }, function(){
                                                        //location.reload();
                                                    });
                                                }
                                                else{
                                                    swal({
                                                        title: "Error! \r\n "+data3+"",
                                                        icon: "error",
                                                        confirmButtonText: "Ok",
                                                        confirmButtonColor:'rgb(65,110,195)'
                                                    }, function(){
                                                        //location.reload();
                                                    });
                                                }
                                            }
                                        });*/
                                    }
                                    else{
                                        $.ajax({
                                            url:`${globalUrl}/creacion-impretension`,
                                            type:'POST',
                                            datatype:'HTML',
                                            data:{cliente:select_cliente},
                                            success:function(data3){
                                                if(data3 == 1){
                                                    $.ajax({
                                                        url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                        type:'POST',
                                                        datatype:'HTML',
                                                        data:{cliente:select_cliente},
                                                        success:function(data4){
                                                            console.log(data4);
                                                            $.ajax({
                                                                url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                                type:'POST',
                                                                datatype:'HTML',
                                                                data:{cliente:select_cliente},
                                                                success:function (data5) {
                                                                    console.log(data5);
                                                                    $.ajax({
                                                                        url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                        type:'POST',
                                                                        datatype:'HTML',
                                                                        data:{cliente:select_cliente},
                                                                        success:function (data6) {
                                                                            console.log(data6);
                                                                            if(data6 == 'ok'){
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Exitoso! Los datos se guardaron correctamente",
                                                                                    icon: "success",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                }).then((value)=>{
                                                                                    if(value.isConfirmed){location.reload();}
                                                                                })
                                                                            }else{
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Error! Los datos no se guardaron correctamente",
                                                                                    icon: "error",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                })
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                                else{
                                                    $.ajax({
                                                        url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                        type:'POST',
                                                        datatype:'HTML',
                                                        data:{cliente:select_cliente},
                                                        success:function(data4){
                                                            console.log(data4);
                                                            $.ajax({
                                                                url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                                type:'POST',
                                                                datatype:'HTML',
                                                                data:{cliente:select_cliente},
                                                                success:function (data5) {
                                                                    console.log(data5);
                                                                    $.ajax({
                                                                        url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                        type:'POST',
                                                                        datatype:'HTML',
                                                                        data:{cliente:select_cliente},
                                                                        success:function (data6) {
                                                                            console.log(data6);
                                                                            if(data6 == 'ok'){
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Exitoso! Los datos se guardaron correctamente",
                                                                                    icon: "success",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                }).then((value)=>{
                                                                                    if(value.isConfirmed){location.reload();}
                                                                                })
                                                                            }else{
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Error! Los datos no se guardaron correctamente",
                                                                                    icon: "error",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                })
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                            }
                                        });
                                    }
                                }
                            });
                        }
                    }
                });
            }
            else{
                $.ajax({
                    url:`${globalUrl}/creacion-cliente-siesa`,
                    type:'POST',
                    datatype:'HTML',
                    data:{cliente:select_cliente},
                    success:function(data1){
                        console.log(data1);
                        ////valida la existencia que el cliente ya exista o si no se va a la creacion de proveedores
                        if(data1 == 'Existe Cliente'){
                            $.ajax({
                                url:`${globalUrl}/creacion-proveedor`,
                                type:'POST',
                                datatype:'HTML',
                                data:{cliente:select_cliente},
                                success:function(data2){
                                    console.log(data2);
                                    //valida la existencia del proveedor
                                    if(data2 == 'Existe Proveedor'){
                                        $.ajax({
                                            url:`${globalUrl}/creacion-impretension`,
                                            type:'POST',
                                            datatype:'HTML',
                                            data:{cliente:select_cliente},
                                            success:function(data3){
                                                if(data3 == 1){
                                                    $.ajax({
                                                        url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                        type:'POST',
                                                        datatype:'HTML',
                                                        data:{cliente:select_cliente},
                                                        success:function(data4){
                                                            console.log(data4);
                                                            $.ajax({
                                                                url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                                type:'POST',
                                                                datatype:'HTML',
                                                                data:{cliente:select_cliente},
                                                                success:function (data5) {
                                                                    console.log(data5);
                                                                    $.ajax({
                                                                        url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                        type:'POST',
                                                                        datatype:'HTML',
                                                                        data:{cliente:select_cliente},
                                                                        success:function (data6) {
                                                                            console.log(data6);
                                                                            if(data6 == 'ok'){
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Exitoso! Los datos se guardaron correctamente",
                                                                                    icon: "success",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                }).then((value)=>{
                                                                                    if(value.isConfirmed){location.reload();}
                                                                                })
                                                                            }else{
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Error! Los datos no se guardaron correctamente",
                                                                                    icon: "error",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                })
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                                else{
                                                    $.ajax({
                                                        url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                        type:'POST',
                                                        datatype:'HTML',
                                                        data:{cliente:select_cliente},
                                                        success:function(data4){
                                                            console.log(data4);
                                                            $.ajax({
                                                                url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                                type:'POST',
                                                                datatype:'HTML',
                                                                data:{cliente:select_cliente},
                                                                success:function (data5) {
                                                                    console.log(data5);
                                                                    $.ajax({
                                                                        url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                        type:'POST',
                                                                        datatype:'HTML',
                                                                        data:{cliente:select_cliente},
                                                                        success:function (data6) {
                                                                            console.log(data6);
                                                                            if(data6 == 'ok'){
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Exitoso! Los datos se guardaron correctamente",
                                                                                    icon: "success",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                }).then((value)=>{
                                                                                    if(value.isConfirmed){location.reload();}
                                                                                })
                                                                            }else{
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Error! Los datos no se guardaron correctamente",
                                                                                    icon: "error",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                })
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                            }
                                        });
                                    }
                                    else{
                                        $.ajax({
                                            url:`${globalUrl}/creacion-impretension`,
                                            type:'POST',
                                            datatype:'HTML',
                                            data:{cliente:select_cliente},
                                            success:function(data3){
                                                if(data3 == 1){
                                                    $.ajax({
                                                        url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                        type:'POST',
                                                        datatype:'HTML',
                                                        data:{cliente:select_cliente},
                                                        success:function(data4){
                                                            console.log(data4);
                                                            $.ajax({
                                                                url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                                type:'POST',
                                                                datatype:'HTML',
                                                                data:{cliente:select_cliente},
                                                                success:function (data5) {
                                                                    console.log(data5);
                                                                    $.ajax({
                                                                        url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                        type:'POST',
                                                                        datatype:'HTML',
                                                                        data:{cliente:select_cliente},
                                                                        success:function (data6) {
                                                                            console.log(data6);
                                                                            if(data6 == 'ok'){
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Exitoso! Los datos se guardaron correctamente",
                                                                                    icon: "success",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                }).then((value)=>{
                                                                                    if(value.isConfirmed){location.reload();}
                                                                                })
                                                                            }else{
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Error! Los datos no se guardaron correctamente",
                                                                                    icon: "error",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                })
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                                else{
                                                    $.ajax({
                                                        url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                        type:'POST',
                                                        datatype:'HTML',
                                                        data:{cliente:select_cliente},
                                                        success:function(data4){
                                                            console.log(data4);
                                                            $.ajax({
                                                                url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                                type:'POST',
                                                                datatype:'HTML',
                                                                data:{cliente:select_cliente},
                                                                success:function (data5) {
                                                                    console.log(data5);
                                                                    $.ajax({
                                                                        url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                        type:'POST',
                                                                        datatype:'HTML',
                                                                        data:{cliente:select_cliente},
                                                                        success:function (data6) {
                                                                            console.log(data6);
                                                                            if(data6 == 'ok'){
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Exitoso! Los datos se guardaron correctamente",
                                                                                    icon: "success",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                }).then((value)=>{
                                                                                    if(value.isConfirmed){location.reload();}
                                                                                })
                                                                            }else{
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Error! Los datos no se guardaron correctamente",
                                                                                    icon: "error",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                })
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                            }
                                        });
                                    }
                                }
                            });
                        }
                        else{
                            $.ajax({
                                url:`${globalUrl}/creacion-proveedor`,
                                type:'POST',
                                datatype:'HTML',
                                data:{cliente:select_cliente},
                                success:function(data2){
                                    console.log(data2);
                                    //valida la existencia del proveedor 
                                    if(data2 == 'Existe Proveedor'){
                                        $.ajax({
                                            url:`${globalUrl}/creacion-impretension`,
                                            type:'POST',
                                            datatype:'HTML',
                                            data:{cliente:select_cliente},
                                            success:function(data3){
                                                if(data3 == 1){
                                                    $.ajax({
                                                        url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                        type:'POST',
                                                        datatype:'HTML',
                                                        data:{cliente:select_cliente},
                                                        success:function(data4){
                                                            console.log(data4);
                                                            $.ajax({
                                                                url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                                type:'POST',
                                                                datatype:'HTML',
                                                                data:{},
                                                                success:function (data5) {
                                                                    console.log(data5);
                                                                    $.ajax({
                                                                        url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                        type:'POST',
                                                                        datatype:'HTML',
                                                                        data:{cliente:select_cliente},
                                                                        success:function (data6) {
                                                                            console.log(data6);
                                                                            if(data6 == 'ok'){
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Exitoso! Los datos se guardaron correctamente",
                                                                                    icon: "success",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                }).then((value)=>{
                                                                                    if(value.isConfirmed){location.reload();}
                                                                                })
                                                                            }else{
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Error! Los datos no se guardaron correctamente",
                                                                                    icon: "error",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                })
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                                else{
                                                    $.ajax({
                                                        url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                        type:'POST',
                                                        datatype:'HTML',
                                                        data:{cliente:select_cliente},
                                                        success:function(data4){
                                                            console.log(data4);
                                                            $.ajax({
                                                                url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                                type:'POST',
                                                                datatype:'HTML',
                                                                data:{cliente:select_cliente},
                                                                success:function (data5) {
                                                                    console.log(data5);
                                                                    $.ajax({
                                                                        url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                        type:'POST',
                                                                        datatype:'HTML',
                                                                        data:{cliente:select_cliente},
                                                                        success:function (data6) {
                                                                            console.log(data6);
                                                                            if(data6 == 'ok'){
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Exitoso! Los datos se guardaron correctamente",
                                                                                    icon: "success",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                }).then((value)=>{
                                                                                    if(value.isConfirmed){location.reload();}
                                                                                })
                                                                            }else{
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Error! Los datos no se guardaron correctamente",
                                                                                    icon: "error",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                })
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                            }
                                        });
                                    }
                                    else{
                                        $.ajax({
                                            url:`${globalUrl}/creacion-impretension`,
                                            type:'POST',
                                            datatype:'HTML',
                                            data:{cliente:select_cliente},
                                            success:function(data3){
                                                if(data3 == 1){
                                                    $.ajax({
                                                        url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                        type:'POST',
                                                        datatype:'HTML',
                                                        data:{cliente:select_cliente},
                                                        success:function(data4){
                                                            console.log(data4);
                                                            $.ajax({
                                                                url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                                type:'POST',
                                                                datatype:'HTML',
                                                                data:{cliente:select_cliente},
                                                                success:function (data5) {
                                                                    console.log(data5);
                                                                    $.ajax({
                                                                        url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                        type:'POST',
                                                                        datatype:'HTML',
                                                                        data:{cliente:select_cliente},
                                                                        success:function (data6) {
                                                                            console.log(data6);
                                                                            if(data6 == 'ok'){
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Exitoso! Los datos se guardaron correctamente",
                                                                                    icon: "success",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                }).then((value)=>{
                                                                                    if(value.isConfirmed){location.reload();}
                                                                                })
                                                                            }else{
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Error! Los datos no se guardaron correctamente",
                                                                                    icon: "error",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                })
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                                else{
                                                    $.ajax({
                                                        url:`${globalUrl}/creacion-impretencion-proveedor`,
                                                        type:'POST',
                                                        datatype:'HTML',
                                                        data:{cliente:select_cliente},
                                                        success:function(data4){
                                                            console.log(data4);
                                                            $.ajax({
                                                                url:`${globalUrl}/crear-pagoelec-bancolombia`,
                                                                type:'POST',
                                                                datatype:'HTML',
                                                                data:{cliente:select_cliente},
                                                                success:function (data5) {
                                                                    console.log(data5);
                                                                    $.ajax({
                                                                        url:`${globalUrl}/crear-pagoelec-bancobogota`,
                                                                        type:'POST',
                                                                        datatype:'HTML',
                                                                        data:{cliente:select_cliente},
                                                                        success:function (data6) {
                                                                            console.log(data6);
                                                                            if(data6 == 'ok'){
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Exitoso! Los datos se guardaron correctamente",
                                                                                    icon: "success",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                }).then((value)=>{
                                                                                    if(value.isConfirmed){location.reload();}
                                                                                })
                                                                            }else{
                                                                                $('#div-crear-cliente').preloader('remove');
                                                                                Swal.fire({
                                                                                    title: "Error! Los datos no se guardaron correctamente",
                                                                                    icon: "error",
                                                                                    confirmButtonText: "Ok",
                                                                                    confirmButtonColor:'rgb(65,110,195)'
                                                                                })
                                                                            }
                                                                        }
                                                                    });
                                                                }
                                                            });
                                                        }
                                                    });
                                                }
                                            }
                                        });
                                    }
                                }
                            });
                        }
                    }
                });
            }
        }
    });
}