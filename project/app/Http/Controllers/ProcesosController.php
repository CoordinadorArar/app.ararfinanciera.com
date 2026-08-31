<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Procesos;
use App\Models\Terceros;
use App\Models\Petitions;
use App\Models\Admin;
use App\Models\User;
use DateTime;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
// use Symfony\Component\Process\Process;
// use Symfony\Component\HttpFoundation\BinaryFileResponse;
// use Illuminate\Http\Response;
// use Illuminate\Support\Facades\Mail;

use App\Http\Controllers\PHPMailerController;
use Barryvdh\DomPDF\Facade\Pdf;
use SoapClient;
use NewSoap;
use SoapFault;
// use SoapVar;
use stdClass;
// use SoapHeader;

class ProcesosController extends Controller
{
    /**Comprobar si el tercero ya ha iniciado un proceso, si es asi, mostrar los datos */
    public function validarDocumento(Request $request){
        $tercero = Terceros::mostrarInfoTercero('',$request->input('documento'));
        if(count($tercero)>0){
            $proceso = Procesos::mostrarTerceroProceso($tercero[0]->IdTercero);
            if(count($proceso)>0){
                if($proceso[0]->EstadoProceso == 1){
                    return response()->json(['Tercero'=>$tercero,'Proceso'=>$proceso, 'Estado'=>1]);
                }elseif($proceso[0]->EstadoProceso == 2){
                    if($proceso[0]->DocumentosCargados == null){
                        return response()->json(['DocumentosCargados' => 0, 'Tercero' => $tercero, 'Proceso' => $proceso]);
                    }else{
                        return response()->json(['Estado'=>$proceso[0]->EstadoProceso]);
                    }
                }else{
                    return response()->json();
                }
            }else{
                return response()->json(['Tercero'=>$tercero]);
            }
        }else{
            return response()->json('no existe');
        }
    }
    /**------------------------------------------------------------- */
    /**Reglas para validación de creación de tercero */
    public function reglas(){
        return [
            'nombres' => 'required|string|max:50',
            'apellidos' => 'required|string|max:50',
            'tipoDocumento' => 'required',
            'documentoTercero' => 'required|numeric',
            'fechaExpedicion' => 'required|date',
            'lugarExpedicion' => 'required|string',
            'fechaNacimiento' => 'required|date',
            'telefonoTercero' => 'required',
            'emailTercero' => 'required|email',
            'direccionTercero' => 'required|string',
            'departamentoResidencia' => 'required',
            'ciudadResidencia' => 'required'
        ];
    }
    /**------------------------------------------------------------- */
    /**Guardar datos personales del usuario */
    public function guardarDatosPersonales(Request $request){
        $validate = Validator::make($request->all(), $this->reglas());
        if($validate->fails()){
            return response()->json(['errors'=>$validate->errors()]);
        }else{
            if($request->input('idTercero') != ''){ //actualizar
                $tercero = Terceros::where('IdTercero',$request->input('idTercero'))->update([
                    'NombresTercero' => $request->input('nombres'),
                    'ApellidosTercero' => $request->input('apellidos'),
                    'IdTipoDocumento' => $request->input('tipoDocumento'),
                    'DocumentoTercero' => $request->input('documentoTercero'),
                    'FechaExpedicionDocumento' => $request->input('fechaExpedicion'),
                    'LugarExpedicionDocumento' => $request->input('lugarExpedicion'),
                    'FechaNacimientoTercero' => $request->input('fechaNacimiento'),
                    'TelefonoTercero' => $request->input('telefonoTercero'),
                    'EmailTercero' => $request->input('emailTercero'),
                    'DireccionDomicilioTercero' => $request->input('direccionTercero'),
                    'DepartamentoDomicilioTercero' => $request->input('departamentoResidencia'),
                    'CiudadDomicilioTercero' => $request->input('ciudadResidencia')
                ]);
                if($tercero){
                    return response()->json(['success'=>'Datos de tercero agregados correctamente','data'=>['id'=>$request->input('idTercero')]]);
                }
            }else{ //guardar primera vez
                $tercero = Terceros::create([
                    'NombresTercero' => $request->input('nombres'),
                    'ApellidosTercero' => $request->input('apellidos'),
                    'IdTipoDocumento' => $request->input('tipoDocumento'),
                    'DocumentoTercero' => $request->input('documentoTercero'),
                    'FechaExpedicionDocumento' => $request->input('fechaExpedicion'),
                    'LugarExpedicionDocumento' => $request->input('lugarExpedicion'),
                    'FechaNacimientoTercero' => $request->input('fechaNacimiento'),
                    'TelefonoTercero' => $request->input('telefonoTercero'),
                    'EmailTercero' => $request->input('emailTercero'),
                    'DireccionDomicilioTercero' => $request->input('direccionTercero'),
                    'DepartamentoDomicilioTercero' => $request->input('departamentoResidencia'),
                    'CiudadDomicilioTercero' => $request->input('ciudadResidencia')
                ]);
                if($tercero){
                    return response()->json(['success'=>'Datos de tercero agregados correctamente','data'=>$tercero]);
                }
            }
        }
    }
    /**------------------------------------------------------------- */
    /**Mostrar configuracion de la pagaduria para hallar el cupo disponible */
    public function mostrarConfigInputs(Request $request){
        if(in_array($request->input('pagaduriaTercero'),[6,8])){
            $salario = Petitions::mostrarSalarioMinimo();
            $salarioMinimo = $salario[0]->ValorVariable;
            if($request->input('ingresosMensuales') > ($salarioMinimo*2)){
                $dataPagaduria = Admin::mostrarInfoPagaduria($request->input('pagaduriaTercero'),'%');
            }else{
                $dataPagaduria = Admin::mostrarInfoPagaduria($request->input('pagaduriaTercero'),'$');
            }
        }else{
            $dataPagaduria = Admin::mostrarInfoPagaduria($request->input('pagaduriaTercero'));
        }
        return response()->json($dataPagaduria);
    }
    /**------------------------------------------------------------- */
    /**Guardar datos financieros del usuario (ingresos,egresos) */
    public function guardarDatosFinancieros(Request $request){
        /**Recibida de arrays de datos y nombres de los datos */
        $nombres = $request->nombres; $valores = $request->valores;
        $array = [];
        /**Asignacion de los valores a sus respectivos nombres */
        foreach($nombres as $index1 => $item1){
            foreach($valores as $index2 => $item2){
                if($index1 == $index2){
                    $array[$item1] = $item2;
                }
            }
        }
        /**Datos necesarios de la BD*/
        $salario = Petitions::mostrarSalarioMinimo();
        $salarioMinimo = $salario[0]->ValorVariable;
        if(in_array($request->input('idPagaduria'),[6,8])){
            if(intval($request->ingresos) > ($salarioMinimo*2)){
                $dataPagaduria = Admin::mostrarInfoPagaduria($request->input('idPagaduria'),'%');
            }else{
                $dataPagaduria = Admin::mostrarInfoPagaduria($request->input('idPagaduria'),'$');
            }
        }else{
            $dataPagaduria = Admin::mostrarInfoPagaduria($request->input('idPagaduria'));
        }
        /**Extracción de string de la configuración de la pagaduria para calcular el cupo disponible */
        $config = $dataPagaduria[0]->Configuracion;
        $configExplode = explode('|',$config);
        /**Recorrido de valores traidos de la vista y los de la configuracion */
        foreach($configExplode as $data){
            foreach($array as $nombre => $valor){
                /**Se reemplaza el nombre de la variable por el valor ingresado en los inputs */
                if($data == $nombre){
                    $config = str_replace($data,$valor,$config);
                }elseif($data == 'salarioMinimoMensual'){
                    $config = str_replace($data,$salarioMinimo,$config);
                }
            }
        }
        $config = explode('|',$config); //separo de nuevo el string de la configuracion
        $operacion = '';
        foreach($config as $data){
            $operacion .= $data; //uno el string sin los signos |
        }
        $resultado = eval("return \$cupo = $operacion;"); //evaluo el string con eval() para que se ejecute la operacion dentro del string
        $cupoDisponible = $cupo;
        /**Actualizo los datos del tercero, se agrega la pagaduria, el valor del credito a solicitar y el valor de los ingresos mensuales que recibe */
        $actualizar = Terceros::where('IdTercero',$request->input('idTercero'))->update([
            'IngresosTercero' => $request->input('ingresos'),
            'IdPagaduria' => $request->input('idPagaduria')
        ]);
        $infoTercero = Terceros::mostrarInfoTercero($request->input('idTercero'));
        $numeroCuotas = $request->input('numeroCuotas'); $valorCredito = $request->input('valorSolicitado');
        $tasaInteres = Petitions::mostrarValorVariable('TasaInteres');
        $calculo = $this->calcularValorCuotas($numeroCuotas,'info',$tasaInteres[0]->ValorVariable,$valorCredito,$infoTercero[0]->FechaTerceroNacimiento);
        if($actualizar){
            /**Creación del proceso con estado 1 = Proceso iniciado */
            $hoy = date('Y-m-d H:i:s');
            $fecha = explode(' ',$hoy)[0];
            $hora = explode(' ',$hoy)[1];
            if($request->input('idProceso') != ''){
                $proceso = Procesos::where('IdProceso',$request->input('idProceso'))->update([
                    'IdTercero' => $request->input('idTercero'),
                    'FechaCreacion' => $fecha.'T'.$hora,
                    'IdUsuario' => auth()->id(),
                    'EstadoProceso' => 1,
                    'ValorCreditoSolicitado' => $valorCredito,
                    'NumeroCuotas' => $numeroCuotas,
                    'ValorCuota' => $calculo['datos']['cuota'],
                    'TasaInteres' => $tasaInteres[0]->ValorVariable,
                    'updated_at' => $fecha.'T'.$hora
                ]);
            }else{
                $proceso = Procesos::create([
                    'IdTercero' => $request->input('idTercero'),
                    'FechaCreacion' => $fecha.'T'.$hora,
                    'IdUsuario' => auth()->id(),
                    'EstadoProceso' => 1,
                    'ValorCreditoSolicitado' => $valorCredito,
                    'NumeroCuotas' => $numeroCuotas,
                    'ValorCuota' => $calculo['datos']['cuota'],
                    'TasaInteres' => $tasaInteres[0]->ValorVariable
                ]);
            }
            if($proceso){
                $consultarProceso = Procesos::mostrarProcesoTercero($request->idTercero,auth()->id(),1);
                $idProceso = $consultarProceso[0]->IdProceso;
                /**Guardar resultados del calculo de cupo*/
                if($request->input('idProceso') != ''){
                    $guardarResultados = Procesos::guardarCupoDisponible($idProceso,$dataPagaduria[0]->Configuracion,$operacion,$cupoDisponible,'actualizar');
                }else{
                    $guardarResultados = Procesos::guardarCupoDisponible($idProceso,$dataPagaduria[0]->Configuracion,$operacion,$cupoDisponible,'guardar');
                }
                return response()->json([
                    'res'=>'Datos ingresados con exito!',
                    'idProceso' => $idProceso,
                    'cupo'=>$cupoDisponible
                ]);
            }
        }
    }
    /**------------------------------------------------------------- */
    /**Verificar edad del tercero, para no permitir periodos tan extensos para adultos muy mayores */
    public function verificarEdadTercero(Request $request){
        $idTercero = $request->get('idTercero');
        $tercero = Terceros::mostrarInfoTercero($idTercero);
        $fechaNacimiento = $tercero[0]->FechaTerceroNacimiento;
        $date = date('Y-m-d');
        $hoy = new DateTime($date);
        $fechaEdad = new DateTime($fechaNacimiento);
        $diferencia = $hoy->diff($fechaEdad);
        return response()->json($diferencia);
    }
    /**------------------------------------------------------------- */
    /**cambiar estado del proceso */
    public function editarEstadoProceso(Request $request){
        if($request->input('estado') == 'pendiente'){
            $procesoResultado = Procesos::mostrarResultadoConfiguracion($request->input('idProceso'));
            if($procesoResultado[0]->CupoDisponible > 0){
                if($procesoResultado[0]->EstadoProceso == 1){
                    $estado = 2; $res = 'edited';
                    $editarEstado = Procesos::editarEstadoProceso($request->input('idProceso'),$estado);
                    return response()->json(['res'=>$res]);
                }
            }else{
                $estado = 0; $res = 'canceled';
                $editarEstado = Procesos::editarEstadoProceso($request->input('idProceso'),$estado);
                return response()->json(['res'=>$res]);
            }
        }else{
            $editarEstado = Procesos::editarEstadoProceso($request->input('idProceso'),$request->input('estado'));
            return response()->json(['res'=>'edited']);
        }
    }
    /**------------------------------------------------------------- */
    //verificar datos en centrales de riesgo
    public function consultaCentralesRiesgo(Request $request){
        require_once('NewSoap.php'); //requerimos la clase NewSoap que extiende de soapclient y utiliza la libreria de robrichards
        ini_set("soap.wsdl_cache_enabled", 0);
        $procesos = Procesos::mostarInfoProceso($request->input('idProceso')); // traer datos del cliente para consultar
        $documento = $procesos['data']->DocumentoTercero;
        $apellido = explode(' ',$procesos['data']->ApellidosTercero)[0];
        $url = 'https://miportafoliouat.transunion.co/InformacionComercialWS/services/InformacionComercialSeg?wsdl'; //url de consulta
        $username = '405736';
        $password = 'ArarF2023T@U';
        $options = array( //parametros para que la conexión funcione
            'login' => $username,
            'password' => $password,
            'cache_wsdl' => 0,
            'trace' => 1,
            'exceptions' => 0,
            'stream_context' => stream_context_create(array(
                'ssl' => array(
                    'verify_peer' => 0,
                    'verify_peer_name' => false,
                    'allow_self_signed' => false
               )
            ))
        );
        $client = new NewSoap($url,$options); //instancia a NewSoap
        try{
            $parametros = [ //parametros para la consulta
                'codigoInformacion' => '5702',
                'motivoConsulta' => '1',
                'numeroIdentificacion' => '91498793',
                'primerApellido' => 'VELANDIA',
                'tipoIdentificacion' => '1',
            ];
            //$client->addUserToken($username,$password);
            $response = $client->consultaXml($parametros);
            $xml = simplexml_load_string($response);
            return response()->json(['res' => 'ok','xml' => $xml]);
        }catch(SoapFault $e){
            return 'Error: '.$e;
        }
    }
    /**------------------------------------------------------------- */
    //subir documento pdf de tratamiento de datos
    public function subirArchivoTratamientoDatos(Request $request){
        $rule = [
            'fileTratamientoDatos' => [
                'required',
                'file',
                'mimes:pdf',                    // Solo archivos PDF
                'max:2048',                     // Máximo 2MB (2048 KB)
                'mimetypes:application/pdf'     // Validación adicional de MIME type
            ]
        ];

        // Mensajes personalizados para las validaciones
        $messages = [
            'fileTratamientoDatos.required' => 'Debe seleccionar un archivo.',
            'fileTratamientoDatos.file' => 'El archivo seleccionado no es válido.',
            'fileTratamientoDatos.mimes' => 'Solo se permiten archivos en formato PDF.',
            'fileTratamientoDatos.max' => 'El archivo no debe superar los 2MB.',
            'fileTratamientoDatos.mimetypes' => 'El archivo debe ser un PDF válido.'
        ];

        $validate = Validator::make($request->all(), $rule, $messages);

        if($validate->fails()){
            return response()->json(['errors'=>$validate->errors()]);
        }

        if($request->hasFile('fileTratamientoDatos')){
            $tercero = Terceros::mostrarInfoTercero($request->input('idTercero'));
            $documentoTercero = $tercero[0]->DocumentoTercero;
            $fecha = date('Y-m-d');
            $nombreArchivo = $documentoTercero.'-'.$fecha.'-autorizacion.pdf';
            $tamañoArchivo = $request->file('fileTratamientoDatos')->getSize();
            $carpetaArchivo = 'app/public/tratamiento_datos/';

            if(Storage::disk('sftp')->putFileAs($carpetaArchivo,$request->file('fileTratamientoDatos'),$nombreArchivo)){
                $guardarTratamiento = Terceros::guardarFormatoTratamientoDatos($request->input('idTercero'),$request->input('idProceso'),$nombreArchivo,$tamañoArchivo,1);
                return response()->json(['success'=>'Archivo cargado con exito!']);
            }
            
            return response()->json(['errors'=>'Surgieron errores al intentar subir el archivo']);
        }
        return response()->json(['errors'=>'No se ha seleccionado un archivo válido']);
    }
    /**------------------------------------------------------------- */
    /**Envio de correo electronico al tercero para su aceptacion de tratamiento de datos */
    public function enviarEmailAprobacionDatos(Request $request){
        $idProceso = $request->input('idProceso');
        $proceso = Procesos::mostrarInfoProcesos($idProceso,'approveCredit');
        $documentoTercero = $proceso['proceso'][0]->DocumentoTercero;
        $asunto = 'Tratamiento de Datos Arar Financiera';
        $to = $proceso['proceso'][0]->EmailTercero;
        $cuerpo = '<style>a{border-radius:5px;background-color:rgb(65,110,195);border-color:rgb(65,110,195);color:rgb(255,255,255);padding:10px;font-size:14px;}</style>';
        $cuerpo .= '<p>Buen d&iacutea,</p><br>';
        $cuerpo .= '<p>Con el siguiente bot&oacuten ser&aacutes redirigido al formulario de aceptaci&oacuten de tratamiento de tus datos personales</p><br>';
        $cuerpo .= '<a href="http://app.ararfinanciera.com/aceptar-tratamiento-datos/'.$idProceso.'/'.$documentoTercero.'">
                        Formulario de tratamiento de datos
                    </a>';
        $data = ['asunto'=>$asunto,'destinatario'=>$to,'cuerpo'=>$cuerpo,'copiaA'=>'','emailBcc'=>''];
        $enviar = PHPMailerController::crearEmail($data);
        if($enviar){
            return response()->json('ok');
        }else{
            return response()->json('fail');
        }
    }
    /**-------------------------------------------------------------- */
    /**Vista de formulario de aceptación de tratamiento de datos*/
    public function aceptarTratamientoDatos($idProceso,$documento){
        $proceso = Procesos::mostrarInfoProcesos($idProceso,'approveCredit');
        return view('perfilamiento.registro-datos.form-aceptar-tratamiento',compact('proceso'));
    }
    /**-------------------------------------------------------------- */
    /**Generar pdf aceptación de tratamiento de datos digital */
    public function generarFormatoAutorizacion(/*Request $request*/$idProceso,$permisos){
        $proceso = Procesos::mostrarInfoProcesos($idProceso,'approveCredit');
        $insertar = Terceros::insertarTratamientoDatosAceptados($proceso['proceso'][0]->IdTercero,$permisos);
        if($insertar){
            view()->share('proceso',$proceso);
            $pdf = Pdf::loadView('proccess.generar-formato-autorizacion',$proceso);
            return $pdf->download('archivo.pdf');
        }else{
            return response()->json($insertar);
        }
    }
    /**------------------------------------------------------------- */
    /**Mostrar procesos segun estado seleccionado por el filtro */
    public function listaProcesos(Request $request){
        $rol = User::obtenerRol(auth()->id());
        $procesos = Procesos::listaProcesos(auth()->id(),$request->input('filtro'),$request->input('busqueda'));
        $tabla = '<thead>
                    <tr class="bg-primary">
                        <th scope="col">Documento</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Fecha Creación</th>
                        <th scope="col">Pagaduria</th>
                        <th scope="col">Fecha Cambio de Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>';
        foreach($procesos as $data){
            $items = '';
            foreach($rol as $dataRol){
                if(in_array($dataRol->IdRol,[1,2,5]) && $data->EstadoProceso == 2){
                    $items .= '<li><a class="dropdown-item" href="#" onclick="mostrarVistas('.$data->IdProceso.',`centrales`)"><i class="fas fa-search-dollar"></i> Centrales de riesgo</a></li>';
                }
                if(in_array($dataRol->IdRol,[1,2,4]) && $data->EstadoProceso == 3){
                    $items .= '<li><a class="dropdown-item" href="#" onclick="mostrarVistas('.$data->IdProceso.',`docs soporte`)"><i class="fas fa-file-upload"></i> Documentos de soporte</a></li>';
                }
                if(in_array($dataRol->IdRol,[1,2,5]) && $data->EstadoProceso == 3){
                    $items .= '<li><a class="dropdown-item" href="#" onclick="mostrarVistas('.$data->IdProceso.',`docs aprobar`)"><i class="fas fa-tasks"></i> Aprobar documentos</a></li>';
                }
                if(in_array($dataRol->IdRol,[1,2,6]) && in_array($data->EstadoProceso,[4,5])){
                    $items .= '<li><a class="dropdown-item" href="#" onclick="mostrarVistas('.$data->IdProceso.',`credito aprobar`)"><i class="fas fa-thumbs-up"></i> Aprobar crédito</a></li>';
                }
            }
            $tabla .= '<tr>
                        <td>'.$data->DocumentoTercero.'</td>
                        <td>'.$data->NombresTercero.' '.$data->ApellidosTercero.'</td>
                        <td>'.$data->FechaCreacion.'</td>
                        <td>'.$data->NombrePagaduria.'</td>
                        <td>'.$data->updated_at.'</td>
                        <td>
                            <div class="dropdown">
                                <a class="btn btn-warning btn-sm dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <i class="fas fa-chevron-circle-down"></i>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                                    '.$items.'
                                </ul>
                            </div>
                        </td>
                    </tr>';
        }
        $tabla .= '</tbody>';
        return response()->json($tabla);
    }
    /**------------------------------------------------------------- */
    /**Validar edad del cliente para saber si es o no mayor y mostrar periodo de tiempo de credito segun respuesta */
    public function validarMeses(Request $request){
        $fecha = $request->get('edadFecha');
        $anio = date('Y'); $edad = explode('-',$request->get('edadFecha'));
        $anioSelect = $edad[0]; $html = '';

        if($anioSelect != ''){
            $anioPeriodo = (int)$anio - (int)$anioSelect;
        }
        if($anioPeriodo >= 18 && $anioPeriodo < 75 && $anioPeriodo != ' '){
            $html .= '<option value="">- Selecciona periodo de crédito -</option>';
            for($i=1; $i<=96; $i++){
                $html .= '<option value="'.$i.'">'.$i.'</option>';
            }
            $estado = 'mayor';
        }elseif($anioPeriodo >= 75 && $anioPeriodo != ' '){
            $html .= '<option value=" ">- Selecciona periodo de crédito -</option>';
            for ($i=1; $i <= 60; $i++) { 
                $html .= '<option value="'.$i.'">'.$i.'</option>';
            }
            $estado = 'mayor';
        }else{
            $html .= '<option value=" ">- Selecciona periodo de crédito -</option>';
            $estado = 'menor';
        }
        return response()->json(['html' => $html, 'estado' => $estado]);
    }
    /**------------------------------------------------------------- */
    /**Calcular credito y mostrar información en tabla de simulacion */
    public function simulacionCredito(Request $request){
        $rules = [
            'fechaEdad' => 'required',
            'periodoCredito' => 'required',
            'valorCredito' => 'required',
            'tasaInteres' => 'required',
        ];
        $validate = Validator::make($request->all(),$rules);
        if($validate->fails()){
            return response()->json(['errors'=>$validate->errors()]);
        }else{
            $periodoCredito = $request->input('periodoCredito'); $edadFecha = $request->input('edadFecha'); $valorCredito = $request->input('valorCredito'); 
            $tasaInteres = $request->input('tasaInteres');
            if($request->input('info')){
                $calculo = $this->calcularValorCuotas($periodoCredito,'info',$tasaInteres,$valorCredito,$edadFecha);
                return response()->json($calculo);
            }else{
                $calculo = $this->calcularValorCuotas($periodoCredito,'',$tasaInteres,$valorCredito,$edadFecha);
                return response()->json($calculo);
            }
        }
    }
    /**------------------------------------------------------------- */
    /**Calcular valor cuotas */
    public function calcularValorCuotas($periodoCredito,$info='',$tasaInteres,$valorCredito,$edadFecha){
        $deuda = str_replace("$","",$valorCredito);
        $deuda = str_replace(" ","",$deuda);
        $deuda = str_replace(",","",$deuda);
        if($info != ''){
            $deuda1 = str_replace("$","$&nbsp;",$valorCredito);
            $deuda1 = str_replace(" ","",$deuda1);
            $deuda1 = str_replace(",",".",$deuda1);
            $deuda = $deuda;
            $nacimiento = new DateTime($edadFecha);
            $actual = new DateTime(date("Y-m-d"));
            $diferencia = $actual->diff($nacimiento);
            $edad1 =  $diferencia->format("%y");
            $interes=($tasaInteres/100);
            $calculo=(int)($deuda*$interes*(pow((1+$interes),($periodoCredito))))/((pow((1+$interes),($periodoCredito)))-1);
            if($edad1 < 75){
                $seguros = $valorCredito * 0.0030;
            }else{
                $seguros = $valorCredito * 0.005625;
            }
            $html = '<tr>
                        <td><strong>Valor Credito : '.$deuda1.' </strong></td>
                    </tr>
                    <tr>
                        <td><strong>Valor Cuota Mensual : $ '.number_format($calculo+$seguros,2,",",".").'</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Numero Cuota : '.$periodoCredito.' </strong></td>
                    </tr>
                    <tr>
                        <td><strong>Tasa Mensual : '.$tasaInteres.' % </strong></td>
                    </tr>
                    <tr style="border-top: 0.5px solid;">
                        <td><h2 style="font-size: 18px;font-weight: 700;">Tabla Simulación de crédito</h2></td>
                    </tr>';
            return ['html' => $html, 'datos' => ['cuota'=>(int)$calculo+$seguros]];
        }else{
            $html = ''; $totalint = 0;
            if(is_numeric($deuda) && is_numeric($periodoCredito)){
                $deuda = $deuda;
                $nacimiento = new DateTime($edadFecha);
                $actual = new DateTime(date('Y-m-d'));
                $diferencia = $actual->diff($nacimiento);
                $edad1 =  $diferencia->format("%y");
                $interes = $tasaInteres/100;
                if($edad1 < 75){
                    $seguros = $valorCredito * 0.0030;
                }else{
                    $seguros = $valorCredito * 0.005625;
                }
                $calculo = (int)($deuda*$interes*(pow((1+$interes),($periodoCredito))))/((pow((1+$interes),($periodoCredito)))-1);
                $html .= '<tr>
                            <td>0</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>'.number_format($deuda,2,",",".").'</td>
                        </tr>';
                for($i=1; $i<=$periodoCredito; $i++) {
                    $totalint = $totalint+($deuda*$interes);
                    $html .= '<tr>
                                <td>'.$i.'</td>
                                <td>'.number_format($calculo,2,",",".").'</td>
                                <td>'.number_format($calculo-($deuda*$interes),2,",",".").'</td>
                                <td>'.number_format($deuda*$interes,2,",",".").'</td>
                                <td>'.number_format($seguros,2,",",".").'</td>
                                <td>'.number_format($calculo+$seguros,2,",",".").'</td>';
                    $deuda = ($deuda-($calculo-($deuda*$interes)));
                    if($deuda<0) {
                        $html .= '<td>0</td>';
                    }else {
                        $html .= '<td>'.number_format($deuda,2,",",".").'</td>';
                    }
                    $html .= '</tr>';
                }
                return ['html' => $html];
            }else{
                return ['error'=>'Parece haber un problema con los valores enviados para la simulación'];
            }
        }
    }
    /**Mostrar valores del proceso seleccionado para iniciar calculo de cupo */
    public function mostrarValoresProceso(Request $request){
        $proceso = Procesos::mostrarProceso(1,$request->input('idProceso'));
        return response()->json($proceso);
    }
    /**------------------------------------------------------------- */
    /**Mostrar datos del proceso y otras tablas segun peticion de la vista */
    public function mostrarInfoProcesos(Request $request){
        $idProceso = $request->input('idProceso'); $accion = $request->input('accion');
        $proceso = Procesos::mostrarInfoProcesos($idProceso,$accion);
        return response()->json($proceso);
    }
    /**------------------------------------------------------------- */
    /**Verificar todos los procesos que ya han cargado todos los documentos, para pasarlos al siguiente estado, espera de aprobación */
    // public function verifyDocumentsAllProccess(){
    //     $consultarProcesos = Procesos::verifyDocumentsAllProccess();

