const mostrarProcesosAsesor = async function(idAsesor){
    let dataToSend = new FormData();
    dataToSend.append('idAsesor',idAsesor);
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-procesos-asesor`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
}
setTimeout(() => {
    crearTablaResponsiva('table-procesos');
},1000);
let filasProcesos = null;
let filtroProcesos = null;
const filterTable = function(estado,boton){
    if(!$.fn.dataTable.isDataTable('#table-procesos')) return;
    const tabla = $('#table-procesos').DataTable();
    filasProcesos = filasProcesos || tabla.rows().nodes().toArray();
    filtroProcesos = filtroProcesos === estado ? null : estado;
    tabla.clear().rows.add(filasProcesos.filter(fila=>{
        const valor = Number(fila.dataset.estado);
        return filtroProcesos === null || (filtroProcesos === 'any' ? [1,2,3,4].includes(valor) : valor === filtroProcesos);
    })).draw();
    $('#filter-buttons > button').removeClass('active');
    $(boton).toggleClass('active',filtroProcesos !== null);
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