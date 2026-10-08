/**Peticion fetch solicita, url, data a enviar, metodo de la solictud y token */
async function makeOptionsFetch(url,dataToSend,method,token,silencioso=false){
    let response;
    try{
        response = await fetch(url,{method:method,body:dataToSend,headers:{'X-CSRF-TOKEN':token,'X-Requested-With':'XMLHttpRequest'}});
    }catch(e){
        let data = {message:MENSAJE_SIN_RED};
        if(!silencioso){
            mostrarErrorHttp(data,0);
        }
        throw Object.assign(new Error(data.message),{data:data,status:0});
    }
    let data = response.ok ? await response.json() : await response.json().catch(()=>({}));
    if(!response.ok){
        let bloqueante = response.status == 401 || response.status == 419 || (response.status == 403 && data.res == 'inactivo');
        data = response.status >= 500 ? {message:MENSAJE_ERROR_SERVIDOR} : data;
        if(!silencioso || response.status == 403 || bloqueante){
            mostrarErrorHttp(data,response.status);
        }
        if(bloqueante){
            return new Promise(()=>{});
        }
        throw Object.assign(new Error(mensajeErrorHttp(data)),{data:data,status:response.status});
    }
    return data;
}
const MENSAJE_SIN_RED = 'No hay conexión con el servidor. Verifica tu red e intenta de nuevo.';
const MENSAJE_ERROR_SERVIDOR = 'Ocurrió un error en el servidor. Intenta de nuevo; si continúa, avisa al área de desarrollo.';
let avisoBloqueanteMostrado = false;
const avisoBloqueante = function(titulo,texto,icono,boton,destino){
    if(avisoBloqueanteMostrado){
        return;
    }
    avisoBloqueanteMostrado = true;
    $(document.body).preloader('remove');
    Swal.fire({title:titulo,text:texto,icon:icono,confirmButtonText:boton,confirmButtonColor:'rgb(65,110,195)',allowOutsideClick:false,allowEscapeKey:false}).then(()=>{ window.location = `${window.location.origin}${destino}`; });
}
const formatearMoneda = function(valor,simbolo=true){
    let numero = Number(valor) || 0;
    return simbolo ? new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',minimumFractionDigits:0,maximumFractionDigits:0}).format(numero) : numero.toLocaleString('es-CO',{maximumFractionDigits:0});
}
const formatearPorcentaje = function(valor,decimales=2){
    return (Number(valor) || 0).toLocaleString('es-CO',{minimumFractionDigits:Math.min(2,decimales),maximumFractionDigits:decimales})+' %';
}
const notificar = function(texto){
    let toast = document.getElementById('ui-toast');
    if(!toast){
        toast = document.createElement('div');
        toast.id = 'ui-toast';
        toast.className = 'ui-toast d-none';
        toast.setAttribute('role','status');
        toast.setAttribute('aria-live','polite');
        document.body.appendChild(toast);
    }
    toast.textContent = texto;
    toast.classList.remove('d-none');
    clearTimeout(toast.temporizador);
    toast.temporizador = setTimeout(()=>toast.classList.add('d-none'),3000);
}
const numeroLimpio = function(texto){
    return String(texto == null ? '' : texto).trim().replace(/[.,]\d{1,2}$/,'').replace(/\D/g,'');
}
const formatearInputMoneda = function(input){
    let digitos = input.value.replace(/\D/g,'').replace(/^0+(?=\d)/,'');
    input.value = digitos === '' ? '' : formatearMoneda(digitos,false);
}
const escapeHtml = function(str){
    return (str == null ? '' : String(str)).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
const mensajeErrorHttp = function(data){
    let errores = (data && data.errors) ? Object.values(data.errors) : [];
    let mensaje = errores.length ? [].concat(errores[0])[0] : ((data && data.message) || 'No fue posible completar la solicitud. Intenta de nuevo.');
    return (data && data.montoMaximo != null) ? mensaje+' Monto máximo prestable: '+formatearMoneda(data.montoMaximo)+'.' : mensaje;
}
const mostrarErrorHttp = function(data,status){
    if(status == 401 || status == 419){
        return avisoBloqueante('Tu sesión expiró','Por seguridad, la sesión se cerró tras un tiempo sin actividad. Inicia sesión de nuevo para continuar; lo que no hayas guardado se perderá.','info','Iniciar sesión','/login');
    }
    if(status == 403 && data && data.res == 'inactivo'){
        return avisoBloqueante('Usuario inactivo',data.message || 'El usuario está inactivo, la sesión no puede continuar.','error','Entendido','/login');
    }
    $(document.body).preloader('remove');
    Swal.fire({
        title: status == 403 ? 'Acción no permitida' : 'No fue posible continuar',
        text: status >= 500 ? MENSAJE_ERROR_SERVIDOR : mensajeErrorHttp(data),
        icon: status == 403 ? 'warning' : 'error',
        confirmButtonText:'Entendido',
        confirmButtonColor:'rgb(65,110,195)'
    });
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

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.auth-toggle');
  if (!btn) return;
  const input = btn.parentNode.querySelector('input');
  const ver = input.type === 'password';
  input.type = ver ? 'text' : 'password';
  btn.setAttribute('aria-pressed', ver);
  btn.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
});

document.addEventListener('submit', (e) => {
  const btn = e.target.querySelector('.auth-btn[data-cargando]');
  if (!btn || e.defaultPrevented) return;
  btn.dataset.texto = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + btn.dataset.cargando;
});

window.addEventListener('pageshow', () => {
  document.querySelectorAll('.auth-btn[data-cargando]:disabled').forEach((btn) => {
    btn.disabled = false;
    if (btn.dataset.texto) btn.innerHTML = btn.dataset.texto;
  });
});