    // }
    /**------------------------------------------------------------- */
    /**Verificar los documentos subidos y comprobar si ya han sido subidos todos para cambiar el estado del proceso a estado 4=En espera de aprobacion de documentos */
    public function verificarDocumentos(Request $request){
        $proceso = Procesos::mostrarInfoProcesos($request->input('idProceso'),'uploadDocs');
        $documentosCargados = explode(',',$proceso['proceso'][0]->DocumentosCargados);
        $documentosSolicitados = $proceso['documentos'];
        $arraySolicitados = array();
        foreach($documentosSolicitados as $data){
            $id = $data->IdDocumentoSolicitado;
            array_push($arraySolicitados,$id);
        }
        $diferencia = array_diff($arraySolicitados,$documentosCargados);
        if(count($diferencia)>0){
            return response()->json('faltan');
        }else{
            return response()->json('subidos');
        }
        $cadenaSolicitados = ''; $cadenaSubidos = '' ;
        // foreach($arraySolicitados as $data){

        // }
    }
    /**------------------------------------------------------------- */
    /**Subir documentos adjuntos de soporte de credito, creando una carpeta por usuario */
    public function subirDocumentosSoporte(Request $request){
        $nombreArchivo = $request->input('nombre');
        $idProceso = $request->input('idProceso');
        if($request->hasFile($nombreArchivo)){
            $tercero = Terceros::mostrarInfoTercero($request->idTercero);
            $documentoTercero = $tercero[0]->DocumentoTercero;
            $fecha = date('Y-m-d');
            $actualizarProceso = Procesos::actualizarDocumentosSubidos($idProceso,$request->input('idDocumento'));
            if($actualizarProceso){
                if($request->input('idDocumento') == 2){
                    $nombre = $tercero[0]->DocumentoTercero.'-'.$fecha.'-autorizacion.pdf';
                    if($request->file($nombreArchivo)->storeAs('public/tratamiento_datos',$nombre)){
                        $permisos = json_encode([
                            'checkData'=>1,
                            'checkTelefonoContact'=>1,
                            'checkEmailContact'=>1
                        ]);
                        $ruta = 'storage/tratamiento_datos/'.$nombre;
                        $insertar = Terceros::insertarTratamientoDatos($tercero[0]->IdTercero,$ruta,$permisos);
                        return response()->json(['success'=>'Subidos correctamente']);
                    }else{
                        return response()->json(['errors'=>'Surgieron errores al intentar subir el archivo','archivo'=>$request->nombre]);
                    }
                }else{
                    $carpetaArchivo = 'app/public/documentos-soporte-'.$documentoTercero.'-'.$idProceso;
                    $archivoNombre = $documentoTercero.'-'.$idProceso.'-'.$nombreArchivo.'.pdf';
                    if(Storage::exists($carpetaArchivo)){
                        if(Storage::disk('sftp')->putFileAs($carpetaArchivo,$request->file($nombreArchivo),$archivoNombre)){
                            return response()->json(['success'=>'Subidos correctamente']);
                        }else{
                            return response()->json(['errors'=>'Surgieron errores al intentar subir el archivo','archivo'=>$request->nombre]);
                        }
                    }else{
                        if(Storage::disk('sftp')->putFileAs($carpetaArchivo,$request->file($nombreArchivo),$archivoNombre)){
                            return response()->json(['success'=>'Subidos correctamente']);
                        }else{
                            return response()->json(['errors'=>'Surgieron errores al intentar subir el archivo','archivo'=>$request->nombre]);
                        }
                    }
                }
            }
        }else{
            return response()->json(['errors'=>'Sin documento']);
        }
        
    }
    /**---------------------------------------------------------------------------------- */
    /**Agregar o eliminar documento de la lista de aceptados en la tabla Procesos */
    public function gestionDocumentosSoporte(Request $request){
        $idProceso = $request->input('idProceso'); $idDocumento = $request->input('idDocumento'); $accion = $request->input('action');
        $proceso = Procesos::mostrarInfoProcesos($idProceso,'checkDocs');
        if($accion == 'verify'){
            $cargados = explode(',',$proceso['proceso'][0]->DocumentosCargados);
            $aprobados = explode(',',$proceso['proceso'][0]->DocumentosAprobados);
            $noAprobados = array_diff($cargados,$aprobados);
            if(count($noAprobados) > 0){
                $respuesta = 'faltan';
            }else{
                $respuesta = 'aprobados';
            }
            return response()->json(['cargados'=>$cargados, 'aprobados'=>$aprobados, 'noAprobados'=>$noAprobados, 'respuesta'=>$respuesta]);
        }elseif($accion == 'rechazar'){
            $actualizarProceso = Procesos::gestionDocumentosSoporte($idProceso,$idDocumento,$accion);
            $documentoTercero = $proceso['proceso'][0]->DocumentoTercero;
            $nombreArchivo = '';
            foreach($proceso['documentos'] as $data){
                if($data->IdDocumentoSolicitado == $idDocumento){
                    $documentoArray = explode(' ',$data->NombreDocumento);
                    $nombre = '';
                    foreach($documentoArray as $key => $dataDocumento){
                        if($key > 0){
                            $palabra = str_split($dataDocumento);
                            $toMayus = '';
                            foreach($palabra as $key2 => $dataPalabra){
                                if($key2 == 0){
                                    $toMayus .= strtoupper($dataPalabra);
                                }else{
                                    $toMayus .= $dataPalabra;
                                }
                            }
                            $nombre .= $toMayus;
                        }else{
                            $nombre .= $dataDocumento;
                        }
                    }
                    $nombreArchivo = $nombre;
                }
            }
            if($idDocumento == 2){
                $nombreArchivo = explode('/',$proceso['tratamiento'][0]->RutaFormato)[4];
                $rutaCarpeta = 'app/public/tratamiento_datos/'.$nombreArchivo;
                $eliminarTratamientoDatos = Procesos::eliminarTratamientoDatos($proceso['proceso'][0]->IdTercero);
            }else{
                //$ruta = 'storage/documentos-soporte-'.$documentoTercero.'-'.$idProceso.'/'.$documentoTercero.'-'.$idProceso.'-'.$nombreArchivo.'.pdf';
                $rutaCarpeta = 'app/public/documentos-soporte-'.$documentoTercero.'-'.$idProceso;
                $ruta = '/'.$rutaCarpeta.'/'.$documentoTercero.'-'.$idProceso.'-'.$nombreArchivo.'.pdf';
            }
            if(Storage::disk('sftp')->delete($ruta)){
                return response()->json('success');
            }
        }else{
            $actualizarProceso = Procesos::gestionDocumentosSoporte($idProceso,$idDocumento,$accion);
            return response()->json('success');
        }
    }
    /**--------------------------------------------------------------------- */
    /**Ver documento de soporte */
    public function verDocumentoSoporte($nombreDocumento,$idProceso){
        $proceso = Procesos::mostrarInfoProcesos($idProceso,'checkDocs');
        $documentoTercero = $proceso['proceso'][0]->DocumentoTercero;
        if($nombreDocumento == 'AutorizacionTratamientoDatos'){
            $nombreArchivo = explode('/',$proceso['tratamiento'][0]->RutaFormato)[4];
            $rutaCarpeta = 'app/public/tratamiento_datos/'.$nombreArchivo;
            $descarga = Storage::disk('sftp')->get('/'.$rutaCarpeta);
            return response($descarga, 200, [
                'Content-Type' => 'application/pdf',
            ]);
        }else{
            $rutaCarpeta = 'app/public/documentos-soporte-'.$documentoTercero.'-'.$idProceso;
            $descarga = Storage::disk('sftp')->get('/'.$rutaCarpeta.'/'.$documentoTercero.'-'.$idProceso.'-'.$nombreDocumento.'.pdf');
            return response($descarga, 200, [
                'Content-Type' => 'application/pdf',
            ]);
        }
    }
    /**--------------------------------------------------------------------- */
    /**Editar proceso */
    public function editarProceso(Request $request){
        $periodoCredito = $request->input('periodoCredito'); $valorCredito = $request->input('valorCredito');
        $idProceso = $request->input('idProceso'); $nuevoCupo = $request->input('nuevoCupo');
        $proceso = Procesos::mostrarInfoProcesos($idProceso,'approveCredit');
        $tasaInteres = Petitions::mostrarValorVariable('TasaInteres');
        $calculo = $this->calcularValorCuotas($periodoCredito,'info',$tasaInteres[0]->ValorVariable,$valorCredito,$proceso['proceso'][0]->FechaTerceroNacimiento);
        $datos = [
            'ValorCreditoSolicitado' => $valorCredito,
            'NumeroCuotas' => $periodoCredito,
            'ValorCuota' => $calculo['datos']['cuota']
        ];
        $datosCupo = ['CupoDisponible' => $nuevoCupo];
        $actualizarProceso = Procesos::editarProceso($idProceso,$datos);
        $actualizarCupoResltado = Procesos::editarResultadoCalculo($idProceso,$datosCupo);
        if($actualizarProceso){
            return response()->json('ok');
        }else{
            return response()->json('fail');
        }
    }
    /**Envio de email al asesor al aprobar o rechazar un credito */
    public function enviarEmailCredito(Request $request){
        $asunto = $request->input('asunto');
        $proceso = Procesos::mostrarInfoProcesos($request->input('idProceso'),'approveCredit');
        $to = Admin::perfilUsuario($proceso['proceso'][0]->IdUsuario);
        $cuerpo = '';
        if($request->input('accion') == 'aprobar'){
            $cuerpo .= '<p>Buen d&iacutea,</p><br>';
            $cuerpo .= $request->input('texto');
            $cuerpo .= '<table style="border-collapse: collapse;text-align:left;">
                            <tr >
                                <th style="border:solid 0.5px #000;">Valor aprobado</th>
                                <td style="border:solid 0.5px #000;">$ '.number_format($proceso['proceso'][0]->ValorCreditoSolicitado,0,'',',').'</td>
                            </tr>
                            <tr>
                                <th style="border:solid 0.5px #000;">Plazo</th>
                                <td style="border:solid 0.5px #000;">'.$proceso['proceso'][0]->NumeroCuotas.' cuotas</td>
                            </tr>
                            <tr>
                                <th style="border:solid 0.5px #000;">Tasa</th>
                                <td style="border:solid 0.5px #000;">'.$proceso['proceso'][0]->TasaInteres.' %</td>
                            </tr>
                            <tr>
                                <th style="border:solid 0.5px #000;">Cuota</th>
                                <td style="border:solid 0.5px #000;">$ '.number_format($proceso['proceso'][0]->ValorCuota,0,'',',').'</td>
                            </tr>';
        }else{
            $cuerpo .= '<p>Buen dd&iacutea,</p><br><br>';
            $cuerpo .= $request->input('texto');
        }
        $data = ['asunto'=>$asunto,'destinatario'=>$to[0]->email,'cuerpo'=>$cuerpo,'copiaA'=>'','emailBcc'=>''];
        $enviar = PHPMailerController::crearEmail($data);
        if($enviar){
            return response()->json('ok');
        }else{
            return response()->json('fail');
        }
    }

