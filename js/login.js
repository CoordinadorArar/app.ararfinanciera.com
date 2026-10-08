const onSubmit = async(e)=>{
    e.preventDefault();

    let form = document.querySelector('#form');
    let formData = new FormData(form);
    let action = form.getAttribute('action'); // obtiene la URL de Laravel
    let boton = form.querySelector('.auth-btn');
    let textoBoton = boton.innerHTML;

    boton.disabled = true;
    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Ingresando…';

    let data;
    try {
        let response = await fetch(action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token-login"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (response.status == 419) {
            data = { message: 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.' };
        } else {
            data = await response.json();
            if (response.status == 422) {
                data = { message: Object.values(data.errors || {})[0]?.[0] || 'Revisa los datos ingresados.' };
            }
        }
    } catch (error) {
        data = { message: 'No fue posible iniciar sesión. Intenta de nuevo.' };
    }

    if(data.res == 'ok'){
        window.location.href = '/';
    }else{
        boton.disabled = false;
        boton.innerHTML = textoBoton;
        if (data.details == 'no seguro') {
            Swal.fire('Atención', data.message,'warning');
            setTimeout(()=>{
                window.location.href = 'password/reset';
            }, 2000);
        } else if (data.details == 'incorrecta') {
            Swal.fire('Atención',data.message,'warning');
        } else {
            Swal.fire('Atención',data.message,'warning');
        }
    }
}
