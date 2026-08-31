window.onload = function(){
    $(function(){
        $('#fechaEdad').datetimepicker({
            format:'Y/m/d',
            timepicker:false,
            datepicker:true
        });
    });
}
const validarPeriodo = async function(element){
    //validarfecha
    let edad = element.value;
    let dataToSend = new FormData(); 
    dataToSend.append('edadFecha', edad);
    let res = await makeOptionsFetch(`${globalUrl}/validar-meses`,dataToSend,'post',$('meta[name="csrf-token-simulador"]').attr('content'));
    if(res.estado == 'menor'){
        Swal.fire({
            title:'Oops!',
            text:'No parece que el cliente sea mayor de edad',
            icon:'error',
            confirmButtonText:'Entendido',
            allowOutsideClick:false,
            allowEscapeKey:false
        }).then((value)=>{
            if(value.isConfirmed){
                element.value = '';
                element.focus();
            }
        })
    }else{
        document.getElementById('periodoCredito').innerHTML = res.html;
    }
}

const calcular = async function(){
    let errores = document.getElementsByClassName('invalid-feedback');
    for($i = 0; $i < errores.length; $i++){ /**Ocultar span de errores */
        errores[$i].style = 'display:none';
    }
    periodoCredito = document.getElementById('periodoCredito').value;
    fechaEdad = document.getElementById('fechaEdad').value;
    valorCredito = document.getElementById('valorCredito').value;
    tasaInteres = document.getElementById('tasaInteres').value;
    tasaInteres = tasaInteres.replace(/,/g, '.');
    let dataToSend = new FormData();
    dataToSend.append('periodoCredito', periodoCredito); dataToSend.append('fechaEdad', fechaEdad); dataToSend.append('valorCredito', valorCredito);
    dataToSend.append('tasaInteres', tasaInteres);
    let res = await makeOptionsFetch(`${globalUrl}/simulacion-credito`,dataToSend,'post',$('meta[name="csrf-token-simulador"]').attr('content'));
    if(res.errors){
        showErrors(res);
    }else if(res.error){
        Swal.fire({title:'Oops!',text:res.error,icon:'error',allowOutsideClick:false,allowEscapeKey:false});
    }else{
        document.getElementById('tbodySimulacion').innerHTML = res.html;
        dataToSend.append('info', true);
        let info = await makeOptionsFetch(`${globalUrl}/simulacion-credito`,dataToSend,'post',$('meta[name="csrf-token-simulador"]').attr('content'));
        document.getElementById('tablaInformacion').innerHTML = info.html;
    }
}

function formatNumber(n) {
    // format number 1000000 to 1,234,567
    return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ",")
}

function formatCurrency(input, blur) {
    // appends $ to value, validates decimal side
    // and puts cursor back in right position.

    // get input value
    let input_val = input.value;

    // don't validate empty input
    if (input_val === "") { return; }

    // original length
    var original_len = input_val.length;

    // initial caret position 
    var caret_pos = input.selectionStart;
        
    // check for decimal
    if(input_val.indexOf(".") >= 0){

        // get position of first decimal
        // this prevents multiple decimals from
        // being entered
        var decimal_pos = input_val.indexOf(".");

        // split number by decimal point
        var left_side = input_val.substring(0, decimal_pos);
        var right_side = input_val.substring(decimal_pos);

        // add commas to left side of number
        left_side = formatNumber(left_side);

        // validate right side
        right_side = formatNumber(right_side);
        
        // On blur make sure 2 numbers after decimal
        if (blur === "blur") {
        right_side += "00";
        }
        
        // Limit decimal to only 2 digits
        right_side = right_side.substring(0, 2);

        // join number by .
        input_val = "$ " + left_side + "." + right_side;
    }else{
        // no decimal entered
        // add commas to number
        // remove all non-digits
        input_val = formatNumber(input_val);
        input_val = "$ " + input_val;
        
        // final formatting
        if (blur === "blur") {
        //   input_val += ".00";
        }
    }
    // send updated string to input
    input.value = input_val;

    // put caret back in the right position
    var updated_len = input_val.length;
    caret_pos = updated_len - original_len + caret_pos;
    input.setSelectionRange(caret_pos, caret_pos);
}

// }