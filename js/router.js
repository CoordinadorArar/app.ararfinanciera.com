/**Cambiar ruta sin recargar página */
function changeUrl(route,event){
    event.preventDefault();
    //window.location.pathname = route;
    console.log(window.location.pathname+' -- '+route)
    /*urlSplit = route.split('/');
    if(urlSplit.length == 1){
        history.replaceState(null,'','/');
        history.pushState(null,'',route);//agregar la ruta nueva al path
    }else if(urlSplit.length > 1){
        history.replaceState(null,'','/');
        history.pushState(null,'','/');
        history.pushState(null,'',route);//agregar la ruta nueva al path
    }
    loadView();*/
}
//detectar cambio en la ruta al regresar o avanzar en el navegador para mostrar contenido en el elemento principal del sitio
async function loadView(){
    url = window.location.pathname;
    let dataToSend = new FormData();
    let res = await makeOptionsFetch(`${globalUrl+url}`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    document.getElementById('content-page').innerHTML = res;
    console.log(res);
}
// window.addEventListener("beforeunload", (evento) => {
//     if (true) {
//         evento.preventDefault();
//         evento.returnValue = "";
//         return "";
//     }
// });