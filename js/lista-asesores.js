const mostrarProcesosAsesor = async function(idAsesor){
    let dataToSend = new FormData();
    dataToSend.append('idAsesor',idAsesor);
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-procesos-asesor`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
}
setTimeout(() => {
    crearTablaResponsiva('table-procesos');
},1000);

/**Mostrar información del proceso seleccionado */
const mostrarInfoProcesos = async function(idProceso){
    $('#div-info-proceso').preloader();
    document.querySelector('#div-info-proceso').style.display = 'block';
    document.querySelector('#div-table-procesos').style.display = 'none';
    let dataToSend = new FormData();
    dataToSend.append('idProceso',idProceso); dataToSend.append('estado','');
    let res = await makeOptionsFetch(`${globalUrl}/proceso-solo-info`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    if(res.proceso){
        console.log(res.proceso);
        $('#div-info-proceso').preloader('remove');
        let html = '';
        res.proceso.forEach(element=>{
            html += `<div class="card-body">
                        <button class="btn btn-secondary btn-sm" onclick="backTable()"><i class="fa fa-arrow-left"></i> Volver</button>
                        <h5 class="text-center">${element.NombresTercero} ${element.ApellidosTercero}</h5><hr>
                        <div class="row">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <p>Pagaduria: ${element.NombrePagaduria}</p>
                                <p></p>
                            </div>
                        </div>
                    </div>`;
        });
        document.querySelector('#div-info-proceso').innerHTML = html;
    }
}

const backTable = function(){
    document.querySelector('#div-info-proceso').style.display = 'none';
    document.querySelector('#div-table-procesos').style.display = 'block';
}

const crearGrafica = function(){
    const ctx = document.getElementById('grafica').getContext('2d');
    const myChart = new Chart(ctx,{
        type: 'bar',
        data: {
            labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
            datasets: [{
                label: '# of Votes',
                data: [12, 19, 3, 5, 2, 3],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(255, 159, 64, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}