const onSubmit = async(e)=>{
    e.preventDefault();

    let form = document.querySelector('#form');
    let formData = new FormData(form);
    let action = form.getAttribute('action'); // obtiene la URL de Laravel

    let response = await fetch(action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token-login"]').attr('content'),
            'X-Requested-With': 'XMLHttpRequest'
        }
    });

    let data = await response.json();

    if(data.res == 'ok'){
        window.location.href = '/';
    }else{
        if (data.details == 'no seguro') {
            Swal.fire('Error!', data.message,'warning');
            setTimeout(()=>{
                window.location.href = 'password/reset';
            }, 2000);
        } else if (data.details == 'incorrecta') {
            Swal.fire('Error!',data.message,'warning');
        } else {
            Swal.fire('Error!',data.message,'warning');
        }
    }
}