    /**Crear documento con estudio del credito solicitado */
    public function descargarInfoCredito($idProceso){
        $proceso = Procesos::mostrarInfoProcesos($idProceso,'approveCredit');
        $configuracion = $proceso['proceso'][0]->Configuracion;
        $operacion = $proceso['proceso'][0]->ValoresOperacion;
        //$nuevoStringConfiguracion = str_replace();
        $separarConfiguracion = explode('|',$configuracion);
        $nuevoArrayConfiguracion = '';
        foreach($separarConfiguracion as $data){
            $nuevoArrayConfiguracion .= $data;
        }
        $separadores = ['+','-','(',')','/','1','2','3','4','5','6','7','8','9','0'];
        $nuevoArrayConfiguracion = str_replace($separadores,'|',$nuevoArrayConfiguracion);
        $separarOperacion = str_replace(['+','-','(',')','/'],'|',$operacion);
        $separarOperacion = explode('|',$separarOperacion);
        $nuevoArrayConfiguracion = explode('|',$nuevoArrayConfiguracion);
        $array[] = new stdClass;
        $contador = 0;
        //return view('procesos.documento-estudio-credito',compact('separarOperacion','nuevoArrayConfiguracion'));
        // foreach($separarConfiguracion as $key => $data){
        //     if(!in_array($data,['-','+','/','(',')','*'])){
        //         foreach($separarOperacion as $key2 => $data2){
        //             if($key)
        //             $array[$contador]->$data = $data2;
        //         }
        //         $contador++;
        //     }
        // }
        
        $rubros = Admin::mostrarRubrosPagaduria($proceso['proceso'][0]->IdPagaduria);
        view()->share(['proceso'=>$proceso,'rubros'=>$rubros]);
        $pdf = Pdf::loadView('procesos.documento-estudio-credito',$proceso,$rubros);
        return $pdf->download('archivo.pdf');
        return view('procesos.documento-estudio-credito',$proceso);
    }
}

