<?php
namespace App\Http\Controllers;

ini_set('max_execution_time', 600);

use App\Models\Admin;
use App\Models\Contable;
use DateTime;
use Illuminate\Http\Request;
use DOMDocument;
use DOMXPath;

class ContableController extends Controller
{
    public function documentoContableFiltros(Request $request){
        $tipoDocumento = $request->get('tipoDocumento');
        $fechaInicial = ($request->get('fechaInicial') != '')? $request->get('fechaInicial') : date('Y-m-d');
        $fechaFinal = ($request->get('fechaFinal') != '')? $request->get('fechaFinal') : date('Y-m-d');
        $htmlTabla = ''; $htmlBoton = ''; $facturas = '';
        if($tipoDocumento == 'NCR'){
            $datosOperaciones = Contable::consultarDocumentosContablesNotas($tipoDocumento,$fechaInicial,$fechaFinal/*'FEX','2019-03-19T00:00:00','2019-03-19T00:00:00'*/);
        }else{
            $datosOperaciones = Contable::consultarDocumentosContables($tipoDocumento,$fechaInicial,$fechaFinal/*'FEX','2019-03-19T00:00:00','2019-03-19T00:00:00'*/);
        }

        if(count($datosOperaciones) > 0){
            foreach($datosOperaciones as $data){
                $facturas_excluidas = ['61745','61845','69723','69724','69725','69726','69727','69728','69729','69730','69731','69747','69748', '71292', '74412', '75789', '86714'];
                $notas_excluidas = ['537'];
                if(($data->tipoDocumento == 'FEX' && in_array($data->factoring,$facturas_excluidas)) || ($data->tipoDocumento == 'NCR' && in_array($data->factoring,$notas_excluidas)) ) {
                    // Omitir esta factura exacta
                } else {
                    $htmlTabla .= '<tr>
                                    <td class="text-center">'.$data->tipoDocumento.'</td>
                                    <td class="text-center">'.$data->factoring.'</td>
                                    <td class="text-center">
                                        <button data-toggle="tooltip" title="Enviar Operacion '.$data->factoring.'" style="padding: 3px;font-size: 10px;line-height: 0;" class="btn btn-primary" 
                                        onclick="EnviarOperacion(`'.$data->factoring.'`)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </td>
                                </tr>';
                    if($data->factoring != $data->siesa){
                        $facturas .= $data->factoring.',';
                    }
                }
            }
            if($facturas != ""){
                $facturas = substr($facturas,0,-1);
            }
            $htmlBoton .= '<div class="row">
                            <div class="col-md-6 col-xs-12 text-center mt-2 p-2">
                                <button class="btn btn-primary" id="btn_enviar" data-toggle="tooltip" title="Enviar Todas Las Facturas" onclick="EnviarTodos(`'.$facturas.'`)" >
                                    <i class="far fa-paper-plane"></i>&nbsp;&nbsp;Enviar Todos
                                </button>
                            </div>
                        </div>';
        }
        return response()->json(['tabla'=>$htmlTabla,'boton'=>$htmlBoton]);
    }

