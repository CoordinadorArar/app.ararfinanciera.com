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