// class WSSoapClient extends SoapClient{
// 	private $OASIS = 'http://docs.oasis-open.org/wss/2004/01';
// 	/**
// 	 * WS-Security Username
// 	 * @var string
// 	 */
// 	private $username;
// 	/**
// 	 * WS-Security Password
// 	 * @var string
// 	 */
// 	private $password;
// 	/**
// 	 * WS-Security PasswordType
// 	 * @var string
// 	 */
// 	private $passwordType;
// 	/**
// 	 * Set WS-Security credentials
// 	 * 
// 	 * @param string $username
// 	 * @param string $password
// 	 * @param string $passwordType
// 	 */
// 	public function __setUsernameToken($username, $password, $passwordType){
// 		$this->username = $username;
// 		$this->password = $password;
// 		$this->passwordType = $passwordType;
// 	}
	   
// 	/**
// 	 * Overwrites the original method adding the security header.
// 	 * As you can see, if you want to add more headers, the method needs to be modified.
// 	 */
// 	public function __call($function_name, $arguments){
// 		$this->__setSoapHeaders($this->generateWSSecurityHeader());
// 		return parent::__call($function_name, $arguments);
// 	}

//     public function callBackPassword($password,$hashed_password){
//         return password_verify($password,$hashed_password);
//     }
	    
