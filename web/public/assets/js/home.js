const envioForm = async (event) => {
    event.preventDefault();

    let form = document.getElementById('form-solicitar');

    let data = new FormData(form);

    let request = await fetch(`${base_url}envioCorreoSolicitud`, {body: data, method: 'post'})
    let response = await request.json();

    if(response.status == 'ok'){
        Swal.fire({
            title: '¡Éxito!',
            text: response.messagge,
            icon: 'success',
            confirmButtonText: 'Aceptar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Limpiar el formulario
                document.getElementById('form-solicitar').reset();
            }
        });

    }else{
        Swal.fire({
            'icon': 'error',
            'title': 'Oops!',
            'text': response.messagge
        });
    }
}