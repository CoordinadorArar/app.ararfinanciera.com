/**Cambiar a vista de calculo de proceso */
const mostrarValoresProceso = async function(idProceso){
    document.querySelector('#tabla-procesos').style = 'display:none;';
    document.querySelector('#calculate-credit').style = 'display:block;';
    document.querySelector('#idProceso').value = idProceso;
    let dataToSend = new FormData();
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-valores-proceso`,dataToSend,'post',$('meta[name="csrf-token-form-calculate"]').attr('content'));
    document.querySelector('#ingresosMensuales').value = res[0].IngresosTercero;
}
/**Volver a la tabla de procesos */
const backForm = function(){
    document.querySelector('#tabla-procesos').style = 'display:block;';
    document.querySelector('#calculate-credit').style = 'display:none;';
    document.querySelector('#idProceso').value = '';
}
/**Calcular cupo según valores ingresados */
const calculate = async function(){
    let dataToSend = new FormData();
    dataToSend.append('idProceso',document.querySelector('#idProceso').value);
    dataToSend.append('salarioMinimo',salarioMinimoMensual);
    dataToSend.append('ingresos',document.querySelector('#ingresosMensuales').value);
    dataToSend.append('ingresosExtras',document.querySelector('#ingresosExtras').value);
    dataToSend.append('descuentosLey',document.querySelector('#descuentosLey').value);
    dataToSend.append('deducciones',document.querySelector('#deducciones').value);
    let res = await makeOptionsFetch(`${globalUrl}/calcular`,dataToSend,'post',$('meta[name="csrf-token-form-calculate"]').attr('content'));
    if(res.response == 'ok'){
        Swal.fire('Excelente!','El usuario tiene cupo disponible','success')
        .then((value)=>{
            document.querySelector('#cupoDisponible').value = res.cupoDisponible;
            document.querySelector('#send-petition').style = 'display:block;';
        });
    }else{
        Swal.fire('Lo sentimos!','No es posible iniciar el proceso ya que el usuario no cuenta con cupo disponible','error')
        .then((value)=>{
            document.querySelector('#cupoDisponible').value = res.cupoDisponible;
        });
    }
}
const startProcess = async function(){
    let dataToSend = new FormData();
    dataToSend.append('idProceso',document.querySelector('#idProceso').value);
    dataToSend.append('cupo',document.querySelector('#cupoDisponible').value);
    let res = await makeOptionsFetch(`${globalUrl}/iniciar-proceso-credito`,dataToSend,'post',$('meta[name="csrf-token-form-calculate"]').attr('content'));
    if(res.success){
        Swal.fire('Perfecto!',res.success,'success')
        .then((value)=>{
            backForm();
            location.reload();
        });
    }
}
// const formatMoney = function(element){
//     let formato = new Intl.NumberFormat('es-CO',{
//         style:'currency',
//         currency:'COP',
//         minimumFractionDigits:0
//     });
//     let nuevoValor = formato.format(parseInt(element.value));
//     element.value = nuevoValor;
//     console.log(nuevoValor);
// }