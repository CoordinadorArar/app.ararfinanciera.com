const envioForm = async (event) => {
    event.preventDefault();

    let form = document.getElementById('form-contacto');

    let data = new FormData(form);

    let request = await fetch(`${base_url}envioCorreoContacto`, {body: data, method: 'post'})
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
                document.getElementById('form-contacto').reset();
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