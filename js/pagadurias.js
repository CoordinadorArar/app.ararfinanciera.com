/**Variables necesarias para toda la vista */
let arrayInputs = [];
let vistaConfiguracion = '';
/**Mostrat pagadurias */
const mostrarPagadurias = async function(){
    let dataToSend = new FormData();
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-pagadurias`,dataToSend,'post',$('meta[name="csrf-token-menus"]').attr('content'));
    html = `<option value="">--Elije una pagaduria--</option>`;
    res.pagadurias.forEach(element=>{
        html += `<option value="${element.IdPagaduria}">${element.NombrePagaduria}</option>`;
    });
    document.querySelector('#lista-pagadurias').innerHTML = html;
}
/**Guardat pagaduria */
const guardarPagaduria = async function(e){
    e.preventDefault();
    let form = document.getElementById('form-pagaduria');
    let dataToSend = new FormData(form); dataToSend.append('action', 'infoBasica');
    let res = await makeOptionsFetch(`${globalUrl}/guardar-pagaduria-info`,dataToSend,'post',$('meta[name="csrf-token-form-pagaduria"]').attr('content'));
    console.log(res);
    if(res.errors){
        showErrors(res);
    }else{
        Swal.fire('Correcto!',res.res,'success')
        .then((value)=>{
            mostrarPagadurias();
        })
    }
}
/**Mostrar información sobre pagaduria seleccionada */
const mostrarInfoPagaduria = async function(id,idConfiguracion=''){
    vistaConfiguracion = ''; //variable global que guarda el string de la operacion
    let dataToSend = new FormData();
    dataToSend.append('IdPagaduria',id);
    idConfiguracion != '' && dataToSend.append('idConfiguracion',idConfiguracion); //si llega el idConfiguracion se agrega al request
    let res = await makeOptionsFetch(`${globalUrl}/mostrar-info-pagaduria`,dataToSend,'post',$('meta[name="csrf-token-form-pagaduria"]').attr('content'));
    if(res.pagaduria){
        html = ``; vistaPrevia = `<p class="lead" id="p-operacion">Operación completa: `;
        res.pagaduria.forEach(element=>{ //recorrer datos traidos de las configuraciones
            document.getElementById('id-config-pagaduria').value = element.IdConfigCalculo;
            html += `<div class="d-block">`;
            configSplit = element.Configuracion.split('|'); //el string esta separado por el caracter |, lo separo para tener control de cada elemento de la operacion
            for(let i = 0; i < configSplit.length; i++){ //recorro el array creado al separar el string
                let longitudString = configSplit[i].split('').length; 
                if(longitudString == 1){ //compruebo si el elemento de la operacion es un simbolo o una variable por medio de la longitud de sus caracteres
                    /**Creo el boton para los operadores y numeros */
                    html += `<button 
                                class="btn btn-sm btn-symbol input-config-drag" 
                                style="width:initial;" id="${configSplit[i]}_${i}"
                                draggable="true" 
                                ondragstart="eventDragDrop(this)">
                                ${configSplit[i]}
                            </button>`;
                    document.getElementById('form-configuracion').clientWidth
                }else{
                    /**Creo el boton para las variables */
                    html += `<button 
                                class="btn btn-sm btn-value input-config-drag" 
                                style="width:auto;" id="${configSplit[i]}_${i}"
                                draggable="true" 
                                ondragstart="eventDragDrop(this)">
                                ${configSplit[i]}
                            </button>`;
                }
                vistaPrevia += `${configSplit[i]}`; // string que se mostrara como vista previa en un elemento p
                vistaConfiguracion += `${configSplit[i]}_${i}|`; //asignamos el string generado a la variable global
            }
        });
        vistaPrevia += `</p>`;
        /**Creacion del boton de eliminar elemento de la operacion */
        html += `</div>
                <br>
                <div class="text-center">
                    <button class="btn btn-danger" id="eliminarElemento">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <hr>${vistaPrevia}`;
        document.getElementById('form-configuracion').innerHTML = html; //impresion de elementos en el DOM
    }
}
/**Mostrar select si la pagaduria tiene varios tipos de descuentos segun salario */
const validarTipoDescuento = async function(id){
    let IdPagaduria = id;
    let dataToSend = new FormData();
    dataToSend.append('IdPagaduria',IdPagaduria);
    let res = await makeOptionsFetch(`${globalUrl}/cantidad-config-pagaduria`,dataToSend,'post',$('meta[name="csrf-token-form-pagaduria"]').attr('content'));
    if(res.length > 1){ //si llegan mas de una configuracion para la pagaduria crea un select para elegir que configuracion mostrar en el DOM
        document.getElementById('id-configuracion').style.display = 'inline-block';
        let html = '<option value="">- Elije el tipo de descuento -</option>';
        res.forEach(element=>{
            let valueHtml = (element.TipoDescuentoMaximo == '%')? 'Mayor a 2 SMMLV' : 'Menor a 2 SMMLV' ;
            html += `<option value="${element.IdConfigCalculo}">${valueHtml}</option>`;
        });
        document.getElementById('id-configuracion').innerHTML = html;
        document.getElementById('id-configuracion').addEventListener('change',function(){
            let idConfiguracion = document.getElementById('id-configuracion').value;
            mostrarInfoPagaduria(IdPagaduria,idConfiguracion);
        });
    }else{
        document.getElementById('id-configuracion').style.display = 'none';
        document.getElementById('id-configuracion').innerHTML = '';
        mostrarInfoPagaduria(IdPagaduria);
    }
    rubrosPagadurias(IdPagaduria);
}
/**Mostrar rubros para cada pagaduria en select para poder agregarlos a la operacion */
const rubrosPagadurias = async function(id){
    let dataToSend = new FormData();
    dataToSend.append('IdPagaduria',id);
    let rubros = await makeOptionsFetch(`${globalUrl}/mostrar-rubros-pagaduria`,dataToSend,'post',$('meta[name="csrf-token-form-pagaduria"]').attr('content'));
    document.getElementById('datosVariables').innerHTML = rubros;
}
/**Crear boton del rubro para agregarlo a la operacion */
const botonRubro = function(valor){
    document.getElementById('botonRubro').innerHTML = '';
    let boton = document.createElement('button');
    let separar = valor.split(' ');
    let valorId = '';
    separar.forEach((item,index)=>{
        if(index > 0){
            let palabra = item.split('');
            let toMayus = '';
            palabra.forEach((item2,index2)=>{
                if(index2 == 0){
                    toMayus += item2.toUpperCase();
                }else{
                    toMayus += item2;
                }
            });
            valorId += toMayus;
        }else{
            valorId += item;
        }
    });
    boton.setAttribute('id','symbol_'+valorId);
    boton.className = 'btn btn-sm btn-value';
    boton.innerHTML = valorId;
    boton.setAttribute('draggable',true); boton.setAttribute('ondragstart','insertarElementoOperacion(this)');
    document.getElementById('botonRubro').appendChild(boton);
}
/**Guardar nuevo Rubro de la pagaduria seleccionada */
const guardarRubro = async function(e){
    e.preventDefault();
    let form = document.getElementById('form-rubro-config');
    let IdPagaduria = document.getElementById('lista-pagadurias').value;
    let dataToSend = new FormData(form);
    if(IdPagaduria == ''){
        Swal.fire('Oops!','No se ha seleccionado una pagaduria','warning');
    }else{
        dataToSend.append('IdPagaduria',IdPagaduria);
        let res = await makeOptionsFetch(`${globalUrl}/guardar-rubro-configuracion`,dataToSend,'post',$('meta[name="csrf-token-form-pagaduria"]').attr('content'));
        if(res == 'ok'){
            Swal.fire('Correcto!','Rubro añadido','success');
            document.getElementById('nombreRubro').value = '';
            document.getElementById('error-nombreRubro').style.display = 'none';
            rubrosPagadurias(IdPagaduria);
        }else if(res.errors){
            showErrors(res);
        }
    }
}
/**Evento del boton de eliminacion */
const eventDragDrop = function(element){
    let elemento;
    elemento = element;
    let configuracion = document.getElementById('form-configuracion');
    let botonEliminar = document.getElementById('eliminarElemento');
    botonEliminar.addEventListener('dragenter',function(){
        botonEliminar.style = 'transform:scale(1.3);';
    });
    botonEliminar.addEventListener('dragleave',function(){
        botonEliminar.style = 'transform:initial;';
    });
    botonEliminar.addEventListener('dragover',function(e){
        e.preventDefault();
    });
    botonEliminar.addEventListener('drop',function(){ /**Al soltar el elemento en el boton de eliminar */
        if(document.querySelector('#form-configuracion > div').removeChild(elemento)){
            botonEliminar.style = 'transform:initial;';
            let vistaSeparada = vistaConfiguracion.split('|'); //recorrido de string de operacion y elementos button que lo representan
            for(let i = 0; i < vistaSeparada.length; i++){
                if(elemento.getAttribute('id') === vistaSeparada[i]){
                    vistaSeparada.splice(i,1);
                    let pElement = document.getElementById('p-operacion');
                    configuracion.removeChild(pElement);
                }
            }
            html = ``;
            vistaConfiguracion = ''; //variable global para mantener el string de la operacion
            vistaSeparada.forEach((item,index)=>{
                let valor = item.split('_');
                vistaConfiguracion += item;
                if(index < vistaSeparada.length-1){
                    html += valor[0];
                    vistaConfiguracion += `|`;
                }
            });
            let p = document.createElement('p'); //creacion de p para mostrar la vista previa de la operacion
            p.className = 'lead'; p.setAttribute('id','p-operacion');
            p.innerHTML = `Operación completa: ${html}`;
            configuracion.appendChild(p);
            if(document.querySelector('#btn-guardar-configuracion') !== null){ //elimino el boton de guardar si ya está presente en el DOM
                document.querySelector('#form-configuracion').removeChild(document.querySelector('#btn-guardar-configuracion'));
            }
            let button = document.createElement('button'); //creacion del boton de guardar
            button.setAttribute('id','btn-guardar-configuracion');
            button.className = 'btn btn-primary'; button.innerHTML = 'Guardar';
            button.setAttribute('onclick','guardarConfiguracion()');
            document.querySelector('#form-configuracion').appendChild(button);
        }
    });
}
/**Preparar elementos nuevos para agregarlos a la operacion, se abre el modal para seleccion de posicion del nuevo elemento */
const insertarElementoOperacion = function(element){
    let configuracion = document.getElementById('form-configuracion');
    let elementos = document.getElementsByClassName('input-config-drag');
    for(let i=0; i<elementos.length; i++){
        elementos[i].addEventListener('dragenter',function(){
            elementos[i].style = 'transform:scale(1.2);';
        });
        elementos[i].addEventListener('dragleave',function(){
            elementos[i].style = 'transform:initial;';
        });
        elementos[i].addEventListener('dragover',function(e){
            e.preventDefault();
        });
        elementos[i].addEventListener('drop',function(){
            idNuevoElemento = element.getAttribute("id");
            idAntiguoElemento = elementos[i].getAttribute("id");
            idElementoSiguiente = "ultimo";
            if(elementos.length != i+1){
                idElementoSiguiente = elementos[i+1].getAttribute('id');   
            }
            $('#modalEdicion').modal('show');
            let html = `<p class="lead">¿Donde irá el nuevo elemento?</p>
                        <div class="row">
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 alert alert-danger text-center" onclick="nuevoElemento('${idNuevoElemento}','${idAntiguoElemento}','${i}')">
                                <p class="lead">Antes de</p>
                                <img src="images/before-button.jpg" class="img-fluid">
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 alert alert-primary text-center" onclick="nuevoElemento('${idNuevoElemento}','${idElementoSiguiente}','${i+1}')">
                                <p class="lead">Despúes de</p>
                                <img src="images/after-button.jpg" class="img-fluid">
                            </div>
                        </div>`;
            document.querySelector('#vistaPreviaEdicion').innerHTML = html; //se imprimen los botones para elegir la posicion del nuevo elemento a la operacion
        });
    }
    document.querySelector('#btnCancelarEdicion').addEventListener('click',function(){
        $('#modalEdicion').modal('hide');
        for(let i=0; i<elementos.length; i++){
            elementos[i].style = 'transform:initial;';
        }
    });
}
/**Agregar nuevo elemento */
const nuevoElemento = function(idNuevo,idAntiguo,indiceAntiguo){
    let elementosOp = document.getElementsByClassName('input-config-drag');
    for(let i=0; i<elementosOp.length; i++){
        elementosOp[i].style = 'transform:initial;';
    }
    let nuevo = document.getElementById(idNuevo);
    let clon = nuevo.cloneNode(); //clonar elemento nuevo
    clon.className = 'btn btn-sm btn-symbol input-config-drag'; //asigno clases
    clon.setAttribute('ondragstart','eventDragDrop(this)'); //asigno atributo para drag & drop
    let valorClonado = nuevo.getAttribute('id').split('_')[1];
    clon.innerHTML = valorClonado;
    clon.setAttribute('id',clon.getAttribute('id').split('_')[1]+'_'+indiceAntiguo);
    if(idAntiguo != "ultimo"){ //si es un elemento diferente al ultimo elemento de la operacion
        let antiguo = document.getElementById(idAntiguo);
        let nuevoValor = nuevo.getAttribute('id').split('_')[1];
        let separar = vistaConfiguracion.split('|');
        separar.splice(indiceAntiguo,0,nuevoValor+'_'+indiceAntiguo);
        if(document.querySelector('#form-configuracion > div').insertBefore(clon,antiguo)){ //inserta el nuevo elemento antes del seleccionado en el modal
            separar.forEach((item,index)=>{
                if(index >= Number(indiceAntiguo)+1 && item != ''){
                    let elemento = item.split('_');
                    separar[index] = elemento[0]+'_'+(Number(elemento[1])+1);
                }
            });
            let html = '';
            vistaConfiguracion = '';
            separar.forEach((item,index)=>{
                let valor = item.split('_');
                vistaConfiguracion += item;
                if(index < separar.length-1){
                    html += valor[0];
                    vistaConfiguracion += `|`;
                }
            });
            document.querySelector('#form-configuracion').removeChild(document.getElementById('p-operacion'));
            let p = document.createElement('p');
            p.className = 'lead'; p.setAttribute('id','p-operacion');
            p.innerHTML = `Operación completa: ${html}`;
            document.querySelector('#form-configuracion').appendChild(p);
            if(document.querySelector('#btn-guardar-configuracion') !== null){ //elimino el boton de guardar si ya está presente en el DOM
                document.querySelector('#form-configuracion').removeChild(document.querySelector('#btn-guardar-configuracion'));
            }
            let button = document.createElement('button');
            button.setAttribute('id','btn-guardar-configuracion');
            button.className = 'btn btn-primary'; button.innerHTML = 'Guardar';
            button.setAttribute('onclick','guardarConfiguracion()');
            document.querySelector('#form-configuracion').appendChild(button);
        }
    }else{
        let antiguo = document.getElementById(idAntiguo);
        let nuevoValor = nuevo.getAttribute('id').split('_')[1];
        let separar = vistaConfiguracion.split('|');
        separar.splice(indiceAntiguo,0,nuevoValor+'_'+indiceAntiguo);
        if(document.querySelector('#form-configuracion > div').appendChild(clon)){
            separar.forEach((item,index)=>{
                if(index >= Number(indiceAntiguo)+1 && item != ''){
                    let elemento = item.split('_');
                    separar[index] = elemento[0]+'_'+(Number(elemento[1])+1);
                }
            });
            let html = '';
            vistaConfiguracion = '';
            separar.forEach((item,index)=>{
                let valor = item.split('_');
                vistaConfiguracion += item;
                if(index < separar.length-1){
                    html += valor[0];
                    vistaConfiguracion += `|`;
                }
            });
            document.querySelector('#form-configuracion').removeChild(document.getElementById('p-operacion'));
            let p = document.createElement('p');
            p.className = 'lead'; p.setAttribute('id','p-operacion');
            p.innerHTML = `Operación completa: ${html}`;
            document.querySelector('#form-configuracion').appendChild(p);
            if(document.querySelector('#btn-guardar-configuracion') !== null){ //elimino el boton de guardar si ya está presente en el DOM
                document.querySelector('#form-configuracion').removeChild(document.querySelector('#btn-guardar-configuracion'));
            }
            let button = document.createElement('button');
            button.setAttribute('id','btn-guardar-configuracion');
            button.className = 'btn btn-primary'; button.innerHTML = 'Guardar';
            button.setAttribute('onclick','guardarConfiguracion()');
            document.querySelector('#form-configuracion').appendChild(button);
        }
    }
    document.querySelector('#vistaPreviaEdicion').innerHTML = '';
    $('#modalEdicion').modal('hide');
}
const guardarConfiguracion = async function(){
    let separar = vistaConfiguracion.split('|');
    nuevaConfig = '';
    for(let i = 0; i < separar.length-1; i++){
        nuevaConfig += separar[i].split('_')[0];
        if(i < Number(separar.length) - 2){
            nuevaConfig += '|';
        }
    }
    let dataToSend = new FormData();
    dataToSend.append('configuracion',nuevaConfig); dataToSend.append('idPagaduria',document.querySelector('#lista-pagadurias').value);
    dataToSend.append('action','config');
    dataToSend.append('idConfiguracion',document.getElementById('id-config-pagaduria').value);
    let res = await makeOptionsFetch(`${globalUrl}/guardar-pagaduria-info`,dataToSend,'post',$('meta[name="csrf-token-form-pagaduria"]').attr('content'));
    if(res.res){
        Swal.fire('Correcto!',res.res,'success')
        .then((value)=>{
            mostrarPagadurias();
            document.getElementById('form-configuracion').innerHTML = '';
            vistaConfiguracion = '';
        });
    }
}
/**Cerrar modal */
const closeModal = function(){

}