// 	/**
// 	 * Generate password digest.
// 	 * 
// 	 * Using the password directly may work also, but it's not secure to transmit it without encryption.
// 	 * And anyway, at least with axis+wss4j, the nonce and timestamp are mandatory anyway.
// 	 * 
// 	 * @return string   base64 encoded password digest
// 	 */
// 	private function generatePasswordDigest(){
// 		$this->nonce = mt_rand();
// 		$this->timestamp = gmdate('Y-m-d\TH:i:s\Z');
		
// 		$packedNonce = pack('H*', $this->nonce);
// 		$packedTimestamp = pack('a*', $this->timestamp);
// 		$packedPassword = pack('a*', $this->password);
		
// 		$hash = sha1($packedNonce . $packedTimestamp . $packedPassword);
// 		$packedHash = pack('H*', $hash);
		
// 		return base64_encode($packedHash);
// 	}
	
// 	/**
// 	 * Generates WS-Security headers
// 	 * 
// 	 * @return SoapHeader
// 	 */
// 	private function generateWSSecurityHeader(){
// 		if ($this->passwordType === 'PasswordDigest'){
// 			$password = $this->generatePasswordDigest();
// 			$nonce = sha1($this->nonce);
// 		}elseif ($this->passwordType === 'PasswordText'){
// 			$password = $this->password;
// 			$nonce = sha1(mt_rand());
// 		}else{
// 			return '';
// 		}

