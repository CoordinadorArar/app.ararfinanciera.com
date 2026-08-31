const register = async(e)=>{
    $('#register').preloader();
    e.preventDefault();
    errorElements = document.getElementsByClassName('invalid-feedback');
    for(let i = 0; i < errorElements.length; i++){
        errorElements[i].style.display = 'none';
    }
    let form = document.querySelector('#form');
    window.CSRF_TOKEN = '{{ csrf_token() }}';
    let formData = new FormData(form);
    let response = await fetch(globalUrl+'/register',{
        body:formData,
        method:'post',
        headers:{'X-CSRF-TOKEN':window.CSRF_TOKEN }
    })
    let data = await response.json();
    if(data.errors){
        $('#register').preloader('remove');
        showErrors(data);
    }else{
        $('#register').preloader('remove');
        Swal.fire({title:'Perfecto!',text:data.success,icon:'success',allowOutsideClick:false,allowEscapeKey:false})
        .then((value)=>{
            if(value.isConfirmed){
                location.reload('http://app.ararfinanciera.com/login');
            }
        });
    }
}
const showPass = function(){
    let pass = document.querySelector('#password');
    let confirm = document.querySelector('#confirmPassword');
    if(pass.type == 'text'){
        pass.type = 'password'; confirm.type = 'password';
    }else{
        pass.type = 'text'; confirm.type = 'text';
    }
}