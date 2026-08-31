/**Habilitar edición de campos */
const enableInputs = function(){
    document.querySelector('#btn-enable').style = 'display:none;';
    document.querySelector('#btn-save').style = 'display:inline;';
    document.querySelector('#btn-cancel').style = 'display:inline;';
    let inputs = document.getElementsByClassName('input-data');
    for(let i = 0; i < inputs.length; i++){
        inputs[i].disabled = false;
    }
}
const cancel = function(){
    document.querySelector('#btn-enable').style = 'display:inline;';
    document.querySelector('#btn-save').style = 'display:none;';
    document.querySelector('#btn-cancel').style = 'display:none;';
    let inputs = document.getElementsByClassName('input-data');
    for(let i = 0; i < inputs.length; i++){
        inputs[i].disabled = true;
    }
}

/**Guardar datos actualizados */
const guardarDatos = async function(e){
    e.preventDefault();
    let form = document.getElementById('form-data-user');
    let dataToSend = new FormData(form);
    let res = await makeOptionsFetch(`${globalUrl}/editar-datos-usuario`,dataToSend,'post',$('meta[name="csrf-token-form-data"]').attr('content'));
    if(res.errors){
        showErrors(res);
    }else{
        Swal.fire('Listo!',res.success,'success')
        .then((value)=>{
            let inputs = document.getElementsByClassName('input-data');
            for(let i=0;i< inputs.length; i++){
                inputs[i].value = '';
            }
            cancel();
            document.querySelector('#nombreUsuario').value = res.data[0].nombreUsuario;
            document.querySelector('#email').value = res.data[0].email;
            document.querySelector('#documento').value = res.data[0].documentoUsuario;
        });
    }
}

/**Modificar contraseña */
const changePassword = async function(e){
    errorElements = document.getElementsByClassName('invalid-feedback');
    for(let i = 0; i < errorElements.length; i++){
        errorElements[i].style.display = 'none';
    }
    e.preventDefault();
    let form = document.getElementById('form-password');
    let dataToSend = new FormData(form);
    let res = await makeOptionsFetch(`${globalUrl}/editar-contrasena`,dataToSend,'post',$('meta[name="csrf-token-form-password"]').attr('content'));
    console.log(res);
    if(res.errors){
        showErrors(res);
    }else{
        Swal.fire('Listo!',res.success,'success')
        .then((value)=>{
            document.querySelector('#password').value = '';
            document.querySelector('#confirmPassword').value = '';
        });
    }
}

const cargarBotonEnvio = function(){
    if(document.getElementById('img-perfil').value != null && document.getElementById('img-perfil').value != ''){
        document.getElementById('div-boton-subida').style.display = 'block';
    }else{
        document.getElementById('div-boton-subida').style.display = 'none';
    }
}

const subirFoto = async function(){
    let img = document.getElementById('img-perfil');
    let dataToSend = new FormData();
    dataToSend.append('photo', img.files[0]);
    let res = await makeOptionsFetch(`${globalUrl}/subir-foto-perfil`,dataToSend,'post',$('meta[name="csrf-token-upload-photo"]').attr('content'));
    console.log(res);
    if(res.success){
        Swal.fire({title:'Perfecto!',text:res.success,icon:'success',allowEscapeKey:false,allowOutsideClick:false})
        .then((value)=>{
            location.reload();
        });
    }
}
window.onload = function(){
    mostrarFotoPerfil();    
}
const mostrarFotoPerfil = async function(){
    let dataToSend = new FormData();
    let res = await fetch(`${globalUrl}/mostrar-foto-perfil/1095833971-perfil.jpg`,{method:'get',headers:$('meta[name="csrf-token-upload-photo"]').attr('content')})
    let req = await res.json();
    console.log(res);
    //document.getElementById('foto-perfil').innerHTML = ;
}