// 		$xml = '<wsse:Security mustUnderstand="NONE" xmlns:wsse="' . $this->OASIS . '/oasis-200401-wss-wssecurity-secext-1.0.xsd">
// 	            <wsse:UsernameToken>
// 	            <wsse:Username>' . $this->username . '</wsse:Username>
// 	            <wsse:Password Type="' . $this->OASIS . '/oasis-200401-wss-username-token-profile-1.0#' . $this->passwordType . '">' . $password . '</wsse:Password>
// 	            <wsse:Nonce EncodingType="' . $this->OASIS . '/oasis-200401-wss-soap-message-security-1.0#Base64Binary">' . $nonce . '</wsse:Nonce>';
		
// 		if ($this->passwordType === 'PasswordDigest'){
// 			$xml .= "\n\t" . '<wsu:Created xmlns:wsu="' . $this->OASIS . '/oasis-200401-wss-wssecurity-utility-1.0.xsd">' . $this->timestamp . '</wsu:Created>';
// 		}
		
// 		$xml .= '</wsse:UsernameToken></wsse:Security>';
		
// 		return new SoapHeader($this->OASIS.'/oasis-200401-wss-wssecurity-secext-1.0.xsd','Security', new SoapVar($xml, XSD_ANYXML),true);
// 	}
// }