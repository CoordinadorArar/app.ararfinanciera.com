/**Variables locales para usar mas adelante */
let nombreInput = '', documentoInput = '', emailInput = '', rolSelect = '0';
/**Cargar roles */
const mostrarRoles = async function(){
    let dataToSend = new FormData();
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-roles`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    html = '<option value="">--Lista de roles--</option>';
    res.roles.forEach(element=>{
        html += `<option value="${element.IdRol}">${element.NombreRol}</option>`;
    });
    document.querySelector('#rol-user').innerHTML = html;
}
/**Traer datos del usuario seleccionado */
const mostrarInfo = async function(idUser){
    mostrarRoles();
    $('#info-user').preloader();//llamar icono de carga
    document.querySelector('#title-info-user').style.display = 'none';
    document.querySelector('#div-form-user').style.display = 'block';
    let dataToSend = new FormData();
    dataToSend.append('idUsuario',idUser);
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-usuario`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    if(res){
        $('#info-user').preloader('remove');//remover icono de carga
        res.forEach(element=>{//llenar campos con información traida
            document.querySelector('#nombreUsuario').value = element.nombreUsuario; /*--*/ nombreInput = element.nombreUsuario;
            document.querySelector('#documentoUsuario').value = element.documentoUsuario; /*--*/ documentoInput = element.documentoUsuario;
            document.querySelector('#email').value = element.email; /*--*/ emailInput = element.email;
            document.querySelector('#id-user-update').value = element.IdUsuario;
            $(`#rol-user option[value=${element.IdRol}]`).prop('selected',true); /*--*/ rolSelect = element.IdRol;
            if(element.estadoUsuario == 0){
                document.querySelector('#btn-enable-user').disabled = false;
                document.querySelector('#btn-disable-user').disabled = true;
                document.getElementById('name-user').innerHTML = element.nombreUsuario+' | <span class="text-danger">Usuario inactivo</span>';
            }else{
                document.querySelector('#btn-enable-user').disabled = true;
                document.querySelector('#btn-disable-user').disabled = false;
                document.getElementById('name-user').innerHTML = element.nombreUsuario+' | <span class="text-success">Usuario activo</span>';
            }
        });
    }
}
const actualizarInfo = function(e){
    e.preventDefault();
    Swal.fire({
        title:'¿Actualizar datos?',
        text:'¿Estás seguro de modificar los datos del usuario?',
        icon:'warning',
        showCancelButton:true,
        confirmButtonText:'Si, actualizar',
        cancelButtonText:'Cancelar'
    }).then(async (value)=>{
        if(value.isConfirmed){
            let form = document.getElementById('form-user-update');
            let dataToSend = new FormData(form);
            let id = document.querySelector('#id-user-update').value;
            let res = await makeOptionsFetch(`${globalUrl}/editar-usuarios`,dataToSend,'post',$('meta[name="csrf-token-form-user-update"]').attr('content'));
            console.log(res);
            Swal.fire('Hecho!',res.success,'success');
            mostrarInfo(id);
        }else{
            document.querySelector('#nombreUsuario').value = nombreInput;
            document.querySelector('#documentoUsuario').value = documentoInput;
            document.querySelector('#email').value = emailInput;
            $(`#rol-user option[value=${rolSelect}]`).prop('selected',true);
        }
    });
}

const cambiarEstado = async function(estado){
    console.log(estado); let config;
    if(estado == 0){
        config = {
            title:'¿Inactivar?',
            text:'¿Seguro que quieres desactivar a este usuario?',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Si, desactivar',
            cancelButtonText:'Cancelar'
        }
    }else if(estado == 1){
        config = {
            title:'Activar?',
            text:'¿Seguro que quieres activar a este usuario?',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Si, activar',
            cancelButtonText:'Cancelar'
        }
    }
    Swal.fire(config).then(async(value)=>{
        if(value.isConfirmed){
            let dataToSend = new FormData();
            let id = document.querySelector('#id-user-update').value
            dataToSend.append('estado',estado); dataToSend.append('idUsuario',id);
            let res = await makeOptionsFetch(`${globalUrl}/editar-usuarios`,dataToSend,'post',$('meta[name="csrf-token-form-user-update"]').attr('content'));
            console.log(res);
            Swal.fire('Hecho!',res.success,'success');
            mostrarInfo(id);
        }
    });
}