    /**Envío de documentos contables a siesa */
    public function documentoContableEnvioSiesa(Request $request){
        set_time_limit(0);
        $errores = [];
        $idOperacion = $request->get('idOperacion');
        $tipoDocumento = $request->get('tipoDocumento');
        // require_once('nusoap.php');
        // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
        // $client->soap_defencoding = 'UTF-8';
        // $client->decode_utf8 = true;
        $numero = 0;
        $factoringFactura = Contable::factoringFactura($idOperacion,$tipoDocumento/*'6077','FEX'*/);
        $response = '';
        if(count($factoringFactura) > 0){
            foreach ($factoringFactura as $key => $value) {
                if($numero != $value->idfactura){
                    $numero = $value->idfactura;
                    $Linea = '';
                    $CO_PRINCIPAL = '001';
                    $F_NUMERO_REG = '0000002';
                    $F_TIPO_REG = '0350';
                    $F_SUBTIPO_REG = '00';
                    $F_VERSION_REG = '01';
                    $F_CIA = '007';  // COMPAÑIA 007 (ARAR Financiera)
                    $F_CONSEC_AUTO_REG = '0';
                    $F350_ID_CLASE_DOCTO = '00030';
                    $F350_IND_IMPRESION = '0'; // Estado de impresión del documento
    
                    // Prueba
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n";*/
                
                    // Real
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n";*/
                    $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";
    
                    $cabecera = "<Linea>000000100000001".$F_CIA."</Linea>\r\n";
                    $parameters .= $cabecera;
                    
                    if($factoringFactura != 0){
                        foreach ($factoringFactura as $key => $value) {
                            if($numero == $value->idfactura){
                                $F350_ID_TIPO_DOCTO = $value->tipoDocumento;
                                $F350_CONSEC_DOCTO = str_pad($value->idfactura,8,"0",STR_PAD_LEFT);
                                $F350_FECHA = date_format(new DateTime($value->fecdocumento),'Ymd');
                                
                                if(preg_match("/-/",$value->idtercero)){
                                    $F350_ID_TERCERO =  substr($value->idtercero, 0, -2);
                                }
                                else{
                                    $F350_ID_TERCERO =  $value->idtercero;
                                }
    
                                $F350_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                                $F350_IND_ESTADO = 1;
    
                                $F350_NOTAS = str_pad($this->eliminarAcentos($value->Nota),255," ",STR_PAD_RIGHT);
                            }
                        }
    
                        $a = 2;
                        $Linea .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_CONSEC_AUTO_REG.
                        $CO_PRINCIPAL.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F350_FECHA.$F350_ID_TERCERO.$F350_ID_CLASE_DOCTO.$F350_IND_ESTADO.$F350_IND_IMPRESION.$F350_NOTAS."</Linea>\r\n";
    
                        $parameters .= $Linea;
                        $Linea1 = '';
    
                        foreach ($factoringFactura as $value) {
                            if($numero == $value->idfactura){
                                if($value->tipo == 'M'){
                                    // MOVIMIENTO CONTABLE
                                    $a++;
                                    $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                                    $F_TIPO_REG = '0351';
                                    $F_SUBTIPO_REG = '00';
                                    $F_VERSION_REG = '02';
                                    $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                                    $F350_ID_CO = '001';
                                    $F350_ID_TIPO_DOCTO = $value->tipoDocumento;
                                    $F350_CONSEC_DOCTO = str_pad($value->idfactura,8,"0",STR_PAD_LEFT);
                                    $F351_ID_AUXILIAR = str_pad($value->cuenta,20, " ", STR_PAD_RIGHT);
                                    if(preg_match("/-/",$value->idtercero)){
                                        $F350_ID_TERCERO =  substr($value->idtercero, 0, -2);
                                    }else{
                                        $F350_ID_TERCERO =  $value->idtercero;
                                    }
                                    $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15, " ", STR_PAD_RIGHT);
                                    $F351_ID_CO_MOV = '001';
                                    $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);
                                    $F351_ID_CCOSTO = str_pad($value->centrocosto,15, " ", STR_PAD_RIGHT);
                                    $F351_ID_FE = str_pad('',10, " ", STR_PAD_RIGHT);
    
                                    if($value->Debito != 0){
                                        $F351_VALOR_DB_SIGNO = '+';
                                        $F351_VALOR_DB_VALOR = str_pad($value->Debito,20,"0",STR_PAD_LEFT);
                                        $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;
                                    }else{
                                        $F351_VALOR_DB = '+000000000000000.0000';
                                    }
    
                                    if($value->credito != 0){
                                        $F351_VALOR_CR_SIGNO = '+';
                                        $F351_VALOR_CR_VALOR = str_pad($value->credito,20,"0",STR_PAD_LEFT);
                                        $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR;
                                    }else{
                                        $F351_VALOR_CR = '+000000000000000.0000';
                                    }
    
                                    $F351_VALOR_DB_ALT = '+000000000000000.0000';
                                    $F351_VALOR_CR_ALT = '+000000000000000.0000';
    
                                    if($value->Base != 0){
                                        $F351_BASE_GRAVABLE_SIGNO = '+';
                                        $F351_VALOR_GRAVABLE_VALOR = str_pad($value->Base,20,"0",STR_PAD_LEFT);
                                        $F351_BASE_GRAVABLE	 = $F351_BASE_GRAVABLE_SIGNO.$F351_VALOR_GRAVABLE_VALOR;
                                    }else{
                                        $F351_BASE_GRAVABLE	 = '+000000000000000.0000';
                                    }
    
                                    $F351_DOCTO_BANCO = str_pad('',2, " ", STR_PAD_RIGHT);
                                    $F351_NRO_DOCTO_BANCO = '00000000';
                                    $F351_NOTAS = str_pad($this->eliminarAcentos($value->Nota),255," ",STR_PAD_RIGHT);
                                    $Linea1 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F350_ID_CO.
                                    $F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.$F351_ID_UN.
                                    $F351_ID_CCOSTO.$F351_ID_FE.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_BASE_GRAVABLE.
                                    $F351_DOCTO_BANCO.$F351_NRO_DOCTO_BANCO.$F351_NOTAS."</Linea>\r\n";   
                                }     
                            }
                        }
    
                        $parameters .= $Linea1;
                        $Linea2 = '';
                    
                        foreach ($factoringFactura as $key => $value) {
                            if($numero == $value->idfactura){
                                if($value->tipo == 'C'){
                                    $a++;
                                    $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                                    $F_TIPO_REG = '0351';
                                    $F_SUBTIPO_REG = '01';
                                    $F_VERSION_REG = '02';
                                    $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                                    $F350_ID_CO = '001';
                                    $F350_ID_TIPO_DOCTO = $value->tipoDocumento;
                                    $F350_CONSEC_DOCTO = str_pad($value->idfactura,8,"0",STR_PAD_LEFT);
                                    $F351_ID_AUXILIAR = str_pad($value->cuenta,20, " ", STR_PAD_RIGHT);
                                    $F350_FECHA = date_format(new DateTime($value->fecdocumento),'Ymd');
                                    
                                    if(preg_match("/-/",$value->idtercero)){
                                        $F351_ID_TERCERO =  substr($value->idtercero, 0, -2);
                                    }
                                    else{
                                        $F351_ID_TERCERO =  $value->idtercero;
                                    }
    
                                    $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                                    $F351_ID_CO_MOV = '001';
                                    $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);
                                    $F351_ID_CCOSTO = str_pad('',15, " ", STR_PAD_RIGHT);
                                    $F351_VALOR_DB_SIGNO = '+';
                                    $F351_VALOR_DB_VALOR = str_pad($value->Debito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;
                                    $F351_VALOR_CR_SIGNO = '+';
                                    $F351_VALOR_CR_VALOR = str_pad($value->credito,15,"0",STR_PAD_LEFT);
                                    $F351_VALOR_CR_VALOR1 = str_pad('.',5,"0",STR_PAD_RIGHT);
                                    $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR.$F351_VALOR_CR_VALOR1;
                                    $F351_VALOR_DB_ALT = '+000000000000000.0000';
                                    $F351_VALOR_CR_ALT = '+000000000000000.0000';
                                    $F351_NOTAS = str_pad($this->eliminarAcentos($value->Nota),255," ",STR_PAD_RIGHT);
                                    $F353_ID_SUCURSAL = '001';                        
                                    $F353_ID_TIPO_DOCTO_CRUCE = str_pad($value->tipoDocumento,3, " ", STR_PAD_RIGHT);
                                    $F353_CONSEC_DOCTO_CRUCE = str_pad($value->idfactura,8,"0",STR_PAD_LEFT);
                                    $F353_NRO_CUOTA_CRUCE = str_pad('0',3, "0", STR_PAD_RIGHT);
                                    $F353_FECHA_VCTO = date_format(new DateTime($value->fechaVencimiento),'Ymd');
                                    $F353_FECHA_DSCTO_PP = date_format(new DateTime($value->fechaVencimiento),'Ymd');
                                    $F353_VLR_DSCTO_PP = '+000000000000000.0000';
                                    $F354_VALOR_APLICADO_PP = '+000000000000000.0000';
                                    $F354_VALOR_APLICADO_PP_ALT = '+000000000000000.0000';
                                    $F354_VALOR_APROVECHA = '+000000000000000.0000';
                                    $F354_VALOR_APROVECHA_ALT = '+000000000000000.0000';
                                    $F354_VALOR_RETENCION = '+000000000000000.0000';
                                    $F354_VALOR_RETENCION_ALT = '+000000000000000.0000';
                                    $F354_TERCERO_VEND = str_pad('900644447',15, " ", STR_PAD_RIGHT);
                                    $F354_NOTAS = str_pad($this->eliminarAcentos($value->Nota),255," ",STR_PAD_RIGHT);
                                    $Linea2 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.
                                    $F350_ID_CO.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.$F351_ID_UN.
                                    $F351_ID_CCOSTO.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_NOTAS.$F353_ID_SUCURSAL.
                                    $F353_ID_TIPO_DOCTO_CRUCE.$F353_CONSEC_DOCTO_CRUCE.$F353_NRO_CUOTA_CRUCE.$F353_FECHA_VCTO.$F353_FECHA_DSCTO_PP.
                                    $F353_VLR_DSCTO_PP.$F354_VALOR_APLICADO_PP.$F354_VALOR_APLICADO_PP_ALT.$F354_VALOR_APROVECHA.$F354_VALOR_APROVECHA_ALT.$F354_VALOR_RETENCION.
                                    $F354_VALOR_RETENCION_ALT.$F354_TERCERO_VEND.$F354_NOTAS."</Linea>\r\n";
                                }
                            }
                        }
                        $parameters .= $Linea2;
                    }
    
                    $a++;
                    $finlinea = str_pad($a,7, "0", STR_PAD_LEFT);
                    $fin = "<Linea>".$finlinea."99990001007</Linea>\r\n";
                    $parameters .= $fin;

                    $parameters .= "</Datos>\r\n</Importar>";

                    $resultado = $this->procesarPeticionSiesa($parameters);
                    if($resultado !== 'ok'){
                        $errores[$numero] = implode("\n", array_map(function($e){
                            $detalle = (string) $e['f_detalle'];
                            $detalle = mb_check_encoding($detalle, 'UTF-8') ? $detalle : mb_convert_encoding($detalle, 'UTF-8', 'ISO-8859-1');
                            return 'Línea '.$e['f_nro_linea'].': '.$detalle.' ('.$e['f_valor'].')';
                        }, $resultado));
                    }
    
                    // $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";
                    // print_r($parameters);
                    // exit;
                    // $result = $client->call('ImportarXML',$parameters);
                    // $Resultado = $result['printTipoError'];
                    // $response = '';
                    // if($Resultado == '1') {
                    //     $response .= 'Lo Sentimos,  Se presentaron Errores en el proceso.';
                    //     $errores = $result['ImportarXMLResult']['diffgram']['NewDataSet']['Table'];
                    //     if (!empty($errores)){
                    //         $f_detalle = $f_valor = $f_nro_linea = ''; 
                    //         foreach ($errores as $rows) {
                    //             if (!empty($rows['f_detalle'])) {
                    //                 $f_detalle = $rows['f_detalle'];
                    //                 $f_valor = $rows['f_valor'];
                    //                 $f_nro_linea = $rows['f_nro_linea'];
                    //                 $response .= $f_nro_linea."\n";
                    //                 $response .= $f_valor."\n";
                    //                 $response .= utf8_encode($f_detalle)."\n";
                    //             }	
                    //         }
                    //         $errores = $result['ImportarXMLResult']['diffgram']['NewDataSet'];
                    //         foreach ($errores as $rows){
                    //             if (!empty($rows['f_detalle'])){
                    //                 $f_detalle = $rows['f_detalle'];
                    //                 $f_valor = $rows['f_valor'];
                    //                 $f_nro_linea = $rows['f_nro_linea'];	
                                    
                    //                 $response .= $f_nro_linea."\n"; 
                    //                 $response .= $f_valor."\n"; 
                    //                 $response .= utf8_encode($f_detalle)."\n"; 
                    //             }	
                    //         }
                    //         return response()->json($response);
                    //     }
                    // }else{
                    //     $response = 'ok';
                    // }
                }
            }
            $response = empty($errores) ? 'ok' : ['errores' => $errores];
        }else{
            $response = ['error' => 'No existe el registro en la base de datos'];
        }
        return response()->json($response);
    }

    /**Envío de notas credito contables a siesa */
    public function NotaCreditoContableEnvioSiesa(Request $request){
        set_time_limit(0);
        $errores = [];
        $idOperacion = $request->get('idOperacion');
        $tipoDocumento = $request->get('tipoDocumento');
        require_once('nusoap.php');
        // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
        // $client->soap_defencoding = 'UTF-8';
        // $client->decode_utf8 = true;
        $numero = 0;
        $factoringNotaCredito = Contable::factoringNotaCredito($idOperacion,$tipoDocumento/*'6077','FEX'*/);
        // return response()->json(count($factoringNotaCredito));
        $response = '';
        if(count($factoringNotaCredito) > 0){
            foreach ($factoringNotaCredito as $key => $value) {
                if($numero != $value->idnotacredito){
                    $numero = $value->idnotacredito;
                    $Linea = '';
                    $CO_PRINCIPAL = '001';
                    $F_NUMERO_REG = '0000002';
                    $F_TIPO_REG = '0350';
                    $F_SUBTIPO_REG = '00';
                    $F_VERSION_REG = '01';
                    $F_CIA = '007';  // COMPAÑIA 007 (ARAR Financiera)
                    $F_CONSEC_AUTO_REG = '0';
                    $F350_ID_CLASE_DOCTO = '00030';
                    $F350_IND_IMPRESION = '0'; // Estado de impresión del documento
    
                    // Prueba
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n";*/
                
                    // Real
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n";*/
                    $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";
    
                    $cabecera = "<Linea>000000100000001".$F_CIA."</Linea>\r\n";
                    $parameters .= $cabecera;
                    
                    if($factoringNotaCredito != 0){
                        foreach ($factoringNotaCredito as $key => $value) {
                            if($numero == $value->idnotacredito){
                                $F350_ID_TIPO_DOCTO = $value->tipoDocumento;
                                $F350_CONSEC_DOCTO = str_pad($value->idnotacredito,8,"0",STR_PAD_LEFT);
                                $F350_FECHA = date_format(new DateTime($value->fecdocumento),'Ymd');
                                
                                if(preg_match("/-/",$value->idtercero)){
                                    $F350_ID_TERCERO =  substr($value->idtercero, 0, -2);
                                }
                                else{
                                    $F350_ID_TERCERO =  $value->idtercero;
                                }
    
                                $F350_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                                $F350_IND_ESTADO = 1;
    
                                $F350_NOTAS = str_pad($value->Nota,255," ",STR_PAD_RIGHT);
                            }
                        }
    
                        $a = 2;
                        $Linea .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_CONSEC_AUTO_REG.
                        $CO_PRINCIPAL.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F350_FECHA.$F350_ID_TERCERO.$F350_ID_CLASE_DOCTO.$F350_IND_ESTADO.$F350_IND_IMPRESION.$F350_NOTAS."</Linea>\r\n";
    
                        $parameters .= $Linea;
                        $Linea1 = '';
    
                        foreach ($factoringNotaCredito as $value) {
                            if($numero == $value->idnotacredito){
                                if($value->tipo == 'M'){
                                    // MOVIMIENTO CONTABLE
                                    $a++;
                                    $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                                    $F_TIPO_REG = '0351';
                                    $F_SUBTIPO_REG = '00';
                                    $F_VERSION_REG = '02';
                                    $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                                    $F350_ID_CO = '001';
                                    $F350_ID_TIPO_DOCTO = $value->tipoDocumento;
                                    $F350_CONSEC_DOCTO = str_pad($value->idnotacredito,8,"0",STR_PAD_LEFT);
                                    $F351_ID_AUXILIAR = str_pad($value->cuenta,20, " ", STR_PAD_RIGHT);
                                    if(preg_match("/-/",$value->idtercero)){
                                        $F350_ID_TERCERO =  substr($value->idtercero, 0, -2);
                                    }else{
                                        $F350_ID_TERCERO =  $value->idtercero;
                                    }
                                    $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15, " ", STR_PAD_RIGHT);
                                    $F351_ID_CO_MOV = '001';
                                    $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);
                                    $F351_ID_CCOSTO = str_pad($value->centrocosto,15, " ", STR_PAD_RIGHT);
                                    $F351_ID_FE = str_pad('',10, " ", STR_PAD_RIGHT);
    
                                    if($value->Debito != 0){
                                        $F351_VALOR_DB_SIGNO = '+';
                                        $F351_VALOR_DB_VALOR = str_pad($value->Debito,20,"0",STR_PAD_LEFT);
                                        $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;
                                    }else{
                                        $F351_VALOR_DB = '+000000000000000.0000';
                                    }
    
                                    if($value->credito != 0){
                                        $F351_VALOR_CR_SIGNO = '+';
                                        $F351_VALOR_CR_VALOR = str_pad($value->credito,20,"0",STR_PAD_LEFT);
                                        $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR;
                                    }else{
                                        $F351_VALOR_CR = '+000000000000000.0000';
                                    }
    
                                    $F351_VALOR_DB_ALT = '+000000000000000.0000';
                                    $F351_VALOR_CR_ALT = '+000000000000000.0000';
    
                                    if($value->Base != 0){
                                        $F351_BASE_GRAVABLE_SIGNO = '+';
                                        $F351_VALOR_GRAVABLE_VALOR = str_pad($value->Base,20,"0",STR_PAD_LEFT);
                                        $F351_BASE_GRAVABLE	 = $F351_BASE_GRAVABLE_SIGNO.$F351_VALOR_GRAVABLE_VALOR;
                                    }else{
                                        $F351_BASE_GRAVABLE	 = '+000000000000000.0000';
                                    }
    
                                    $F351_DOCTO_BANCO = str_pad('',2, " ", STR_PAD_RIGHT);
                                    $F351_NRO_DOCTO_BANCO = '00000000';
                                    $F351_NOTAS = str_pad($value->Nota,255," ",STR_PAD_RIGHT);
                                    $Linea1 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F350_ID_CO.
                                    $F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.$F351_ID_UN.
                                    $F351_ID_CCOSTO.$F351_ID_FE.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_BASE_GRAVABLE.
                                    $F351_DOCTO_BANCO.$F351_NRO_DOCTO_BANCO.$F351_NOTAS."</Linea>\r\n";   
                                }     
                            }
                        }
                        // print_r($F351_VALOR_DB.' -- '.$F351_VALOR_CR);
                        // exit;
    
                        $parameters .= $Linea1;
                        $Linea2 = '';
                    
                        foreach ($factoringNotaCredito as $key => $value) {
                            if($numero == $value->idnotacredito){
                                if($value->tipo == 'C'){
                                    // print_r($value->credito);
                                    // print_r($value->Debito);
                                    // exit;
                                    $a++;
                                    $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                                    $F_TIPO_REG = '0351';
                                    $F_SUBTIPO_REG = '01';
                                    $F_VERSION_REG = '02';
                                    $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                                    $F350_ID_CO = '001';
                                    $F350_ID_TIPO_DOCTO = $value->tipoDocumento;
                                    $F350_CONSEC_DOCTO = str_pad($value->idnotacredito,8,"0",STR_PAD_LEFT);
                                    $F351_ID_AUXILIAR = str_pad($value->cuenta,20, " ", STR_PAD_RIGHT);
                                    $F350_FECHA = date_format(new DateTime($value->fecdocumento),'Ymd');
                                    
                                    if(preg_match("/-/",$value->idtercero)){
                                        $F351_ID_TERCERO =  substr($value->idtercero, 0, -2);
                                    }
                                    else{
                                        $F351_ID_TERCERO =  $value->idtercero;
                                    }
    
                                    $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                                    $F351_ID_CO_MOV = '001';
                                    $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);
                                    $F351_ID_CCOSTO = str_pad('',15, " ", STR_PAD_RIGHT);
                                    $F351_VALOR_DB_SIGNO = '+';
                                    $F351_VALOR_DB_VALOR = str_pad($value->Debito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;
                                    $F351_VALOR_CR_SIGNO = '+';
                                    $F351_VALOR_CR_VALOR = str_pad($value->credito,15,"0",STR_PAD_LEFT);
                                    $F351_VALOR_CR_VALOR1 = str_pad('.',5,"0",STR_PAD_RIGHT);
                                    $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR.$F351_VALOR_CR_VALOR1;
                                    $F351_VALOR_DB_ALT = '+000000000000000.0000';
                                    $F351_VALOR_CR_ALT = '+000000000000000.0000';
                                    $F351_NOTAS = str_pad($value->Nota,255," ",STR_PAD_RIGHT);
                                    $F353_ID_SUCURSAL = '001';                        
                                    $F353_ID_TIPO_DOCTO_CRUCE = str_pad($value->tipoDocumento,3, " ", STR_PAD_RIGHT);
                                    $F353_CONSEC_DOCTO_CRUCE = str_pad($value->idnotacredito,8,"0",STR_PAD_LEFT);
                                    $F353_NRO_CUOTA_CRUCE = str_pad('0',3, "0", STR_PAD_RIGHT);
                                    $F353_FECHA_VCTO = date_format(new DateTime($value->fechaVencimiento),'Ymd');
                                    $F353_FECHA_DSCTO_PP = date_format(new DateTime($value->fechaVencimiento),'Ymd');
                                    $F353_VLR_DSCTO_PP = '+000000000000000.0000';
                                    $F354_VALOR_APLICADO_PP = '+000000000000000.0000';
                                    $F354_VALOR_APLICADO_PP_ALT = '+000000000000000.0000';
                                    $F354_VALOR_APROVECHA = '+000000000000000.0000';
                                    $F354_VALOR_APROVECHA_ALT = '+000000000000000.0000';
                                    $F354_VALOR_RETENCION = '+000000000000000.0000';
                                    $F354_VALOR_RETENCION_ALT = '+000000000000000.0000';
                                    $F354_TERCERO_VEND = str_pad('900644447',15, " ", STR_PAD_RIGHT);
                                    $F354_NOTAS = str_pad($value->Nota,255," ",STR_PAD_RIGHT);
                                    $Linea2 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.
                                    $F350_ID_CO.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.$F351_ID_UN.
                                    $F351_ID_CCOSTO.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_NOTAS.$F353_ID_SUCURSAL.
                                    $F353_ID_TIPO_DOCTO_CRUCE.$F353_CONSEC_DOCTO_CRUCE.$F353_NRO_CUOTA_CRUCE.$F353_FECHA_VCTO.$F353_FECHA_DSCTO_PP.
                                    $F353_VLR_DSCTO_PP.$F354_VALOR_APLICADO_PP.$F354_VALOR_APLICADO_PP_ALT.$F354_VALOR_APROVECHA.$F354_VALOR_APROVECHA_ALT.$F354_VALOR_RETENCION.
                                    $F354_VALOR_RETENCION_ALT.$F354_TERCERO_VEND.$F354_NOTAS."</Linea>\r\n";
                                }
                            }
                        }
                        $parameters .= $Linea2;
                    }
    
                    $a++;
                    $finlinea = str_pad($a,7, "0", STR_PAD_LEFT);
                    $fin = "<Linea>".$finlinea."99990001007</Linea>\r\n";
                    $parameters .= $fin;

                    $parameters .= "</Datos>\r\n</Importar>";

                    $resultado = $this->procesarPeticionSiesa($parameters);
                    if($resultado !== 'ok'){
                        $errores[$numero] = implode("\n", array_map(function($e){
                            $detalle = (string) $e['f_detalle'];
                            $detalle = mb_check_encoding($detalle, 'UTF-8') ? $detalle : mb_convert_encoding($detalle, 'UTF-8', 'ISO-8859-1');
                            return 'Línea '.$e['f_nro_linea'].': '.$detalle.' ('.$e['f_valor'].')';
                        }, $resultado));
                    }
    
                    // $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";              
                    // $result = $client->call('ImportarXML',$parameters);
                    // $Resultado = $result['printTipoError'];
                    // $response = '';
                    // if($Resultado == '1') {
                    //     $response .= 'Lo Sentimos,  Se presentaron Errores en el proceso.';
                    //     $errores = $result['ImportarXMLResult']['diffgram']['NewDataSet']['Table'];
                    //     if (!empty($errores)){
                    //         $f_detalle = $f_valor = $f_nro_linea = ''; 
                    //         foreach ($errores as $rows) {
                    //             if (!empty($rows['f_detalle'])) {
                    //                 $f_detalle = $rows['f_detalle'];
                    //                 $f_valor = $rows['f_valor'];
                    //                 $f_nro_linea = $rows['f_nro_linea'];
                    //                 $response .= $f_nro_linea."\n";
                    //                 $response .= $f_valor."\n";
                    //                 $response .= utf8_encode($f_detalle)."\n";
                    //             }	
                    //         }
                    //         $errores = $result['ImportarXMLResult']['diffgram']['NewDataSet'];
                    //         foreach ($errores as $rows){
                    //             if (!empty($rows['f_detalle'])){
                    //                 $f_detalle = $rows['f_detalle'];
                    //                 $f_valor = $rows['f_valor'];
                    //                 $f_nro_linea = $rows['f_nro_linea'];	
                                    
                    //                 $response .= $f_nro_linea."\n"; 
                    //                 $response .= $f_valor."\n"; 
                    //                 $response .= utf8_encode($f_detalle)."\n"; 
                    //             }	
                    //         }
                    //         return response()->json($response);
                    //     }
                    // }else{
                    //     $response = 'ok';
                    // }
                }
            }
            $response = empty($errores) ? 'ok' : ['errores' => $errores];
        }else{
            $response = ['error' => 'No existe el registro en la base de datos'];
        }
        return response()->json($response);
    }

    public function reclasificarFiltros(Request $request){
        $operacion = $request->get('operacion');
        $tabla = '';
        $consultaReclasificacion = Contable::consultarReclasificaciones($operacion/*'113'*/);
        if($consultaReclasificacion != 0){
            foreach ($consultaReclasificacion as $data) {
                $tabla .= '<tr>
                                <td class="text-center">'.$data->tipdocumento.'</td>
                                <td class="text-center">'.$data->IdOperacion.'</td>
                                <td class="text-center">
                                    <button data-toggle="tooltip" title="Enviar Operacion '.$data->IdOperacion.'" id="btnEnviar" class="btn btn-primary" onclick="EnviarOperacion(`'.$data->IdOperacion.'`)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </td>
                            </tr>';
            }
        }
        $status = 'ok'; 
        return response()->json(compact('status','tabla'));
    }

    public function reclasificarOpEnvioSiesa(Request $request){
        $idOperacion = $request->get('idOperacion');
        $fechaOperacion = $request->get('fechaOperacion');
        require_once('nusoap.php');
        // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
        // $client->soap_defencoding = 'UTF-8';
        // $client->decode_utf8 = true;
        $numero = 0;
        $Recla='Operacion Refinanciada';
        $busquedaOperacion = Contable::busquedaOperacion($idOperacion/*'113'*/);
        if(count($busquedaOperacion) > 0){
            foreach ($busquedaOperacion as $data) {
                if($numero != $data->IdOperacion){
                    $numero = $data->IdOperacion;
    
                    $Linea = '';
                    $CO_PRINCIPAL = '001';
                    $F_NUMERO_REG = '0000002';
                    $F_TIPO_REG = '0350';
                    $F_SUBTIPO_REG = '00';
                    $F_VERSION_REG = '01';
                    $F_CIA = '007';  // COMPAÑIA 007 (ARAR Financiera)
                    $F_CONSEC_AUTO_REG = '1';
                    $F350_ID_CLASE_DOCTO = '00030';
                    $F350_IND_IMPRESION = '0'; // Estado de impresión del documento
    
                    // Prueba
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */
            
                    // Real
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n";*/
                    $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";                    
                    
                    $cabecera = "<Linea>000000100000001".$F_CIA."</Linea>\r\n";
                    $parameters .= $cabecera;
    
                    foreach($busquedaOperacion as $key => $data){
                        if($numero == $data->IdOperacion){
                            $F350_ID_TIPO_DOCTO = $data->TipoDocumento;
                            $F350_CONSEC_DOCTO = str_pad($data->IdOperacion+10000000,8,"0",STR_PAD_LEFT);
                            $F350_FECHA = date_format(new DateTime($fechaOperacion),'Ymd');
                            
                            if(preg_match("/-/",$data->IdCliente)){
                                $F350_ID_TERCERO =  substr($data->IdCliente, 0, -2);
                            }else{
                                $F350_ID_TERCERO =  $data->IdCliente;
                            }
        
                            $F350_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                            $F350_IND_ESTADO = 1;
                            $F350_NOTAS = str_pad($Recla.$data->Nota,255," ",STR_PAD_RIGHT);
                        }
                    }
    
                    $Linea .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_CONSEC_AUTO_REG.
                    $CO_PRINCIPAL.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F350_FECHA.$F350_ID_TERCERO.$F350_ID_CLASE_DOCTO.$F350_IND_ESTADO.$F350_IND_IMPRESION.$F350_NOTAS."</Linea>\r\n";
                    $parameters .= $Linea;
    
                    $a = 2;
                    //MOVIMIENTO CONTABLE
                    $Linea1 = '';
                    foreach($busquedaOperacion as $data){
                        if($numero == $data->IdOperacion){
                            if($data->Tipo == 'M'){
                                $a++;
                                $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                                $F_TIPO_REG = '0351';
                                $F_SUBTIPO_REG = '00';
                                $F_VERSION_REG = '02';
                                $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                                $F350_ID_CO = '001';
                                $F350_ID_TIPO_DOCTO = $data->TipoDocumento;
                                $F350_CONSEC_DOCTO = str_pad($data->IdOperacion+10000000,8,"0",STR_PAD_LEFT);
                                $F351_ID_AUXILIAR = str_pad($data->cuenta,20, " ", STR_PAD_RIGHT);
                                if(preg_match("/-/",$data->IdCliente)){
                                    $F350_ID_TERCERO =  substr($data->IdCliente, 0, -2);
                                }else{
                                    $F350_ID_TERCERO =  $data->IdCliente;
                                }
                                $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15, " ", STR_PAD_RIGHT);
                                $F351_ID_CO_MOV = '001';
                                $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);    
                                $F351_ID_CCOSTO = str_pad('',15, " ", STR_PAD_RIGHT);
                                $F351_ID_FE = str_pad('',10, " ", STR_PAD_RIGHT);
    
                                if($data->Debito != 0){
                                    $F351_VALOR_DB_SIGNO = '+';
                                    $F351_VALOR_DB_VALOR = str_pad($data->Debito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;
                                }else{
                                    $F351_VALOR_DB = '+000000000000000.0000';
                                }
    
                                if($data->Credito != 0){
                                    $F351_VALOR_CR_SIGNO = '+';
                                    $F351_VALOR_CR_VALOR = str_pad($data->Credito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR;
                                }else{
                                    $F351_VALOR_CR = '+000000000000000.0000';
                                }
    
                                $F351_VALOR_DB_ALT = '+000000000000000.0000';
                                $F351_VALOR_CR_ALT = '+000000000000000.0000';
                                $F351_BASE_GRAVABLE	 = '+000000000000000.0000';
                                $F351_DOCTO_BANCO = str_pad('',2, " ", STR_PAD_RIGHT);
                                $F351_NRO_DOCTO_BANCO = '00000000';
                                $F351_NOTAS = str_pad($Recla.$data->Nota,255," ",STR_PAD_RIGHT);
                                $Linea1 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F350_ID_CO.
                                $F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.$F351_ID_UN.
                                $F351_ID_CCOSTO.$F351_ID_FE.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_BASE_GRAVABLE.
                                $F351_DOCTO_BANCO.$F351_NRO_DOCTO_BANCO.$F351_NOTAS."</Linea>\r\n";   
                            }     
                        }
                    }
                    $parameters .= $Linea1;
    
                    //MOVIMIENTO CxC
                    $Linea2 = '';
                    $cuota = 0;
                    $docucruce = 'OPE';
                    foreach($busquedaOperacion as $key => $data){
                        if($numero == $data->IdOperacion){
                            if($data->Tipo == 'C'){
                                $a++;
                                $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                                $F_TIPO_REG = '0351';
                                $F_SUBTIPO_REG = '01';
                                $F_VERSION_REG = '02';
                                $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                                $F350_ID_CO = '001';
                                $F350_ID_TIPO_DOCTO = $data->TipoDocumento;
                                $F350_CONSEC_DOCTO = str_pad($data->IdOperacion+10000000,8,"0",STR_PAD_LEFT);
                                $F351_ID_AUXILIAR = str_pad($data->cuenta,20, " ", STR_PAD_RIGHT);
                                $F350_FECHA = date_format(new DateTime($fechaOperacion),'Ymd');
                                
                                if(preg_match("/-/",$data->IdCliente)){
                                    $F351_ID_TERCERO =  substr($data->IdCliente, 0, -2);
                                }else{
                                    $F351_ID_TERCERO =  $data->IdCliente;
                                }
    
                                $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                                $F351_ID_CO_MOV = '001';
                                $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);
                                $F351_ID_CCOSTO = str_pad('',15, " ", STR_PAD_RIGHT);
                                $F351_VALOR_DB_SIGNO = '+';
                                $F351_VALOR_DB_VALOR = str_pad($data->Debito,20,"0",STR_PAD_LEFT);
                                $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;
    
                                if($data->Credito != 0){
                                    $F351_VALOR_CR_SIGNO = '+';
                                    $F351_VALOR_CR_VALOR = str_pad($data->Credito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR;
                                }else{
                                    $F351_VALOR_CR = '+000000000000000.0000';
                                }
    
                                $F351_VALOR_DB_ALT = '+000000000000000.0000';
                                $F351_VALOR_CR_ALT = '+000000000000000.0000';
                                $F351_NOTAS = str_pad($Recla.$data->Nota,255," ",STR_PAD_RIGHT);
                                $F353_ID_SUCURSAL = '001';
                                $F353_ID_TIPO_DOCTO_CRUCE = str_pad($docucruce,3, " ", STR_PAD_RIGHT);
                                $F353_CONSEC_DOCTO_CRUCE = str_pad($data->IdOperacion+10000000,8,"0",STR_PAD_LEFT);
                                $F353_NRO_CUOTA_CRUCE = str_pad($cuota,3, "0", STR_PAD_LEFT);
                                $cuota++;
                                $F353_FECHA_VCTO = date_format(new DateTime($data->FecVencimiento),'Ymd');
                                $F353_FECHA_DSCTO_PP = date_format(new DateTime($data->FecVencimiento),'Ymd');
                                $F353_VLR_DSCTO_PP = '+000000000000000.0000';
                                $F354_VALOR_APLICADO_PP = '+000000000000000.0000';
                                $F354_VALOR_APLICADO_PP_ALT = '+000000000000000.0000';
                                $F354_VALOR_APROVECHA = '+000000000000000.0000';
                                $F354_VALOR_APROVECHA_ALT = '+000000000000000.0000';
                                $F354_VALOR_RETENCION = '+000000000000000.0000';
                                $F354_VALOR_RETENCION_ALT = '+000000000000000.0000';
                                if(preg_match("/-/",$data->vendedor)){
                                    $F354_TERCERO_VEND =  substr($data->vendedor, 0, -2);
                                }else{
                                    $F354_TERCERO_VEND =  $data->vendedor;
                                }
    
                                $F354_TERCERO_VEND = str_pad($F354_TERCERO_VEND,15, " ", STR_PAD_RIGHT);
                                $F354_NOTAS = str_pad($Recla.$data->Nota,255," ",STR_PAD_RIGHT);
    
                                // if($cuota > 99){
                                //     $cuota = 0;
                                //     $docucruce = 'CC1';
                                // }
    
                                $Linea2 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.
                                $F350_ID_CO.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.$F351_ID_UN.
                                $F351_ID_CCOSTO.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_NOTAS.$F353_ID_SUCURSAL.
                                $F353_ID_TIPO_DOCTO_CRUCE.$F353_CONSEC_DOCTO_CRUCE.$F353_NRO_CUOTA_CRUCE.$F353_FECHA_VCTO.$F353_FECHA_DSCTO_PP.
                                $F353_VLR_DSCTO_PP.$F354_VALOR_APLICADO_PP.$F354_VALOR_APLICADO_PP_ALT.$F354_VALOR_APROVECHA.$F354_VALOR_APROVECHA_ALT.
                                $F354_VALOR_RETENCION.$F354_VALOR_RETENCION_ALT.$F354_TERCERO_VEND.$F354_NOTAS."</Linea>\r\n";
                            }
                        }
                    }
                    $parameters .= $Linea2;

                    //MOVIMIENTO CxP
                    $Linea3 = '';
                    $cuotaCxP=0;
                    foreach ($busquedaOperacion as $key => $data) {
                        if($numero == $data->IdOperacion){
                            if($data->Tipo == 'P'){
                                $a++;
                                $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                                $F_TIPO_REG = '0351';
                                $F_SUBTIPO_REG = '02';
                                $F_VERSION_REG = '03';
                                $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                                $F350_ID_CO = '001';
                                $F350_ID_TIPO_DOCTO = $data->TipoDocumento;
                            
                                $F350_CONSEC_DOCTO = str_pad($data->IdOperacion+10000000,8,"0",STR_PAD_LEFT);
                                $F351_ID_AUXILIAR = str_pad($data->cuenta,20, " ", STR_PAD_RIGHT);
                                
                                if(preg_match("/-/",$data->IdCliente)){
                                    $F351_ID_TERCERO =  substr($data->IdCliente, 0, -2);
                                }else{
                                    $F351_ID_TERCERO =  $data->IdCliente;
                                }
        
                                $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                                $F351_ID_CO_MOV = '001';
                                $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);
                                $F351_ID_CCOSTO = str_pad('',15, " ", STR_PAD_RIGHT);
        
                                if($data->Debito != 0){
                                    $F351_VALOR_DB_SIGNO = '+';
                                    $F351_VALOR_DB_VALOR = str_pad($data->Debito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;
                                }else{
                                    $F351_VALOR_DB = '+000000000000000.0000';
                                }
        
                                if($data->Credito != 0){
                                    $F351_VALOR_CR_SIGNO = '+';
                                    $F351_VALOR_CR_VALOR = str_pad($data->Credito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR;
                                }else{
                                    $F351_VALOR_CR = '+000000000000000.0000';
                                }
        
                                $F351_VALOR_DB_ALT = '+000000000000000.0000';
                                $F351_VALOR_CR_ALT = '+000000000000000.0000';
        
                                $F351_NOTAS = str_pad($Recla.$data->Nota,255," ",STR_PAD_RIGHT);
                                $F353_ID_SUCURSAL = '001';
        
                                $F353_PREFIJO_CRUCE = str_pad($data->TipoDocumento,20," ",STR_PAD_RIGHT);
                                $F353_CONSEC_DOCTO_CRUCE = str_pad($data->IdOperacion+10000000,8,"0",STR_PAD_LEFT);
                                $F353_NRO_CUOTA_CRUCE = str_pad($cuotaCxP,3, "0", STR_PAD_LEFT);
                                $cuotaCxP++;
        
                                // if($cuotaCxP > 99){
                                //     $cuotaCxP = 0;
                                // }
        
                                $F353_ID_FE = str_pad('',10," ",STR_PAD_RIGHT);
                                $fechaOperacion = new DateTime($fechaOperacion);
                                $F353_FECHA_VCTO = date_format($fechaOperacion,'Ymd');
                                $F353_FECHA_DSCTO_PP = date_format($fechaOperacion,'Ymd');
                                $F353_FECHA_DOCTO_CRUCE = date_format($fechaOperacion,'Ymd');
        
                                $F353_VLR_DSCTO_PP = '+000000000000000.0000';
                                $F354_VALOR_APLICADO_PP = '+000000000000000.0000';
                                $F354_VALOR_APLICADO_PP_ALT = '+000000000000000.0000';
                                $F354_VALOR_RETENCION = '+000000000000000.0000';
                                $F354_VALOR_RETENCION_ALT = '+000000000000000.0000';
                                $F354_NOTAS = str_pad($Recla.$data->Nota,255," ",STR_PAD_RIGHT);
        
                                $Linea3 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.
                                $F350_ID_CO.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.
                                $F351_ID_UN.$F351_ID_CCOSTO.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_NOTAS.
                                $F353_ID_SUCURSAL.$F353_PREFIJO_CRUCE.$F353_CONSEC_DOCTO_CRUCE.$F353_NRO_CUOTA_CRUCE.$F353_ID_FE.
                                $F353_FECHA_VCTO.$F353_FECHA_DSCTO_PP.$F353_FECHA_DOCTO_CRUCE.$F353_VLR_DSCTO_PP.$F354_VALOR_APLICADO_PP.
                                $F354_VALOR_APLICADO_PP_ALT.$F354_VALOR_RETENCION.$F354_VALOR_RETENCION_ALT.$F354_NOTAS."</Linea>\r\n";
                            }
                        }
                    }
                    $parameters .= $Linea3;
    
                    $a++;
                    $finlinea = str_pad($a,7, "0", STR_PAD_LEFT);
                    $fin = "<Linea>".$finlinea."99990001007</Linea>\r\n";
    
                    $parameters .= $fin;

                    $parameters .= "</Datos>\r\n</Importar>";

                    $response = $this->procesarPeticionSiesa($parameters);
                    // $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";  
    
                    // $result = $client->call('ImportarXML',$parameters);
                    // $Resultado = $result['printTipoError'];
                    // if($Resultado == '1') {
                    //     $response .= 'Lo Sentimos,  Se presentaron Errores en el proceso.';
                    //     $errores = $result['ImportarXMLResult']['diffgram']['NewDataSet']['Table'];
                    //     if (!empty($errores)){
                    //         $f_detalle = $f_valor = $f_nro_linea = ''; 
                    //         foreach ($errores as $rows) {
                    //             if (!empty($rows['f_detalle'])) {
                    //                 $f_detalle = $rows['f_detalle'];
                    //                 $f_valor = $rows['f_valor'];
                    //                 $f_nro_linea = $rows['f_nro_linea'];	

                    //                 $response .= $f_nro_linea."\n";
                    //                 $response .= $f_valor."\n";
                    //                 $response .= utf8_encode($f_detalle)."\n";
                    //             }	
                    //         }
                    //         $errores = $result['ImportarXMLResult']['diffgram']['NewDataSet'];
                    //         foreach ($errores as $rows){
                    //             if (!empty($rows['f_detalle'])){
                    //                 $f_detalle = $rows['f_detalle'];
                    //                 $f_valor = $rows['f_valor'];
                    //                 $f_nro_linea = $rows['f_nro_linea'];	
                                    
                    //                 $response .= $f_nro_linea."\n"; 
                    //                 $response .= $f_valor."\n"; 
                    //                 $response .= utf8_encode($f_detalle)."\n"; 
                    //             }	
                    //         }
                    //     }
                    // }else{
                    //     $response = 'ok';
                    // }
                }else{
                    $response = ['error' => 'Falla en la consulta de la operación'];        
                }
            }
        }else{
            $response = ['error' => 'No existe la operación en la base de datos'];
            return response()->json($response);
        }
    }
    /**mostrar datos documento operaciones */
    public function documentoOperacionesFiltros(Request $request){
        $fechaInicial = ($request->get('fechaInicial') != '')? $request->get('fechaInicial') : date('Y-m-d');
        $fechaFinal = ($request->get('fechaFinal'))? $request->get('fechaFinal') : date('Y-m-d');
        $tabla = ''; 
        $boton = ''; 
        $idOperaciones = '';
        $datosOperaciones = Contable::datosOperaciones($fechaInicial,$fechaFinal/*'2019-03-19T00:00:00','2022-03-19T00:00:00'*/);
        if($datosOperaciones != 0){
            foreach ($datosOperaciones as $data){
                $tabla .= '<tr>
                                <td class="text-center">'.$data->tipdocumento.'</td>
                                <td class="text-center">'.$data->IdOperacion.'</td>
                                <td class="text-center">
                                    <button data-toggle="tooltip" title="Enviar Operacion '.$data->IdOperacion.'" style="padding: 3px;font-size: 10px;line-height: 0;" class="btn btn-primary" onclick="EnviarOperacion(`'.$data->IdOperacion.'`)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </td>
                            </tr>';
                if($data->IdOperacion != $data->siesa){
                    $idOperaciones .= $data->IdOperacion.',';
                }
            }
            if($idOperaciones != ""){
                $idOperaciones = substr($idOperaciones,0,-1);
            }
            $boton .= '<div class="row">
                        <div class="col-md-6 col-xs-12 text-center mt-2 p-2">
                            <button class="btn btn-primary" id="btn_enviar" data-toggle="tooltip" title="Enviar Todas Las Facturas"
                            onclick="EnviarTodos(`'.$idOperaciones.'`)" >
                                <i class="far fa-paper-plane"></i>&nbsp;&nbsp;Enviar Todos
                            </button>
                        </div>
                    </div>';
        }
        return response()->json(['tabla'=>$tabla,'boton'=>$boton]);
    }
    /**Envio siesa documentos operaciones */
    public function documentoOperacionesEnvioSiesa(Request $request){
        $idOperacion = $_POST['idOperacion'];
        require_once('nusoap.php');
        // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
        // $client->soap_defencoding = 'UTF-8';
        // $client->decode_utf8 = true;
        $numero = 0;
        $busquedaOperacionId = Contable::busquedaOperacionId($idOperacion/*'2167'*/);

        if($busquedaOperacionId != 0){
            foreach ($busquedaOperacionId as $data) {
                if($numero != $data->IdOperacion){
                    $numero = $data->IdOperacion;
                    $Linea = '';
                    $CO_PRINCIPAL = '001';
                    $F_NUMERO_REG = '0000002';
                    $F_TIPO_REG = '0350';
                    $F_SUBTIPO_REG = '00';
                    $F_VERSION_REG = '01';
                    $F_CIA = '007';  // COMPAÑIA 007 (ARAR Financiera)
                    $F_CONSEC_AUTO_REG = '1';
                    $F350_ID_CLASE_DOCTO = '00030';
                    $F350_IND_IMPRESION = '0'; // Estado de impresión del documento

                    // Prueba
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n";*/
            
                    // Real
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n";*/
                    $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";
                    
                    $cabecera = "<Linea>000000100000001".$F_CIA."</Linea>\r\n";
                    $parameters .= $cabecera;

                    foreach ($busquedaOperacionId as $key => $data) {
                        if($numero == $data->IdOperacion){
                        $F350_ID_TIPO_DOCTO = $data->TipoDocumento;
                        $F350_CONSEC_DOCTO = str_pad($data->IdOperacion,8,"0",STR_PAD_LEFT);
                        $F350_FECHA = date_format(new DateTime($data->FecOperacion),'Ymd');
                        
                        if(preg_match("/-/",$data->IdCliente)){
                            $F350_ID_TERCERO =  substr($data->IdCliente, 0, -2);
                        }else{
                            $F350_ID_TERCERO =  $data->IdCliente;
                        }

                        $F350_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                        $F350_IND_ESTADO = 1;
                        $F350_NOTAS = str_pad($data->Nota,255," ",STR_PAD_RIGHT);
                        }
                    }

                    $Linea .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_CONSEC_AUTO_REG.
                    $CO_PRINCIPAL.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F350_FECHA.$F350_ID_TERCERO.$F350_ID_CLASE_DOCTO.$F350_IND_ESTADO.$F350_IND_IMPRESION.$F350_NOTAS."</Linea>\r\n";
        
                    $parameters .= $Linea;

                    $a = 2;

                    //MOVIMIENTO CONTABLE
                    $Linea1 = '';
                    foreach ($busquedaOperacionId as $data) {
                        if($numero == $data->IdOperacion){
                            if($data->Tipo == 'M'){
                                $a++;

                                $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                                $F_TIPO_REG = '0351';
                                $F_SUBTIPO_REG = '00';
                                $F_VERSION_REG = '02';
                                $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                                $F350_ID_CO = '001';
                                $F350_ID_TIPO_DOCTO = $data->TipoDocumento;
                                $F350_CONSEC_DOCTO = str_pad($data->IdOperacion,8,"0",STR_PAD_LEFT);
                                $F351_ID_AUXILIAR = str_pad($data->cuenta,20, " ", STR_PAD_RIGHT);
                                if(preg_match("/-/",$data->IdCliente)){
                                    $F350_ID_TERCERO =  substr($data->IdCliente, 0, -2);
                                }else{
                                    $F350_ID_TERCERO =  $data->IdCliente;
                                }
                                $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15, " ", STR_PAD_RIGHT);
                                $F351_ID_CO_MOV = '001';
                                $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);
                                $F351_ID_CCOSTO = str_pad('',15, " ", STR_PAD_RIGHT);
                                $F351_ID_FE = str_pad('',10, " ", STR_PAD_RIGHT);

                                if($data->Debito != 0){
                                    $F351_VALOR_DB_SIGNO = '+';
                                    $F351_VALOR_DB_VALOR = str_pad($data->Debito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;
                                }else{
                                    $F351_VALOR_DB = '+000000000000000.0000';
                                }

                                if($data->Credito != 0){
                                    $F351_VALOR_CR_SIGNO = '+';
                                    $F351_VALOR_CR_VALOR = str_pad($data->Credito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR;
                                }else{
                                    $F351_VALOR_CR = '+000000000000000.0000';
                                }

                                $F351_VALOR_DB_ALT = '+000000000000000.0000';
                                $F351_VALOR_CR_ALT = '+000000000000000.0000';
                                $F351_BASE_GRAVABLE	 = '+000000000000000.0000';
                                $F351_DOCTO_BANCO = str_pad('',2, " ", STR_PAD_RIGHT);
                                $F351_NRO_DOCTO_BANCO = '00000000';
                                $F351_NOTAS = str_pad($data->Nota,255," ",STR_PAD_RIGHT);
                                
                                $Linea1 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F350_ID_CO.
                                $F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.$F351_ID_UN.
                                $F351_ID_CCOSTO.$F351_ID_FE.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_BASE_GRAVABLE.
                                $F351_DOCTO_BANCO.$F351_NRO_DOCTO_BANCO.$F351_NOTAS."</Linea>\r\n";   
                            }     
                        }
                    }
                    $parameters .= $Linea1;

                    //MOVIMIENTO CxC
                    $Linea2 = '';
                    $cuota = 0;
                    $docucruce = 'OPE';
                    foreach ($busquedaOperacionId as $key => $data) {
                        if($numero == $data->IdOperacion){
                            if($data->Tipo == 'C'){
                                $a++;
                                
                                $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                                $F_TIPO_REG = '0351';
                                $F_SUBTIPO_REG = '01';
                                $F_VERSION_REG = '02';
                                $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                                $F350_ID_CO = '001';
                                $F350_ID_TIPO_DOCTO = $data->TipoDocumento;
                                $F350_CONSEC_DOCTO = str_pad($data->IdOperacion,8,"0",STR_PAD_LEFT);
                                $F351_ID_AUXILIAR = str_pad($data->cuenta,20, " ", STR_PAD_RIGHT);
                                $F350_FECHA = date_format(new DateTime($data->FecOperacion),'Ymd');
                                
                                if(preg_match("/-/",$data->IdCliente)){
                                    $F351_ID_TERCERO =  substr($data->IdCliente, 0, -2);
                                }else{
                                    $F351_ID_TERCERO =  $data->IdCliente;
                                }

                                $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                                $F351_ID_CO_MOV = '001';
                                $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);
                                $F351_ID_CCOSTO = str_pad('',15, " ", STR_PAD_RIGHT);
                                $F351_VALOR_DB_SIGNO = '+';
                                $F351_VALOR_DB_VALOR = str_pad($data->Debito,20,"0",STR_PAD_LEFT);
                                $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;

                                if($data->Credito != 0){
                                    $F351_VALOR_CR_SIGNO = '+';
                                    $F351_VALOR_CR_VALOR = str_pad($data->Credito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR;
                                }else{
                                    $F351_VALOR_CR = '+000000000000000.0000';
                                }

                                $F351_VALOR_DB_ALT = '+000000000000000.0000';
                                $F351_VALOR_CR_ALT = '+000000000000000.0000';
                                $F351_NOTAS = str_pad($data->Nota,255," ",STR_PAD_RIGHT);
                                $F353_ID_SUCURSAL = '001';
                                $F353_ID_TIPO_DOCTO_CRUCE = str_pad($docucruce,3, " ", STR_PAD_RIGHT);
                                $F353_CONSEC_DOCTO_CRUCE = str_pad($data->IdOperacion,8,"0",STR_PAD_LEFT);
                                $F353_NRO_CUOTA_CRUCE = str_pad($cuota,3, "0", STR_PAD_LEFT);
                                $cuota++;
                                $F353_FECHA_VCTO = date_format(new DateTime($data->FecVencimiento),'Ymd');
                                $F353_FECHA_DSCTO_PP = date_format(new DateTime($data->FecVencimiento),'Ymd');
                                $F353_VLR_DSCTO_PP = '+000000000000000.0000';
                                $F354_VALOR_APLICADO_PP = '+000000000000000.0000';
                                $F354_VALOR_APLICADO_PP_ALT = '+000000000000000.0000';
                                $F354_VALOR_APROVECHA = '+000000000000000.0000';
                                $F354_VALOR_APROVECHA_ALT = '+000000000000000.0000';
                                $F354_VALOR_RETENCION = '+000000000000000.0000';
                                $F354_VALOR_RETENCION_ALT = '+000000000000000.0000';

                                if(preg_match("/-/",$data->vendedor)){
                                    $F354_TERCERO_VEND =  substr($data->vendedor, 0, -2);
                                }else{
                                    $F354_TERCERO_VEND =  $data->vendedor;
                                }

                                $F354_TERCERO_VEND = str_pad($F354_TERCERO_VEND,15, " ", STR_PAD_RIGHT);
                                $F354_NOTAS = str_pad($data->Nota,255," ",STR_PAD_RIGHT);

                                // if($cuota > 99){
                                //     $cuota = 0;
                                //     $docucruce = 'CC1';
                                // }

                                $Linea2 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.
                                $F350_ID_CO.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.$F351_ID_UN.
                                $F351_ID_CCOSTO.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_NOTAS.$F353_ID_SUCURSAL.
                                $F353_ID_TIPO_DOCTO_CRUCE.$F353_CONSEC_DOCTO_CRUCE.$F353_NRO_CUOTA_CRUCE.$F353_FECHA_VCTO.$F353_FECHA_DSCTO_PP.
                                $F353_VLR_DSCTO_PP.$F354_VALOR_APLICADO_PP.$F354_VALOR_APLICADO_PP_ALT.$F354_VALOR_APROVECHA.$F354_VALOR_APROVECHA_ALT.
                                $F354_VALOR_RETENCION.$F354_VALOR_RETENCION_ALT.$F354_TERCERO_VEND.$F354_NOTAS."</Linea>\r\n";
                            }
                        }
                    }
                    $parameters .= $Linea2;
                    //MOVIMIENTO CxP
                    $Linea3 = '';
                    $cuotaCxP=0;
                    foreach ($busquedaOperacionId as $key => $data) {
                        if($numero == $data->IdOperacion){
                            if($data->Tipo == 'P'){
                                $a++;
                                $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                                $F_TIPO_REG = '0351';
                                $F_SUBTIPO_REG = '02';
                                $F_VERSION_REG = '03';
                                $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                                $F350_ID_CO = '001';
                                $F350_ID_TIPO_DOCTO = $data->TipoDocumento;
                                $F350_CONSEC_DOCTO = str_pad($data->IdOperacion,8,"0",STR_PAD_LEFT);
                                $F351_ID_AUXILIAR = str_pad($data->cuenta,20, " ", STR_PAD_RIGHT);
                                
                                if(preg_match("/-/",$data->IdCliente)){
                                    $F351_ID_TERCERO =  substr($data->IdCliente, 0, -2);
                                }else{
                                    $F351_ID_TERCERO =  $data->IdCliente;
                                }

                                $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                                $F351_ID_CO_MOV = '001';
                                $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);
                                $F351_ID_CCOSTO = str_pad('',15, " ", STR_PAD_RIGHT);

                                if($data->Debito != 0){
                                    $F351_VALOR_DB_SIGNO = '+';
                                    $F351_VALOR_DB_VALOR = str_pad($data->Debito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;
                                }else{
                                    $F351_VALOR_DB = '+000000000000000.0000';
                                }

                                if($data->Credito != 0){
                                    $F351_VALOR_CR_SIGNO = '+';
                                    $F351_VALOR_CR_VALOR = str_pad($data->Credito,20,"0",STR_PAD_LEFT);
                                    $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR;
                                }else{
                                    $F351_VALOR_CR = '+000000000000000.0000';
                                }

                                $F351_VALOR_DB_ALT = '+000000000000000.0000';
                                $F351_VALOR_CR_ALT = '+000000000000000.0000';
                                $F351_NOTAS = str_pad($data->Nota,255," ",STR_PAD_RIGHT);
                                $F353_ID_SUCURSAL = '001';
                                $F353_PREFIJO_CRUCE = str_pad($data->TipoDocumento,20," ",STR_PAD_RIGHT);
                                $F353_CONSEC_DOCTO_CRUCE = str_pad($data->IdOperacion,8,"0",STR_PAD_LEFT);
                                $F353_NRO_CUOTA_CRUCE = str_pad($cuotaCxP,3, "0", STR_PAD_LEFT);
                                $cuotaCxP++;

                                // if($cuotaCxP > 99){
                                //     $cuotaCxP = 0;
                                // }

                                $F353_ID_FE = str_pad('',10," ",STR_PAD_RIGHT);
                                $F353_FECHA_VCTO = date_format(new DateTime($data->FecOperacion),'Ymd');
                                $F353_FECHA_DSCTO_PP = date_format(new DateTime($data->FecOperacion),'Ymd');
                                $F353_FECHA_DOCTO_CRUCE = date_format(new DateTime($data->FecOperacion),'Ymd');
                                $F353_VLR_DSCTO_PP = '+000000000000000.0000';
                                $F354_VALOR_APLICADO_PP = '+000000000000000.0000';
                                $F354_VALOR_APLICADO_PP_ALT = '+000000000000000.0000';
                                $F354_VALOR_RETENCION = '+000000000000000.0000';
                                $F354_VALOR_RETENCION_ALT = '+000000000000000.0000';
                                $F354_NOTAS = str_pad($data->Nota,255," ",STR_PAD_RIGHT);

                                $Linea3 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.
                                $F350_ID_CO.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.
                                $F351_ID_UN.$F351_ID_CCOSTO.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_NOTAS.
                                $F353_ID_SUCURSAL.$F353_PREFIJO_CRUCE.$F353_CONSEC_DOCTO_CRUCE.$F353_NRO_CUOTA_CRUCE.$F353_ID_FE.
                                $F353_FECHA_VCTO.$F353_FECHA_DSCTO_PP.$F353_FECHA_DOCTO_CRUCE.$F353_VLR_DSCTO_PP.$F354_VALOR_APLICADO_PP.
                                $F354_VALOR_APLICADO_PP_ALT.$F354_VALOR_RETENCION.$F354_VALOR_RETENCION_ALT.$F354_NOTAS."</Linea>\r\n";
                            }
                        }
                    }
                    $parameters .= $Linea3;
                    $a++;
                    $finlinea = str_pad($a,7, "0", STR_PAD_LEFT);
                    $fin = "<Linea>".$finlinea."99990001007</Linea>\r\n";
                    $parameters .= $fin;

                    $parameters .= "</Datos>\r\n</Importar>";

                    $response = $this->procesarPeticionSiesa($parameters);

                    // print_r($parameters);
                    // exit;

                    // $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";  
                    // $result = $client->call('ImportarXML',$parameters);
                    // $Resultado = $result['printTipoError'];
                    // if($Resultado == '1') {
                    //     $response = '';
                    //     $response .= 'Lo Sentimos,  Se presentaron Errores en el proceso.';
                    //     $return =  $result['ImportarXMLResult']['schema']['element']['complexType'];
                    //     $errores = $result['ImportarXMLResult']['diffgram']['NewDataSet']['Table'];
                    //     if (!empty($errores)){
                    //         $f_detalle = $f_valor = $f_nro_linea = ''; 
                    //         foreach ($errores as $rows) {
                    //             if (!empty($rows['f_detalle'])) {
                    //                 $f_detalle = $rows['f_detalle'];
                    //                 $f_valor = $rows['f_valor'];
                    //                 $f_nro_linea = $rows['f_nro_linea'];	

                    //                 $response .= $f_nro_linea."\n";
                    //                 $response .= $f_valor."\n";
                    //                 $response .= utf8_encode($f_detalle)."\n";
                    //             }	
                    //         }
                    //         $errores = $result['ImportarXMLResult']['diffgram']['NewDataSet'];
                    //         foreach ($errores as $rows){
                    //             if (!empty($rows['f_detalle'])){
                    //                 $f_detalle = $rows['f_detalle'];
                    //                 $f_valor = $rows['f_valor'];
                    //                 $f_nro_linea = $rows['f_nro_linea'];	
                                    
                    //                 $response .= $f_nro_linea."\n"; 
                    //                 $response .= $f_valor."\n"; 
                    //                 $response .= utf8_encode($f_detalle)."\n"; 
                    //             }	
                    //         }
                    //         return response()->json($response);
                    //     }
                    // }else{
                    //     $response = 'ok';
                    // }
                }
            }
            return response()->json($response);
        }
    }
    /**Generar archivo de cupones */
    public function generarCuponesArchivo(Request $request){
        switch($request->get('Action')){
            case 'AddCupones':
                $datosUsuario = Admin::perfilUsuario();
                $UsuarioRegistro = $datosUsuario[0]->documentoUsuario;
                $Detail_Cupones = json_decode($request->get('Detail'), true);
    
                if(!empty($Detail_Cupones)){
                    $addEncabezado = Contable::addCabeceraCupones($UsuarioRegistro);
                    foreach($Detail_Cupones as $key => $data){
                        $fecha = date('Y-m-d H:i:s');
                        $fechaCreacion = explode(' ',$fecha)[0].'T'.explode(' ',$fecha)[1];
                        $add_detalle = Contable::addDetalleCupones($addEncabezado, $data['Consecutivo'], $data['IdOperacion'], $data['IdCliente'], str_replace(",","", $data['ValorCupon']), $data['FechaLimiteCupon'], $UsuarioRegistro,$fechaCreacion);
                        if($add_detalle == 0){
                            $rollback = Contable::deleteCabeceraAndDetalleCupones($addEncabezado);
                            return response()->json(['res' => 'bad', 'title' => 'Lo siento!', 'text' => 'No se ha podido generar el archivo de cupones, por favor refresque e intente nuevamente...']);
                        }else{
                            //generar archivo de cupones
                        }
                    }
                    return response()->json(['res' => 'ok', 'id_cupon' => $addEncabezado, 'title' => 'Excelente!', 'text' => 'Se ha creado los cupones satisfactoriamente...']);
                }
                // End  Add Operaciones
            break;
            case 'SearchOperacion':
                $searchOperacion = Contable::getOperacionById($request->get('IdOperacion'));
                if(!empty($searchOperacion)){
                    $searchOperacion = $searchOperacion[0];
                    return response()->json(['res' => 'ok', 'title' => 'Excelente!', 'text' => 'Se ha encontrado la operación.', 'datos' => $searchOperacion]);
                }else{
                    return response()->json(['res' => 'bad', 'title' => 'Lo siento!', 'text' => 'No se ha encontrado la operación que desea. Verifique e intente nuevamente.']);
                }
            break;
        }
    }

    public function procesarPeticionSiesa ($xml) {
        $url = 'http://172.28.254.19/WSUNOEE/WSUNOEE.asmx';

		$soapEnvelope = '<?xml version="1.0" encoding="utf-8"?>
    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                  xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
      <soap:Body>
        <ImportarXML xmlns="http://tempuri.org/">
          <pvstrDatos><![CDATA[' . $xml . ']]></pvstrDatos>
          <printTipoError>1</printTipoError>
        </ImportarXML>
      </soap:Body>
    </soap:Envelope>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $soapEnvelope);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: text/xml; charset=utf-8',
			'SOAPAction: "http://tempuri.org/ImportarXML"',
			'Content-Length: ' . strlen($soapEnvelope)
		));
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		// 1. Verificar errores de red antes de parsear
		if ($curlError || $httpCode !== 200) {
			echo json_encode([
				'error' => 'Error de conexión: ' . ($curlError ?: "HTTP $httpCode")
			]);
			exit;
		}

		// 2. Parsear SOAP response        
        $dom = new DOMDocument();
        $dom->loadXML($response);
        $xpath = new DOMXPath($dom);

        // Registrar los namespaces necesarios
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Buscar todos los nodos Table dentro del diffgram
        $tablas = $xpath->query('//diffgr:diffgram/NewDataSet/Table');

        $errores = [];

        foreach ($tablas as $tabla) {
            $error = [];
            
            // Extraer cada campo del error
            $campos = ['f_nro_linea', 'f_tipo_reg', 'f_subtipo_reg', 'f_version', 
                    'f_nivel', 'f_valor', 'f_detalle'];
            
            foreach ($campos as $campo) {
                $nodo = $xpath->query($campo, $tabla)->item(0);
                $error[$campo] = $nodo ? $nodo->nodeValue : null;
            }
            
            $errores[] = $error;
        }

        if(!empty($errores)){
            return $errores;
        }else{
            return 'ok';
        }
        // return $errores;
		// $soapBody = $xmlResponse->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;
		// $importResp = $soapBody->children('http://tempuri.org/')->ImportarXMLResponse;
		// $Resultado = (string) $importResp->printTipoError;

        // if ($Resultado == '1') {
        //     $innerXmlStr = (string) $importResp->ImportarXMLResult;
            
        //     // Limpiar namespaces problemáticos antes de parsear
        //     $innerXmlStr = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $innerXmlStr);
        //     $innerXmlStr = preg_replace('/\s+/', ' ', $innerXmlStr);

        //     $innerXmlStr = (string) $importResp->ImportarXMLResult;
            
        //     $innerXml = simplexml_load_string($innerXmlStr);
            
        //     if ($innerXml === false) {
        //         return ['error' => 'No se pudo parsear la respuesta del webservice'];
        //     }

        //     $errores = [];

        //     // Usar registro sin namespace
        //     $tablas = $innerXml->xpath('//Table');

        //     if (empty($tablas)) {
        //         // Intentar con diffgram
        //         $tablas = $innerXml->xpath('//*[local-name()="Table"]');
        //     }

        //     foreach ($tablas as $row) {
        //         $f_detalle   = (string) $row->f_detalle;
        //         $f_valor     = (string) $row->f_valor;
        //         $f_nro_linea = (string) $row->f_nro_linea;

        //         if (!empty($f_detalle)) {
        //             $errores[] = [
        //                 'nro_linea' => $f_nro_linea,
        //                 'valor'     => $f_valor,
        //                 'detalle'   => mb_convert_encoding(trim($f_detalle), 'UTF-8', 'ISO-8859-1'),
        //             ];
        //         }
        //     }

            

        // } else {
        //     return 'ok';
        // }
    }

    public function eliminarAcentos($texto) {
        // Array de caracteres con acentos
        $acentos = array(
            'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð',
            'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã',
            'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ',
            'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ'
        );
        
        // Array de caracteres sin acentos
        $sinAcentos = array(
            'A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D',
            'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a',
            'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o',
            'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y'
        );
        
        return str_replace($acentos, $sinAcentos, $texto);
    }
}
