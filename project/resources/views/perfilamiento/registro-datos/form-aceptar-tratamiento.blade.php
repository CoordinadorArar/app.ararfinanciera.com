<link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

<!-- Styles -->
<link href="{{ asset('css/app.css') }}" rel="stylesheet">
<link href="{{ asset('css/font-awesome/all.min.css')}}" rel="stylesheet">
<link href="{{ asset('css/navbar-sidebar.css') }}" rel="stylesheet">
{{-- <link href="{{ asset('css/bootstrap-datetimepicker.min.css') }}" rel="stylesheet"> --}}
<link href="{{ asset('css/jquery.datetimepicker.min.css') }}" rel="stylesheet">
<link href="{{ asset('css/preloader.css') }}" rel="stylesheet">
<style>
    body{
        background: rgb(65,110,195);
    }
    #btnGuardar:hover{
        transform: scale(1.1);
    }
</style>
<body>
    <div class="text-center my-2">
        <h4 class="text-white">Aceptar Tratamiento de Datos Personales</h4><br>
        <div class="card w-50 ms-auto me-auto">
            <div class="card-body">
                <p class="lead">
                    Yo <u>{{ $proceso['proceso'][0]->NombresTercero.' '.$proceso['proceso'][0]->ApellidosTercero }}</u> identificado(a) con 
                    <u>{{ $proceso['proceso'][0]->NombreTipoDocumento }}</u> número <u>{{ $proceso['proceso'][0]->DocumentoTercero }}</u> expedida en
                    <u>{{ $proceso['proceso'][0]->LugarExpedicionDocumento }}</u> autiorizo que
                </p>
                <p class="lead" style="text-align:justify;">
                    ARAR FINANCIERA S.A.S identificado con NIT. 900.644.447-7 será el responsable del tratamiento de mis 
                    datos personales, y en tal virtud, podrá recolectar, almacenar, usar mis datos personales según las finalidades de la autorización 
                    <strong><a target="_blanck" href="http://www.ararfinanciera.com/politica.html">ver aquí</a></strong>. y que reconozco me han sido puestas de presente antes de entregar 
                    mis datos, suscribiendo las siguientes autorizaciones de forma libre y voluntaria una vez leídas en su totalidad.
                </p><hr>
                <form action="" id="" method="post">
                    <div class="form-group d-flex align-items-center">
                        <meta name="csrf-token-form-approve-data" content="{{ csrf_token() }}" />
                        <input type="hidden" id="idProceso" name="idProceso" value="{{ $proceso['proceso'][0]->IdProceso }}">
                        <input type="checkbox" class="me-2" id="checkData" checked>
                        <p class="text-start m-0">
                            El tratamiento de mis datos personales de conformidad con la Política de tratamiento de datos personales de Arar Financiera ver más...
                        </p>
                    </div>
                    <hr>
                    <div class="form-group d-flex align-items-center">
                        <input type="checkbox" class="me-2" id="checkTelefonoContact" checked>
                        <p class="text-start m-0">
                            Contactarme a través de mi número de celular vía llamadas, SMS, Chat, WhatsApp para el envío de publicidad, 
                            realizar invitaciones a eventos y ofrecer nuevos productos y/o servicios.
                        </p>
                    </div>
                    <hr>
                    <div class="form-group d-flex align-items-center">
                        <input type="checkbox" class="me-2" id="checkEmailContact" checked>
                        <p class="text-start m-0">
                            Contactarme a través de correo electrónico para el envío de publicidad, realizar invitaciones a eventos y ofrecer nuevos productos y/o servicios.
                        </p>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-primary" id="btnGuardar" onclick="enviar(event)">Guardar</button>
                </form>
            </div>
        </div>
    </div>
</body>
<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/font-awesome/all.min.js') }}"></script>
<script src="{{ asset('js/font-awesome/brands.min.js') }}"></script>
<script src="{{ asset('js/font-awesome/regular.min.js') }}"></script>
<script src="{{ asset('js/font-awesome/solid.min.js') }}"></script>
<!--<script src="{{ asset('js/main.js') }}"></script>-->
<!--<script src="{{ asset('js/funciones-globales.js') }}"></script>-->
<script src="{{ asset('js/sweetalert2@11.js') }}"></script>
<script src="{{ asset('js/jquery.preloader.min.js') }}"></script>
<script>
    const enviar = async function(e){
        e.preventDefault();
        let checkData = document.getElementById('checkData');
        let checkTelefonoContact = document.getElementById('checkTelefonoContact');
        let checkEmailContact = document.getElementById('checkEmailContact');
        if(!$(checkData).prop('checked')){
            Swal.fire({title:'Oops!',text:'Es necesario que se acepte el tratamiento de tus datos personales',icon:'warning',confirmButtonText:'Entendido'})
        }else if(!$(checkTelefonoContact).prop('checked') || !$(checkEmailContact).prop('checked')){
            Swal.fire({
                title:'Espera!',
                text:'Es posible que información importante sea compartida por estos medios. ¿Seguro que no aceptarás?',
                icon:'info',
                confirmButtonText:'Si, continuar',
                showCancelButton:true,
                cancelButtonText:'No, volver para seleccionar',
                allowOutsideClick:false,
                allowEscapeKey:false
            }).then(async(value)=>{
                if(value.isConfirmed){
                    Swal.fire({title:'Espera',text:'Generando documento',icon:'info',showConfirmButton:false,allowEscapeKey:false,allowOutsideClick:false,timer:2500})
                    .then(async()=>{
                        let idProceso = document.getElementById('idProceso').value;
                        window.open('http://app.ararfinanciera.com/generar-formato-autorizacion/'+idProceso);
                    });
                }
            });
        }else{
            Swal.fire({title:'Espera',text:'Generando documento',icon:'info',showConfirmButton:false,allowEscapeKey:false,allowOutsideClick:false,timer:2500})
            .then(async()=>{
                let idProceso = document.getElementById('idProceso').value;
                let permisos = {
                    checkData:($('#checkData').prop('checked'))? '1' : '0',
                    checkTelefonoContact:($('#checkTelefonoContact').prop('checked'))? '1' : '0',
                    checkEmailContact:($('#checkEmailContact').prop('checked'))? '1' : '0'
                };
                console.log(permisos);
                window.open('http://app.ararfinanciera.com/generar-formato-autorizacion/'+idProceso+'/'+JSON.stringify(permisos));
            });
        }
    }
</script>