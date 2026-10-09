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
const MENU_ABIERTOS = 'arar.menu.abiertos';
document.addEventListener('DOMContentLoaded',function(){
    let raiz = document.documentElement;
    let btnMenu = document.getElementById('shellBtnMenuEscritorio');
    let marcarMenu = function(){
        let oculto = raiz.classList.contains('shell-menu-oculto');
        let texto = oculto ? 'Mostrar menú' : 'Ocultar menú';
        btnMenu.setAttribute('aria-expanded', oculto ? 'false' : 'true');
        btnMenu.setAttribute('aria-label', texto);
        btnMenu.title = texto;
    };
    if(btnMenu){
        marcarMenu();
        btnMenu.addEventListener('click', function(){
            raiz.classList.add('shell-menu-anim');
            let oculto = raiz.classList.toggle('shell-menu-oculto');
            try{ localStorage.setItem('shellMenuOculto', oculto ? '1' : '0'); }catch(e){}
            marcarMenu();
            setTimeout(()=>window.dispatchEvent(new Event('resize')), 260);
        });
    }
    let contenedor = document.getElementById('div-menu-content');
    let guardar = function(){
        try{
            localStorage.setItem(MENU_ABIERTOS, JSON.stringify(Array.from(contenedor.querySelectorAll('.submenu-collapse.show'), c=>c.id)));
        }catch(e){}
    };
    contenedor.addEventListener('shown.bs.collapse', guardar);
    contenedor.addEventListener('hidden.bs.collapse', guardar);
    let ultimoGrupo = null;
    let cerrarOtros = function(actual){
        contenedor.querySelectorAll('.submenu-collapse.show').forEach(el=>{
            if(el !== actual) bootstrap.Collapse.getOrCreateInstance(el, {toggle:false}).hide();
        });
    };
    contenedor.addEventListener('show.bs.collapse', function(e){
        if(!e.target.classList.contains('submenu-collapse')) return;
        ultimoGrupo = e.target;
        cerrarOtros(e.target);
    });
    contenedor.addEventListener('shown.bs.collapse', function(e){
        if(!e.target.classList.contains('submenu-collapse')) return;
        if(e.target !== ultimoGrupo) bootstrap.Collapse.getOrCreateInstance(e.target, {toggle:false}).hide();
        else cerrarOtros(e.target);
    });
    contenedor.addEventListener('click', function(e){
        if(e.target.closest('[data-logout]')){
            e.preventDefault();
            logOut();
        }
    });
    validarEstadoUsuario();
});
async function validarEstadoUsuario(){
    let res = await makeOptionsFetch(`${globalUrl}/validar-estado-usuario`,new FormData(),'post',$('meta[name="csrf-token-menus"]').attr('content'),true).catch(()=>({}));
    if(res.res == 'inactivo'){
        avisoBloqueante('Usuario inactivo','El usuario está inactivo, la sesión no puede continuar.','error','Entendido','/log-out');
    }else{
        mostrarMenu();
    }
}
async function mostrarMenu(){
    let contenedor = document.getElementById('div-menu-content');
    contenedor.setAttribute('aria-busy','true');
    contenedor.innerHTML = '<span class="shell-menu-esqueleto"></span>'.repeat(6) + '<span class="visually-hidden" role="status">Cargando menú…</span>';
    let dataToSend = new FormData();
    dataToSend.append('rutaActual',window.location.pathname);
    try{
        let res = await makeOptionsFetch(`${globalUrl}/menus`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'),true);
        let plantilla = document.createElement('template');
        plantilla.innerHTML = res.ul;
        let abiertos = [];
        try{
            abiertos = JSON.parse(localStorage.getItem(MENU_ABIERTOS)) || [];
        }catch(e){}
        let grupos = Array.from(plantilla.content.querySelectorAll('.submenu-collapse'));
        let restaurar = grupos.some(g=>g.classList.contains('show')) ? undefined : abiertos.map(id=>grupos.find(g=>g.id === id)).find(Boolean);
        grupos.forEach(grupo=>{
            if(grupo === restaurar){
                let boton = plantilla.content.querySelector(`[aria-controls="${grupo.id}"]`);
                grupo.classList.add('show');
                boton && boton.classList.remove('collapsed');
                boton && boton.setAttribute('aria-expanded','true');
            }
        });
        contenedor.replaceChildren(plantilla.content);
        let activo = contenedor.querySelector('[aria-current="page"]');
        if(activo && window.matchMedia('(min-width: 992px)').matches){
            activo.scrollIntoView({block:'nearest'});
        }
    }catch(e){
        contenedor.innerHTML = '<div class="shell-menu-error" role="alert"><p class="mb-2">No fue posible cargar el menú.</p><button type="button" class="btn btn-sm btn-light" onclick="mostrarMenu()">Reintentar</button></div>';
    }
    contenedor.removeAttribute('aria-busy');
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
