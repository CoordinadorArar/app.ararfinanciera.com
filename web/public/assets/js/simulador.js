const toggleForm = () =>{
    let form = document.getElementById('simuladorForm');

    if(form.classList.contains('hide')){
        form.classList.remove('hide');
    }
}

function formatCurrency(input) {

    let value = input.value.replace(/[^0-9.]/g, '');

    const parts = value.split('.');
    if (parts.length > 2) {
        value = parts[0] + '.' + parts.slice(1).join('');
    }

    value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ",");

    input.value = value;
}

const calcularCuotas = async (data) => {
   try {
       const response = await fetch('simulador/creditos/calcular', {
           method: 'POST',
           headers: {
               'Content-Type': 'application/json',
               'X-Requested-With': 'XMLHttpRequest',
               'X-CSRF-Token': 'tu_token_csrf_aqui'
           },
           body: JSON.stringify(data)
       });

       if (!response.ok) {
           throw new Error('Error en la solicitud: ' + response.statusText);
       }

       const result = await response.json();
       mostrarResultado(result.html); 
   } catch (error) {
       console.error('Error:', error);
   }
};

const mostrarResultado = (html) => {
   const resultadoDiv = document.getElementById('resultado');

   const tablaHTML = `
        <h4 class="titulo-simulador2 mb-3">Simulación de Cuotas</h4>

        <div class="table-responsive"> <!-- Agregado para hacer la tabla responsive -->
            <table class="table table-bordered text-center">
                <thead>
                    <tr class="titulos-tabla">
                        <th>Número de Cuota</th>
                        <th>Valor Cuota Mensual</th>
                        <th>Amortización</th>
                        <th>Interés</th>
                        <th>Seguros</th>
                        <th>Total a Pagar</th>
                        <th>Saldo Restante</th>
                    </tr>
                </thead>
                <tbody>
                    ${html.replace(/<td>(\d+)<\/td>/g, '<td class="">$1</td>')}
                </tbody>
            </table>
        </div>
   `;
   resultadoDiv.innerHTML = tablaHTML; 
};


document.getElementById('simuladorForm').addEventListener('submit', function(event) {
    event.preventDefault(); 

    const periodoCredito = parseInt(document.getElementById('numeroCuotas').value);
    const valorCredito = parseFloat(document.getElementById('valorCredito').value.replace(/[$,]/g, '')); // Eliminar $ y ,
    const edadFecha = document.getElementById('rangoEdad').value;

    const datos = {
        periodoCredito: periodoCredito,
        info: '', 
        tasaInteres: 5, 
        valorCredito: valorCredito,
        edadFecha: edadFecha
    };

    calcularCuotas(datos);
});


document.getElementById('btnContinuar').addEventListener('click', function() {
    document.getElementById('formularioSimulador').scrollIntoView({ 
        behavior: 'smooth' 
    });
});
