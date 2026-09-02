/**Peticion fetch solicita, url, data a enviar, metodo de la solictud y token */
async function makeOptionsFetch(url,dataToSend,method,token){
    let response = await fetch(url,{method:method,body:dataToSend,headers:{'X-CSRF-TOKEN':token}})
    let data = await response.json();
    return data;
}
/**Mostrar mensajes de errores */
const showErrors = async function(data){
    for(let nombre in data.errors){
        document.querySelector(`#error-${nombre}`).innerHTML = data.errors[nombre];
        document.querySelector(`#error-${nombre}`).style="display:block;";
    }
}
/**Conmutar el ambiente de trabajo entre produccion y demo */
const conmutarAmbiente = function(destino,elemento){
    let demo = destino == 'demo';
    if(elemento){
        elemento.disabled = true;
    }
    Swal.fire({
        title: demo ? '¿Cambiar a ambiente Demo?' : '¿Volver al ambiente de Producción?',
        text: demo ? 'Verás y modificarás datos de prueba. Ninguna información real se altera. La página se recargará.' : 'Volverás a ver y modificar datos reales. La página se recargará.',
        icon: demo ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: demo ? 'Sí, entrar a Demo' : 'Sí, volver a Producción',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: demo ? '#c99a2e' : 'rgb(65,110,195)'
    }).then(async (value)=>{
        if(!value.isConfirmed){
            restablecerSwitchAmbiente(elemento,demo);
            return;
        }
        let dataToSend = new FormData();
        dataToSend.append('ambiente',destino);
        let res = await makeOptionsFetch(`${globalUrl}/cambiar-ambiente`,dataToSend,'post',$('meta[name="csrf-token-ambiente"]').attr('content'));
        if(res.success){
            Swal.fire({
                title: demo ? 'Ambiente Demo activado' : 'Ambiente Producción activo',
                text: demo ? 'Todo lo que hagas a partir de ahora ocurre sobre datos de prueba.' : 'Vuelves a trabajar sobre datos reales.',
                icon:'success',
                confirmButtonText:'Entendido',
                confirmButtonColor: demo ? '#c99a2e' : 'rgb(65,110,195)',
                allowOutsideClick:false,
                allowEscapeKey:false
            }).then((value)=>{
                location.reload();
            });
        }else{
            restablecerSwitchAmbiente(elemento,demo);
            if(res.errors && document.getElementById('error-ambiente')){
                showErrors(res);
            }
            Swal.fire({
                title:'No fue posible cambiar de ambiente',
                text:'Intenta de nuevo. Si el problema continúa, avisa al área de desarrollo.',
                icon:'error',
                confirmButtonText:'Entendido'
            });
        }
    });
}
/**Dejar el switch en el ambiente que sigue activo en el servidor */
const restablecerSwitchAmbiente = function(elemento,demo){
    if(elemento){
        elemento.checked = !demo;
        elemento.disabled = false;
    }
}
/**Convertir a mayusculas los valores ingresados */
const toUpperValues = function(element){
    element.value = element.value.toUpperCase();
}
/**Crear tabla responsiva con datatables */
const crearTablaResponsiva = async function(element){
    $('#'+element).dataTable({
        'bSort':false,
        'bPaginate':true,
        'sPaginationType':'full_numbers',
        'iDisplayLength':5,
        /*"iPageLength" : 5,*/
        "lengthMenu": [5, 10, 15],
        'searching':false,
        language: {
            "decimal": "",
            "emptyTable": "No hay información",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ Entradas",
            "infoEmpty": "Mostrando 0 to 0 of 0 Entradas",
            "infoFiltered": "(Filtrado de _MAX_ total entradas)",
            "infoPostFix": "",
            "thousands": ",",
            "lengthMenu": "Mostrar _MENU_ Entradas",
            "loadingRecords": "Cargando...",
            "processing": "Procesando...",
            "search": "Buscar:",
            "zeroRecords": "Sin resultados encontrados",
            "paginate": {
                "first": "Primero",
                "last": "Ultimo",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
    });
}

const soloNumeros = (event) => {
  let charCode = event.which ? event.which : event.keyCode;
  if (charCode < 48 || charCode > 57) {
      event.preventDefault(); // Bloquea la entrada de caracteres no numéricos
  }
}

const noStrangeCharacters = (e)=>{
  let allowedKeys = ["Backspace", "Delete", "ArrowLeft", "ArrowRight", "Tab", "Enter"];
  let patron = /^[a-zA-Z0-9@ _.,#\-$áéíóúÁÉÍÓÚñÑ]$/; // Expresión corregida
  let tecla = e.key; // Captura la tecla presionada

  if (allowedKeys.includes(tecla) || patron.test(tecla)) {
      return true; // Permite la tecla
  }

  e.preventDefault();
  return false; // Bloquea la tecla no permitida
}