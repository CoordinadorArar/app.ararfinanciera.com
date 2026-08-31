window.onload = function(){
    let rol = document.getElementById('rol-usuario-validar');
    if(rol.value != 1){
        document.getElementById('card-roles').addEventListener('click', function(){
            Swal.fire('Oops!','Esta sección es solo editable para un administrador','info')
        });
        document.querySelector('#card-roles input').setAttribute('disabled', 'disabled');
        document.querySelector('#card-roles select').setAttribute('disabled', 'disabled');
        let buttons = document.querySelectorAll('#card-roles button');
        for(let i = 0; i < buttons.length; i++){
            buttons[i].setAttribute('disabled', 'disabled');
        }
    }
}
/**Mostrar datos de rol seleccionado en formulario */
const gestionRol = async function(accion,idRol=''){
    document.getElementById('inputNuevoRol').style.display = 'block';
    let dataToSend = new FormData();
    let htmlSubmeus = '';
    if(accion == 'nuevo'){
        dataToSend.append('IdRol','');
        let res = await makeOptionsFetch(`${globalUrl}/submenus`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'));
        document.getElementById('nombreRol').value = '';
        res.submenus.forEach(element=>{
            if(element.NombreSubmenu != 'Cerrar sesión'){
                input = `<input type="checkbox" class="checkboxRol" id="submenu-${element.IdSubmenu}" name="submenu-${element.IdSubmenu}">`;
                htmlSubmeus += `<div class="d-flex align-items-center justify-content-between my-1">
                                    <label for="">${element.NombreSubmenu}</label>
                                    ${input}
                                </div>`;
            }
        });
        document.getElementById('btnGuardarRol').innerHTML = 'Guardar';
        document.getElementById('submenusRol').innerHTML = htmlSubmeus;
    }else if(accion == 'editar'){
        dataToSend.append('peticion','roles'); dataToSend.append('idRol',idRol);
        let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-admin`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'));
        console.log(res);
        document.getElementById('nombreRol').value = res['rol'][0].NombreRol;
        res['submenusTodos'].forEach(async function(element){
            if(element.NombreSubmenu != 'Cerrar sesión'){
                let input = `<input type="checkbox" class="checkboxRol" id="submenu-${element.IdSubmenu}" name="submenu-${element.IdSubmenu}">`;
                htmlSubmeus += `<div class="d-flex align-items-center justify-content-between my-1">
                                    <label for="">${element.NombreSubmenu}</label>
                                    ${input}
                                </div>`;
            }
        });
        document.getElementById('btnGuardarRol').innerHTML = 'Actualizar';
        document.getElementById('submenusRol').innerHTML = htmlSubmeus;
        let inputs = document.getElementsByClassName('checkboxRol');
        for(let i=0; i <= inputs.length; i++){
            res['submenusRol'].forEach(function(item2){
                if(item2.IdSubmenu == inputs[i].getAttribute('id').split('-')[1]){
                    inputs[i].checked = true;
                }
            });
        }
    }
    document.getElementById('cancelRol').style.display = 'inline-block';
    //selectSubmenus();
}
// const selectSubmenus = async function(idRol){
//     let inputs = document.getElementsByClassName('checkboxRol');
//     for(let i=0; i<inputs.length; i++){
//         if(inputs[i].checked){
//             console.log();
//         }
//     }
//     let dataToSend = new FormData();
//     dataToSend.append('idRol', idRol);
//     let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-admin`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'));
// }
const guardarRol = async function(e){
    e.preventDefault();
    let inputs = document.getElementsByClassName('checkboxRol');
    let submenusSeleccionados = [];
    let submenusEliminados = [];
    for(let i = 0; i < inputs.length; i++){
        if(inputs[i].checked){
            let idSubmenu = inputs[i].getAttribute('id').split('-')[1];
            submenusSeleccionados.push(idSubmenu);
        }else{
            let idSubmenu = inputs[i].getAttribute('id').split('-')[1];
            submenusEliminados.push(idSubmenu);
        }
    }
    let dataToSend = new FormData();
    dataToSend.append('idRol',document.getElementById('listaRoles').value);
    dataToSend.append('submenus',JSON.stringify(submenusSeleccionados));
    dataToSend.append('submenusEliminados',JSON.stringify(submenusEliminados));
    dataToSend.append('option','roles');
    let res = await makeOptionsFetch(`${globalUrl}/guardar-datos-sitio`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'));
    if(res.res == 'ok'){
        Swal.fire({title:'Perfecto!',text:'Permisos modificados correctamente',icon:'success',confirmButtonText:'Entendido'})
        .then((value)=>{
            if(value.isConfirmed){
                cancelarGuardado('rol');
            }
        });
    }else{
        Swal.fire({title:'Oops!',text:'Surgieron problemas al intentar agregar un permiso',icon:'error',confirmButtonText:'Entendido',allowEscapeKey:false,alloOutsideClick:false})
        .then((value)=>{
            if(value.isConfirmed){
                cancelarGuardado('rol');
            }
        });
    }
}
/**Mostrar datos de variable seleccionada en formulario */
const editarValorVariable = async function(idVariable){
    let dataToSend = new FormData();
    dataToSend.append('peticion','variables'); dataToSend.append('idVariable',idVariable);
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-admin`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'));
    document.getElementById('nombreVariable').value = res[0].NombreValorVariable;
    document.getElementById('valorVariable').value = res[0].ValorVariable;
    document.getElementById('btnGuardarVariable').innerHTML = 'Actualizar';
    document.getElementById('cancelVariable').style.display = 'inline-block';
    document.getElementById('idVariable').value = idVariable;
}
/**Limpiar inputs de valores traidos */
const cancelarGuardado = function(section){
    switch (section){
        case 'rol':
            document.getElementById('nombreRol').value = '';
            document.getElementById('submenusRol').innerHTML = '';
            document.getElementById('btnGuardarRol').innerHTML = 'Guardar';
            document.getElementById('cancelRol').style.display = 'none';
            document.getElementById('inputNuevoRol').style.display = 'none';
            $('#listaRoles option[value="0"]').prop('selected', true);
        break;
        case 'variable':
            document.getElementById('nombreVariable').value = '';
            document.getElementById('valorVariable').value = '';
            document.getElementById('btnGuardarVariable').innerHTML = 'Guardar';
            document.getElementById('cancelVariable').style.display = 'none';
            document.getElementById('idVariable').value = '';
        break;
    }
}
/**Guardar datos */
const guardarVariable = async function(e){
    e.preventDefault();
    let form = document.getElementById('form-variables');
    let dataToSend = new FormData(form);
    dataToSend.append('idVariable', document.getElementById('idVariable').value);
    dataToSend.append('option','variables');
    let res = await makeOptionsFetch(`${globalUrl}/guardar-datos-sitio`,dataToSend,'post',$('meta[name="csrf-token-admin-management"]').prop('content'));
    if(res == 'ok'){
        Swal.fire({title:'Perfecto!',text:'Los datos han sido ingresados con exito',icon:'success',confirmButtonText:'Entendido'})
        .then((value)=>{
            if(value.isConfirmed){
                location.reload();
            }
        });
    }else if(res.errors){
        showErrors(res);
    }else{
        Swal.fire({title:'Oops!',text:'Algo falló al intentar guardara los datos',icon:'error',confirmButtonText:'Entendido'})
    }
}

const soloNumeros = (event) => {
  let charCode = event.which ? event.which : event.keyCode;
  if (charCode < 48 || charCode > 57) {
      event.preventDefault(); // Bloquea la entrada de caracteres no numéricos
  }
}

const noStrangeCharacters = (e)=>{
  let allowedKeys = ["Backspace", "Delete", "ArrowLeft", "ArrowRight", "Tab", "Enter"];
  let patron = /^[a-zA-Z0-9@ _.,#\-$áéíóúÁÉÍÓÚñÑ]$/; // Expresión corregida
  let tecla = e.key; // Captura la tecla presionada

  if (allowedKeys.includes(tecla) || patron.test(tecla)) {
      return true; // Permite la tecla
  }

  e.preventDefault();
  return false; // Bloquea la tecla no permitida
}