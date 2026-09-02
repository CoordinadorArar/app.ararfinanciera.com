let globalUrl = window.location.origin;
let storageUrl = globalUrl + '/project/storage/app/public/';
//const salarioMinimoMensual = 1000000;
let htmlMenu = false;
// document.oncontextmenu = function(){return false}
// $(document).ready(function(){
//     $('html').on('mousedown',function(event){
//         if(event.button == 2){
//             Swal.fire({
//                 title:'Oops!',
//                 text:'Imposible usar el click derecho en el aplicativo',
//                 icon:'warning',
//                 confirmButttonText:'Entendido',
//                 confirmButtonColor:'rgb(65,110,195)'
//             })
//         }
//     });
// });
document.addEventListener('DOMContentLoaded',function(){
    validarEstadoUsuario();
    reloj();
});
/**Validar si el usuario está activo */
async function validarEstadoUsuario(){
    let dataToSend = new FormData();
    let res = await makeOptionsFetch(`${globalUrl}/validar-estado-usuario`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    if(res.res == 'inactivo'){
        Swal.fire({
            title:'Oops!',
            text:'El usuario está inactivo, la sesión no puede ser creada correctamente',
            icon:'error',
            confirmButtonText:'Entendido',
            allowOutsideClick:false,
            allowEscapeKey:false
        }).then(async (value)=>{
            if(value.isConfirmed){
                window.location = `${globalUrl}/log-out`;
            }
        })
    }else if(res.res == 'activo'){        
        mostrarMenu();
    }
}
/**Traer menu y crear html para mostrar en el DOM*/
async function mostrarMenu(){
    let dataToSend = new FormData();
    dataToSend.append('rutaActual',window.location.pathname);//Para marcar el submenu de la pagina abierta
    let res = await makeOptionsFetch(`${globalUrl}/menus`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    //console.log(res);
    document.getElementById('div-menu-content').innerHTML = res.ul;
    // if(!sessionStorage.getItem('menu')){
    //     let arrayMenu = [];
    //     let dataToSend = new FormData();
    //     let res = await makeOptionsFetch(`${globalUrl}/menus`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    //     let dataRol = await datosUsuario();
    //     let submenus = dataRol && await mostrarSubmenus(dataRol[0].IdRol);
    //     res.menus.forEach(element=>{//Crear array con todos los menus
    //         items = {
    //             'IdMenu':element.IdMenu,
    //             'NombreMenu': element.NombreMenu,
    //             'RutaMenu': element.RutaMenu,
    //             'CodigoMenu':element.CodigoMenu,
    //             'submenus':[]
    //         }
    //         arrayMenu.push(items);
    //     });
    //     html = ``;
    //     arrayMenu.forEach((element,index)=>{//Crear array copn los submenus según coincidan las relaciones en la base de datos
    //         submenus.forEach(items=>{
    //             if(element.IdMenu == items.IdMenu){
    //                 itemsMenu = {
    //                     'IdSubmenu':items.IdSubmenu,
    //                     'NombreSubmenu':items.NombreSubmenu,
    //                     'RutaSubmenu':items.RutaSubmenu,
    //                     'CodigoSubmenu':items.CodigoSubmenu
    //                 }
    //                 arrayMenu[index].submenus.push(itemsMenu);
    //             }
    //         });
    //     });
    //     arrayMenu.forEach(element=>{//Recorrer array de menus para crear el HTML necesario para mostrar en el DOM
    //         submenusHtml = element.submenus.length > 0 ? `<ul class="dropdown-menu text-small shadow" aria-labelledby="dropdown">` : ``;
    //         let csrf_token = '<?php echo csrf_token(); ?>';
    //         element.submenus.forEach(items=>{
    //             if(items.RutaSubmenu == 'logout'){
    //                 submenusHtml += `<li>
    //                                     <a class="dropdown-item" href="#" onclick="logOut()">${items.CodigoSubmenu} ${items.NombreSubmenu}</a>
    //                                 </li>`;
    //             }else{
    //                 submenusHtml += `<li>
    //                                     <a class="dropdown-item" href="${globalUrl+items.RutaSubmenu}">${items.CodigoSubmenu} ${items.NombreSubmenu}</a>
    //                                 </li>`;
    //             }
    //         })
    //         submenusHtml += element.submenus.length > 0 ? `</ul>` : ``;
    //         html += `<li class="${element.submenus.length > 0 ? 'dropdown' : 'nav-item'}">
    //                     <a href="${element.submenus.length > 0 ? '#' : element.RutaMenu}" class="nav-link text-truncate${element.submenus.length > 0 ? ' dropdown-toggle' : ''}" data-bs-toggle="${element.submenus.length > 0 && 'dropdown'}">
    //                         ${element.CodigoMenu} <span class="ms-1 d-none d-sm-inline">${element.NombreMenu}</span>
    //                     </a>
    //                     ${submenusHtml}
    //                 </li>`;
    //     });
    //     let ul = document.createElement('ul');
    //     ul.className = 'nav nav-pills flex-column mb-sm-auto mb-0 align-items-start';
    //     ul.setAttribute('id','menu');
    //     ul.innerHTML = html;
    //     document.querySelector('#div-menu-content').appendChild(ul);
    //     sessionStorage.setItem('menu','<ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-start" id="menu">'+html+'</ul>');
    // }else{
    //     /**Traer menu de los datos de sessionStorage */
    //     let parser = new DOMParser();
    //     htmlMenu = parser.parseFromString(sessionStorage.getItem('menu'),'text/html').body;
    //     console.log(htmlMenu);
    //     document.querySelector('#div-menu-content').appendChild(htmlMenu.childNodes[0]);
    // }
}
async function datosUsuario(){
    let dataToSend = new FormData();
    let res = await makeOptionsFetch(`${globalUrl}/informacion-usuario`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    return res.userData;
}
async function mostrarSubmenus(id){
    let dataToSend = new FormData();
    dataToSend.append('IdRol',id);
    let res = await makeOptionsFetch(`${globalUrl}/submenus`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    return res.submenus;
}
/**Cerrar Sesión */
const logOut = function(){
    if(sessionStorage.getItem('menu')){
        sessionStorage.removeItem('menu');
    }
    document.getElementById('logout-form').submit();
}
/**Reloj en vivo */
const reloj = async function(){
    let momentoActual = new Date();
    hora = momentoActual.getHours();
    minuto = momentoActual.getMinutes();
    segundo = momentoActual.getSeconds();
    anio = momentoActual.getFullYear();
    let mes = momentoActual.getMonth();
    let dia = momentoActual.getDay();
    let diaMes = momentoActual.getDate();
    let dias = ['Domingo','Lunes','Martes','Miercoles','Jueves','Viernes','Sábado'];
    let meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    for(let i = 0; i <= dias.length; i++){
        if(i == dia){
            dia = dias[i];
        }
    }
    for(let i = 0; i <= meses.length; i++){
        if(i == mes){
            mes = meses[i];
        }
    }

    str_segundo = new String (segundo);
    if(str_segundo.length == 1){
       segundo = "0" + segundo;
    }
    str_minuto = new String (minuto);
    if(str_minuto.length == 1){
       minuto = "0" + minuto;
    }
    str_hora = new String (hora);
    if(str_hora.length == 1){
        hora = "0" + hora;
    }
    horaImprimible = hora+' : '+minuto+' : '+segundo+' - '+dia+' '+diaMes+' de '+mes+' de '+anio;
    document.querySelector('#form-reloj #reloj').value = horaImprimible;
    setTimeout("reloj()",1000);
}