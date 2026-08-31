<?php

namespace App\Http\Controllers;

use App\Models\Terceros;
use Illuminate\Http\Request;
include 'nusoap.php';
use nusoap_client;

class TerceroSiesaController extends Controller
{
    public function creacionTerceroSiesa(Request $request){
        $cliente = $request->get('cliente');
        if(preg_match("/-/",$cliente)){
            $cliente1 = substr($cliente,0,-2);
        }else{
            $cliente1 = $request->get('cliente');
        }
        
        $LoadClientes = Terceros::LoadClientes($cliente);
        $ValidarTerceros = Terceros::ValidarTerceros($cliente1);
        
        if(count($ValidarTerceros) == 0){
            if(count($LoadClientes) > 0){
                foreach($LoadClientes as $key => $data){
                    if(preg_match("/-/",$data->IdCliente)){
                        $documento = substr($data->IdCliente,0,-2);
                    }else{
                        $documento = $data->IdCliente;
                    }
                    if($data->ApeCliente != ''){
                        if(preg_match("/ /",$data->ApeCliente)){
                            $apellidos = explode(' ',$data->ApeCliente);
                            $apellido1 = $apellidos[0];
                            $apellido2 = $apellidos[1];
                        }
                        $apellido1 = $data->ApeCliente;
                        $apellido2 = ' ';
                    }else{
                        $apellido1 = ' ';
                        $apellido2 = ' ';
                    }

                    $nombres = $data->NomCliente;
                    $razon_social = $nombres." ".$apellido1." ".$apellido2;
                    $direccion = $data->Direccion;
                    $pais = $data->IdPais;
                    $dpto = $data->IdDepartamento;
                    $ciudad = $data->IdCiudad;
                    $telefono = $data->Telefono;
                    $celular = $data->Celular;
                    $email = $data->Email;
                    $diacreacion = date('Ymd');

                    $F_NUMERO_REG = '0000002';
                    $F_TIPO_REG = '0200'; 
                    $F_SUBTIPO_REG = '00';
                    $F_VERSION_REG = '08';
                    $F_CIA = '007';
                    $F_ACTUALIZA_REG = '1';
            
                    // require_once('nusoap.php');
                    // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
                    // $client->soap_defencoding = 'UTF-8';
                    // $client->decode_utf8 = true;
                
                    //Real
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */
                    $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";
                    
                    //Prueba
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */
                    
                    $cabecera = "<Linea>000000100000001007</Linea>\r\n";
                    $parameters .= $cabecera;
            
                    $Codigo_Tercero = str_pad($documento,15, " ", STR_PAD_RIGHT);
                    $Codigo_Tercero1 = str_pad($documento,25, " ", STR_PAD_RIGHT);
                    $Digito_Verificacion = str_pad('0',3, " ", STR_PAD_RIGHT);
                    $Tipo_Identificacion = str_pad('C',1, " ", STR_PAD_RIGHT);
                    $Tipo_Tercero = str_pad('1',1, " ", STR_PAD_RIGHT);
                    $Razon_Social = str_pad($razon_social,100, " ", STR_PAD_RIGHT);
                    $Apellido1 = str_pad($apellido1,29, " ", STR_PAD_RIGHT);
                    $Apellido2 = str_pad($apellido2,29, " ", STR_PAD_RIGHT);
                    $Nombres = str_pad($nombres,40, " ", STR_PAD_RIGHT);
                    $Nombre_Establecimiento = str_pad('',50, " ", STR_PAD_RIGHT);
                    $Es_Cliente = str_pad('1',1, " ", STR_PAD_RIGHT);
                    $Es_Tercero = str_pad('0',1, " ", STR_PAD_RIGHT);
                    $Es_Empleado = str_pad('0',1, " ", STR_PAD_RIGHT);
                    $Es_Accionista = str_pad('0',1, " ", STR_PAD_RIGHT);
                    $Es_Otro = str_pad('0',1, " ", STR_PAD_RIGHT);
                    $Es_Interno = str_pad('0',1, " ", STR_PAD_RIGHT);
                    $Contacto = str_pad($nombres,50, " ", STR_PAD_RIGHT);
                    $Direccion = str_pad($direccion,40, " ", STR_PAD_RIGHT);
                    $Direccion1 = str_pad('',40, " ", STR_PAD_RIGHT);
                    $Direccion2 = str_pad('',40, " ", STR_PAD_RIGHT);
                    $Codigo_Pais = str_pad($pais,3, " ", STR_PAD_RIGHT);
                    $Codigo_Dpto = str_pad($dpto,2, " ", STR_PAD_RIGHT);
                    $Codigo_Ciudad = str_pad($ciudad,3, " ", STR_PAD_RIGHT);
                    $Codigo_Barrio = str_pad('',40, " ", STR_PAD_RIGHT);
                    $Telefono = str_pad($telefono,20, " ", STR_PAD_RIGHT);
                    $Fax = str_pad('',20, " ", STR_PAD_RIGHT);
                    $Codigo_Postal = str_pad('',10, " ", STR_PAD_RIGHT);
                    $Email = str_pad($email,255, " ", STR_PAD_RIGHT);
                    $Fecha_Nacimiento = str_pad($diacreacion,8, " ", STR_PAD_RIGHT);
                    $Codigo_Act_Economica = str_pad('',4, " ", STR_PAD_RIGHT);
                    $Indicador_Domicilio = str_pad('0',1, " ", STR_PAD_RIGHT);
                    $Indicador_Estado = str_pad('1',1, " ", STR_PAD_RIGHT);
                    $Celular = str_pad($celular,50, " ", STR_PAD_RIGHT);
            
                    $Linea = "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_ACTUALIZA_REG.$Codigo_Tercero.
                    $Codigo_Tercero1.$Digito_Verificacion.$Tipo_Identificacion.$Tipo_Tercero.$Razon_Social.$Apellido1.$Apellido2.
                    $Nombres.$Nombre_Establecimiento.$Es_Cliente.$Es_Tercero.$Es_Empleado.$Es_Accionista.$Es_Otro.$Es_Interno.$Contacto.$Direccion.
                    $Direccion1.$Direccion2.$Codigo_Pais.$Codigo_Dpto.$Codigo_Ciudad.$Codigo_Barrio.$Telefono.$Fax.$Codigo_Postal.$Email.
                    $Fecha_Nacimiento.$Codigo_Act_Economica.$Indicador_Domicilio.$Indicador_Estado.$Celular."</Linea>\r\n";
            
                    $parameters .= $Linea;
                    $Fin = "<Linea>000000399990001007</Linea>\r\n";
                    $parameters .= $Fin;
                    //$parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";
                    $parameters .= "</Datos>\r\n</Importar>";

                    $response = $this->procesarPeticionSiesa($parameters);

                    // $result = $client->call('ImportarXML',$parameters);     
                    // $Resultado = $result['printTipoError'];
                    // if($Resultado == '1') {
                    //     $response = '';
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
                    return response()->json($response);
                }
            }
        }else{
            return response()->json('Existe Tercero');
        }
    }
//la funcion creacionClienteSiesa necesito que cuando reciba el $apellido1, $apellido2 cambie las ñ por n, Ñ por N
    public function creacionClienteSiesa(Request $request){
        $cliente = $request->get('cliente');
        if(preg_match("/-/",$cliente)){
            $cliente1 = substr($cliente,0,-2);
        }else{
            $cliente1 = $request->get('cliente');
        }

        $LoadClientes = Terceros::LoadClientes($cliente);
        $ValidarCliente = Terceros::ValidarCliente($cliente1);

        if(count($ValidarCliente) == 0){
            if(count($LoadClientes) > 0){
                foreach ($LoadClientes as $key => $data){
                    if(preg_match("/-/",$data->IdCliente)){
                        $documento = substr($data->IdCliente,0,-2);
                    }else{
                        $documento = $data->IdCliente;
                    }

                    if($data->ApeCliente != ''){
                        if(preg_match("/ /",$data->ApeCliente)){
                            $apellidos = explode(' ',$data->ApeCliente);
                            $apellido1 = str_replace(['ñ', 'Ñ'], ['n', 'N'], $apellidos[0]);
                            $apellido2 = str_replace(['ñ', 'Ñ'], ['n', 'N'], $apellidos[1]);
                        }
                        $apellido1 = $data->ApeCliente;
                        $apellido2 = ' ';
                    }else{
                        $apellido1 = ' ';
                        $apellido2 = ' ';
                    }
                    $nombres = $data->NomCliente;
                    $razon_social = $nombres." ".$apellido1." ".$apellido2;
                    $direccion = $data->Direccion;
                    $pais = $data->IdPais;
                    $dpto = $data->IdDepartamento;
                    $ciudad = $data->IdCiudad;
                    $telefono = $data->Telefono;
                    $celular = $data->Celular;
                    $email = $data->Email;
                    $diacreacion = date('Ymd');

                    $F_NUMERO_REG = '0000002';
                    $F_TIPO_REG = '0201'; 
                    $F_SUBTIPO_REG = '00';
                    $F_VERSION_REG = '09';
                    $F_CIA = '007';
                    $F_ACTUALIZA_REG = '0';

                    // require_once('nusoap.php');
                    // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
                    // $client->soap_defencoding = 'UTF-8';
                    // $client->decode_utf8 = true;
                    //real
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */
                    $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";
                
                    //prueba
                    /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */
                    $cabecera = "<Linea>000000100000001007</Linea>\r\n";
                    $parameters .= $cabecera;

                    $Codigo_Tercero = str_pad($documento,15, " ", STR_PAD_RIGHT);
                    $Codigo_Sucursal = str_pad('001',3, " ", STR_PAD_RIGHT);
                    $Estado_Activo = str_pad('1',1, " ", STR_PAD_RIGHT);
                    $Razon_Social = str_pad($razon_social,40, " ", STR_PAD_RIGHT);
                    $Moneda = str_pad('COP',3, " ", STR_PAD_RIGHT);
                    $Codigo_Vendedor = str_pad('VEN1',4, " ", STR_PAD_RIGHT);
                    $Ind_Clasificacion = str_pad('A',1, " ", STR_PAD_RIGHT);
                    $Condicion_Pago = str_pad('C00',3, " ", STR_PAD_RIGHT);
                    $Dias_Gracias = str_pad('000',3, " ", STR_PAD_RIGHT);
                    $Cupo_Credito = str_pad('000000000000000.0000',21, " ", STR_PAD_RIGHT);
                    $Codigo_cliente_Corpo = str_pad('',15, " ", STR_PAD_RIGHT);
                    $Codigo_Sucursal_Copo = str_pad('',3, " ", STR_PAD_RIGHT);
                    $Tipo_Cliente = str_pad('001',4, " ", STR_PAD_RIGHT);
                    $Grupo_Descuento = str_pad('',4, " ", STR_PAD_RIGHT);
                    $Lista_Precio = str_pad('001',3, " ", STR_PAD_RIGHT);
                    $Ind_Pedido_BackOrder = str_pad('1',1, " ", STR_PAD_RIGHT);
                    $Porc_Exceso_Venta = str_pad('0000.00',7, " ", STR_PAD_RIGHT);
                    $Porc_Min_Margen = str_pad('0',7, " ", STR_PAD_LEFT);
                    $Porc_Max_Margen = str_pad('100',7, " ", STR_PAD_LEFT);
                    $Indicador_Bloqueado = str_pad('1',1, " ", STR_PAD_RIGHT);
                    $BloqueCupo = str_pad('0',1, " ", STR_PAD_RIGHT);
                    $Bloqueo_Mora = str_pad('0',1, " ", STR_PAD_RIGHT);
                    $Indicador_Factura = str_pad('0',1, " ", STR_PAD_RIGHT);
                    $Co_Defecto_Facturacion = str_pad('',3, " ", STR_PAD_RIGHT);
                    $Observacion = str_pad('',255, " ", STR_PAD_RIGHT);
                    $Contacto = str_pad($nombres,50, " ", STR_PAD_RIGHT);
                    $Direccion1 = str_pad($direccion,40, " ", STR_PAD_RIGHT);
                    $Direccion2 = str_pad('',40, " ", STR_PAD_RIGHT);
                    $Direccion3 = str_pad('',40, " ", STR_PAD_RIGHT);
                    $Pais = str_pad($pais,3, " ", STR_PAD_RIGHT);
                    $Dpto = str_pad($dpto,2, " ", STR_PAD_RIGHT);
                    $Ciudad = str_pad($ciudad,3, " ", STR_PAD_RIGHT);
                    $Barrio = str_pad('',40, " ", STR_PAD_RIGHT);
                    $Telefono = str_pad($telefono,20, " ", STR_PAD_RIGHT);
                    $Fax = str_pad('',20, " ", STR_PAD_RIGHT);
                    $Codigo_Postal = str_pad('',10, " ", STR_PAD_RIGHT);
                    $Correo_Electronico = str_pad($email,255, " ", STR_PAD_RIGHT);
                    $Fecha_Ingreso = str_pad('20160601',8, " ", STR_PAD_RIGHT);
                    $Co_Operacion = str_pad('',3, " ", STR_PAD_RIGHT);
                    $Und_Negocio = str_pad('',20, " ", STR_PAD_RIGHT);
                    $Parametro_Edi = str_pad('',4, " ", STR_PAD_RIGHT);
                    $Cod_Ean = str_pad('',35, " ", STR_PAD_RIGHT);
                    $Fech_Vigencia_Cupo = str_pad("",8, " ", STR_PAD_RIGHT);
                    $Porcentaje_Tolerancia = str_pad('0000.00',7, " ", STR_PAD_RIGHT);
                    $Dia_Maximo_Facturacion = str_pad('00',2, " ", STR_PAD_RIGHT);
                    $Motivo_Bloqueo= str_pad('',3, " ", STR_PAD_RIGHT);
                    $Cod_Cobrador= str_pad('VEN1',4, " ", STR_PAD_RIGHT);
                    $Indicador_Compromiso= str_pad('0',1, " ", STR_PAD_RIGHT);
                    $Indicador_Evalua= str_pad('0',1, " ", STR_PAD_RIGHT);

                    $Linea = "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_ACTUALIZA_REG.$Codigo_Tercero.$Codigo_Sucursal.$Estado_Activo.$Razon_Social.$Moneda.$Codigo_Vendedor.$Ind_Clasificacion.
                    $Condicion_Pago.$Dias_Gracias.$Cupo_Credito.$Codigo_cliente_Corpo.$Codigo_Sucursal_Copo.$Tipo_Cliente.
                    $Grupo_Descuento.$Lista_Precio.$Ind_Pedido_BackOrder.$Porc_Exceso_Venta.$Porc_Min_Margen.$Porc_Max_Margen.
                    $Indicador_Bloqueado.$BloqueCupo.$Bloqueo_Mora.$Indicador_Factura.$Co_Defecto_Facturacion.$Observacion.
                    $Contacto.$Direccion1.$Direccion2.$Direccion3.$Pais.$Dpto.$Ciudad.$Barrio.$Telefono.$Fax.$Codigo_Postal.
                    $Correo_Electronico.$Fecha_Ingreso.$Co_Operacion.$Und_Negocio.$Parametro_Edi.$Cod_Ean.$Fech_Vigencia_Cupo.
                    $Porcentaje_Tolerancia.$Dia_Maximo_Facturacion.$Motivo_Bloqueo.$Cod_Cobrador.$Indicador_Compromiso.
                    $Indicador_Evalua."</Linea>\r\n";

                    $parameters .= $Linea;

                    $Fin = "<Linea>000000399990001007</Linea>\r\n";
                    $parameters .= $Fin;
                    // $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";
                    $parameters .= "</Datos>\r\n</Importar>";

                    $response = $this->procesarPeticionSiesa($parameters);
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
                    //     }
                    // }else{
                    //     $response = 'ok';
                    // }
                    return response()->json($response);
                }
            }
        }else{
            return response()->json('Existe Cliente');
        }
    }

    public function creacionProveedor(Request $request){
        $cliente = $request->get('cliente');
        if(preg_match("/-/",$cliente)){
            $cliente1 = substr($cliente,0,-2);
        }else{
            $cliente1 = $request->get('cliente');
        }
        $ValidarProveedor = Terceros::ValidarProveedor($cliente);
        $LoadClientes = Terceros::LoadClientes($cliente1);

        if(count($ValidarProveedor) == 0){
                if(($LoadClientes) > 0){
                    foreach ($LoadClientes as $key => $data){
                        $documento = $data->IdCliente;
                        if($data->ApeCliente != ''){
                            if(preg_match("/ /",$data->ApeCliente)){
                                $apellidos = explode(' ',$data->ApeCliente);
                                $apellido1 = $apellidos[0];
                                $apellido2 = $apellidos[1];
                            }
                            $apellido1 = $data->ApeCliente;
                            $apellido2 = ' ';
                        }else{
                            $apellido1 = ' ';
                            $apellido2 = ' ';
                        }
                        $nombres = $data->NomCliente;
                        $razon_social = $nombres." ".$apellido1." ".$apellido2;
                        $direccion = $data->Direccion;
                        $pais = $data->IdPais;
                        $dpto = $data->IdDepartamento;
                        $ciudad = $data->IdCiudad;
                        $telefono = $data->Telefono;
                        $celular = $data->Celular;
                        $email = $data->Email;

                        // require_once('nusoap.php');
                        // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
                        // $client->soap_defencoding = 'UTF-8';
                        // $client->decode_utf8 = true;

                        //Real
                        /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n";*/
                        $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";

                        //Prueba
                        /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */

                        $cabecera = "<Linea>000000100000001007</Linea>\r\n";
                        $parameters .= $cabecera;
                        
                        $F_NUMERO_REG = str_pad('2',7, "0", STR_PAD_LEFT);
                        $F_TIPO_REG = '0202';
                        $F_SUBTIPO_REG = '00';
                        $F_VERSION_REG = '03';
                        $F_CIA = '007';
                        $F_ACTUALIZA_REG = '0';

                        $F202_ID_TERCERO = str_pad($documento,15, " ", STR_PAD_RIGHT);
                        $F202_ID_SUCURSAL = '001';
                        $F202_IND_ESTADO = '1';
                        $F202_DESCRIPCION_SUCURSAL = str_pad($razon_social,40, " ", STR_PAD_RIGHT);
                        $F202_ID_MONEDA = 'COP';
                        $F202_ID_CLASE_PROVEEDOR = str_pad('PVAC',4, " ", STR_PAD_RIGHT);
                        $F202_ID_COND_PAGO = str_pad('C30',3, " ", STR_PAD_RIGHT);
                        $F202_DIAS_GRACIA = str_pad('0',3, " ", STR_PAD_RIGHT);
                        $F202_CUPO_CREDITO = '+000000000000000.0000';
                        $F202_ID_TIPO_PROV = str_pad('015',4, " ", STR_PAD_RIGHT);
                        $F202_IND_FORMA_PAGO = '1';
                        $F202_NOTAS = str_pad('',255, " ", STR_PAD_RIGHT);
                        $F015_CONTACTO = str_pad($razon_social,50, " ", STR_PAD_RIGHT);
                        $F015_DIRECCION1 = str_pad($direccion,40, " ", STR_PAD_RIGHT);
                        $F015_DIRECCION2 = str_pad('',40, " ", STR_PAD_RIGHT);
                        $F015_DIRECCION3 = str_pad('',40, " ", STR_PAD_RIGHT);
                        $F015_ID_PAIS = str_pad($pais,3, " ", STR_PAD_RIGHT);
                        $F015_ID_DEPTO = str_pad($dpto,2, " ", STR_PAD_RIGHT);
                        $F015_ID_CIUDAD = str_pad($ciudad,3, " ", STR_PAD_RIGHT);
                        $F015_ID_BARRIO = str_pad('',40, " ", STR_PAD_RIGHT);
                        $F015_TELEFONO = str_pad($celular,20, " ", STR_PAD_RIGHT);
                        $F015_FAX = str_pad('',20, " ", STR_PAD_RIGHT);
                        $F015_COD_POSTAL = str_pad('',10, " ", STR_PAD_RIGHT);
                        $F015_EMAIL = str_pad($email,50, " ", STR_PAD_RIGHT);
                        $F202_FECHA_INGRESO = '20160101';
                        $F202_PORCENTAJE_EXCESO_COMPRA = '000.00';
                        $F202_MONTO_ANUAL_COMPRA = '0000000000000.00';
                        $F202_IND_MONTO_ANUAL_COMPRA = '0';
                        $f202_IND_EXIGE_COTIZ_EN_OC = '0';
                        $f202_IND_EXIGE_OC_EN_EA = '0';

                        $Linea =  "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_ACTUALIZA_REG.$F202_ID_TERCERO.
                            $F202_ID_SUCURSAL.$F202_IND_ESTADO.$F202_DESCRIPCION_SUCURSAL.$F202_ID_MONEDA.$F202_ID_CLASE_PROVEEDOR.
                            $F202_ID_COND_PAGO.$F202_DIAS_GRACIA.$F202_CUPO_CREDITO.$F202_ID_TIPO_PROV.$F202_IND_FORMA_PAGO.$F202_NOTAS.
                            $F015_CONTACTO.$F015_DIRECCION1.$F015_DIRECCION2.$F015_DIRECCION3.$F015_ID_PAIS.$F015_ID_DEPTO.$F015_ID_CIUDAD.
                            $F015_ID_BARRIO.$F015_TELEFONO.$F015_FAX.$F015_COD_POSTAL.$F015_EMAIL.$F202_FECHA_INGRESO.$F202_PORCENTAJE_EXCESO_COMPRA.
                            $F202_MONTO_ANUAL_COMPRA.$F202_IND_MONTO_ANUAL_COMPRA.$f202_IND_EXIGE_COTIZ_EN_OC.$f202_IND_EXIGE_OC_EN_EA."</Linea>\r\n";

                        $parameters .= $Linea;

                        $Fin = "<Linea>000000399990001007</Linea>\r\n";
                        $parameters .= $Fin;
                
                        // $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";
                        $parameters .= "</Datos>\r\n</Importar>";

                        $response = $this->procesarPeticionSiesa($parameters);
                        // $result = $client->call('ImportarXML',$parameters);
                        // $Resultado = $result['printTipoError'];
                        // if($Resultado == '1') {
                        //     $response = '';
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
                        return response()->json($response);
                    }
                }
            }else{
                return response()->json('Existe Proveedor');
            }
    }

    public function creacionImpretension(Request $request){
        $cliente = $request->get('cliente');
        if(preg_match("/-/",$cliente)){
            $cliente = substr($cliente,0,-2);
        }

        if($cliente != ''){
            $documento = $cliente;

            $F_NUMERO_REG = '0000002';
            $F_TIPO_REG = '0046'; 
            $F_SUBTIPO_REG = '00';
            $F_VERSION_REG = '01';
            $F_CIA = '007';
            $F_ACTUALIZA_REG = '1';
            $F_NUMERO_REG2 = '0000003';
            $F_TIPO_REG2 = '0047'; 

            // require_once('nusoap.php');
            // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
            // $client->soap_defencoding = 'UTF-8';
            // $client->decode_utf8 = true;

            //Real
            /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */
            $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";
            
            //Prueba
            /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */
            
            $cabecera = "<Linea>000000100000001007</Linea>\r\n";
            $parameters .= $cabecera;

            $Codigo_Tercero = str_pad($documento,15, " ", STR_PAD_RIGHT);

            $Linea = "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_ACTUALIZA_REG.$Codigo_Tercero."0011  1 IV19</Linea>\r\n";
            $Linea .= "<Linea>".$F_NUMERO_REG2.$F_TIPO_REG2.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_ACTUALIZA_REG.$Codigo_Tercero."00180 1 9001</Linea>\r\n";
            $parameters .= $Linea;

            $Fin = "<Linea>000000499990001007</Linea>\r\n";
            $parameters .= $Fin;
            // $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";  
            $parameters .= "</Datos>\r\n</Importar>";

            $response = $this->procesarPeticionSiesa($parameters);

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
            //     }
            // }else{
            //     $response = 'ok';
            // }
            return response()->json($response);
        }
    }

    public function creacionImpretencionProveedor(Request $request){
        $cliente = $request->get('cliente');
        if(preg_match("/-/",$cliente)){
            $cliente = substr($cliente,0,-2);
        }

        if($cliente != ''){
            $documento = $cliente;

            $F_NUMERO_REG = '0000002';
            $F_TIPO_REG = '0049'; 
            $F_SUBTIPO_REG = '00';
            $F_VERSION_REG = '01';
            $F_CIA = '007';
            $F_ACTUALIZA_REG = '1';
            /*$F_NUMERO_REG2 = '0000003';
            $F_TIPO_REG2 = '0047'; */

            // require_once('nusoap.php');
            // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
            // $client->soap_defencoding = 'UTF-8';
            // $client->decode_utf8 = true;

            //Real
            /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */
            $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";

            //Prueba
            /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */
            

            $cabecera = "<Linea>000000100000001007</Linea>\r\n";
            $parameters .= $cabecera;

            $Codigo_Tercero = str_pad($documento,15, " ", STR_PAD_RIGHT);

            $Linea = "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_ACTUALIZA_REG.$Codigo_Tercero."0011  1 IV19</Linea>\r\n";
            //$Linea .= "<Linea>".$F_NUMERO_REG2.$F_TIPO_REG2.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_ACTUALIZA_REG.$Codigo_Tercero."00180 1 9001</Linea>\r\n";
            $parameters .= $Linea;
            $Fin = "<Linea>000000399990001007</Linea>\r\n";
            $parameters .= $Fin;
            // $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";  
            $parameters .= "</Datos>\r\n</Importar>";

            $response = $this->procesarPeticionSiesa($parameters);

            // $result = $client->call('ImportarXML',$parameters);
            // $Resultado = $result['printTipoError'];
            // if($Resultado == '1') {
            //     $response = '';
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
            return response()->json($response);
        }
    }

    public function crearPagoelecBancolombia(Request $request){
        $cliente1 = $request->get('cliente');
        if(preg_match("/-/",$cliente1)){
            $cliente = substr($cliente1,0,-2);
        }else{
            $cliente = $request->get('cliente');
        }

        $ValidarProveedor = Terceros::ValidarProveedor($cliente);
        $LoadClientes = Terceros::LoadClientes($cliente1);

        //if($ValidarProveedor == 0){
        if(count($LoadClientes) > 0){
            foreach ($LoadClientes as $key => $data){
                $documento = $data->IdCliente;
                $apellidos = explode(' ',$data->ApeCliente);
                $apellido1 = $apellidos[0];
                $apellido2 = $apellidos[1];
                $nombres = $data->NomCliente;
                $razon_social = $nombres." ".$apellido1." ".$apellido2;
                $direccion = $data->Direccion;
                $pais = $data->IdPais;
                $dpto = $data->IdDepartamento;
                $ciudad = $data->IdCiudad;
                $telefono = $data->Telefono;
                $celular = $data->Celular;
                $email = $data->Email;

                // require_once('nusoap.php');
                // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
                // $client->soap_defencoding = 'UTF-8';
                // $client->decode_utf8 = true;

                //Real
                /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n";*/
                $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";
                
                //Prueba
                /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */

                $cabecera = "<Linea>000000100000001007</Linea>\r\n";
                $parameters .= $cabecera;
                
                $F_NUMERO_REG = str_pad('2',7, "0", STR_PAD_LEFT);
                $F_TIPO_REG = '0634';
                $F_SUBTIPO_REG = '00';
                $F_VERSION_REG = '01';
                $F_CIA = '007';
                $F_ACTUALIZA_REG = '0';
                $F633_ID_TERCERO = str_pad($documento,15, " ", STR_PAD_RIGHT);
                $F633_ID_SUCURSAL = '001';
                $F633_IND_TIPO_PAGO = '1';
                $F633_ID_BANCO = str_pad('01',10, " ", STR_PAD_RIGHT);
                $F633_NUMERO_CUENTA = str_pad('1111111111',30, " ", STR_PAD_RIGHT);
                $F633_TIPO_CUENTA = '2';
                $F634_ID_FORMATO = str_pad('41',8, " ", STR_PAD_RIGHT);
                $F202_IND_FORMA_PAGO = '1';
                $F202_PE_DEFAULT = '1';
                $F634_DATO_01 = str_pad('63555656',50, " ", STR_PAD_RIGHT);
                $F634_DATO_02 = str_pad('1',50, " ", STR_PAD_RIGHT);
                $F634_DATO_03 = str_pad('000000000',50, " ", STR_PAD_RIGHT);
                $F634_DATO_04 = str_pad('1111111111',50, " ", STR_PAD_RIGHT);
                $F634_DATO_05 = str_pad('6',50, " ", STR_PAD_RIGHT);
                $F634_DATO_06 = str_pad('10',50, " ", STR_PAD_RIGHT);
                $F634_DATO_07 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_08 = str_pad('302',50, " ", STR_PAD_RIGHT);
                $F634_DATO_09 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_10 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_11 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_12 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_13 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_14 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_15 = str_pad('',50, " ", STR_PAD_RIGHT);

                $Linea =  "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_ACTUALIZA_REG.$F633_ID_TERCERO.
                $F633_ID_SUCURSAL.$F633_IND_TIPO_PAGO.$F633_ID_BANCO.$F633_NUMERO_CUENTA.$F633_TIPO_CUENTA.$F634_ID_FORMATO.
                $F202_IND_FORMA_PAGO.$F202_PE_DEFAULT.$F634_DATO_01.$F634_DATO_02.$F634_DATO_03.$F634_DATO_04.$F634_DATO_05.
                $F634_DATO_06.$F634_DATO_07.$F634_DATO_08.$F634_DATO_09.$F634_DATO_10.$F634_DATO_11.$F634_DATO_12.$F634_DATO_13.
                $F634_DATO_14.$F634_DATO_15."</Linea>\r\n";

                $parameters .= $Linea;
                $Fin = "<Linea>000000399990001007</Linea>\r\n";
                $parameters .= $Fin;
                // $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";
                $parameters .= "</Datos>\r\n</Importar>";

                $response = $this->procesarPeticionSiesa($parameters);

                // $result = $client->call('ImportarXML',$parameters);
                // $Resultado = $result['printTipoError'];
                // if($Resultado == '1') {
                //     $response = '';
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
                return response()->json($response);
            }
        }
    }

    public function crearPagoelecBancobogota(Request $request){
        $cliente1 = $request->get('cliente');
        if(preg_match("/-/",$cliente1)){
            $cliente = substr($cliente1,0,-2);
        }else{
            $cliente = $request->get('cliente');
        }

        $ValidarProveedor = Terceros::ValidarProveedor($cliente);
        $LoadClientes = Terceros::LoadClientes($cliente1);

        //if($ValidarProveedor == 0){
        if(count($LoadClientes) > 0){
            foreach ($LoadClientes as $key => $data) {
                $documento = $data->IdCliente;
                $apellidos = explode(' ',$data->ApeCliente);
                $apellido1 = $apellidos[0];
                $apellido2 = $apellidos[1];
                $nombres = $data->NomCliente;
                $razon_social = $nombres." ".$apellido1." ".$apellido2;
                $direccion = $data->Direccion;
                $pais = $data->IdPais;
                $dpto = $data->IdDepartamento;
                $ciudad = $data->IdCiudad;
                $telefono = $data->Telefono;
                $celular = $data->Celular;
                $email = $data->Email;

                // require_once('nusoap.php');
                // $client = new \nusoap_client('http://172.28.254.19/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl');
                // $client->soap_defencoding = 'UTF-8';
                // $client->decode_utf8 = true;
                $numero = 0;

                //Real
                /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n";*/
                $parameters = "<Importar>\r\n<NombreConexion>Real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>" . config('services.siesa.clave') . "</Clave>\r\n<Datos>\r\n";
                
                //Prueba
                /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".config('services.siesa.clave')."</Clave>\r\n<Datos>\r\n"; */

                $cabecera = "<Linea>000000100000001007</Linea>\r\n";
                $parameters .= $cabecera;
                
                $F_NUMERO_REG = str_pad('2',7, "0", STR_PAD_LEFT);
                $F_TIPO_REG = '0634';
                $F_SUBTIPO_REG = '00';
                $F_VERSION_REG = '01';
                $F_CIA = '007';
                $F_ACTUALIZA_REG = '0';

                $F633_ID_TERCERO = str_pad($documento,15, " ", STR_PAD_RIGHT);
                $F633_ID_SUCURSAL = '001';
                $F633_IND_TIPO_PAGO = '1';
                $F633_ID_BANCO = str_pad('01',10, " ", STR_PAD_RIGHT);
                $F633_NUMERO_CUENTA = str_pad('1111111111',30, " ", STR_PAD_RIGHT);
                $F633_TIPO_CUENTA = '2';
                $F634_ID_FORMATO = str_pad('7',8, " ", STR_PAD_RIGHT);
                $F202_IND_FORMA_PAGO = '1';
                $F202_PE_DEFAULT = '1';
                $F634_DATO_01 = str_pad('C',50, " ", STR_PAD_RIGHT);
                $F634_DATO_02 = str_pad('10000000000',50, " ", STR_PAD_RIGHT);
                $F634_DATO_03 = str_pad('1',50, " ", STR_PAD_RIGHT);
                $F634_DATO_04 = str_pad('1111111111',50, " ", STR_PAD_RIGHT);
                $F634_DATO_05 = str_pad('001',50, " ", STR_PAD_RIGHT);
                $F634_DATO_06 = str_pad('1',50, " ", STR_PAD_RIGHT);
                $F634_DATO_07 = str_pad('N',50, " ", STR_PAD_RIGHT);
                $F634_DATO_08 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_09 = str_pad('7000',50, " ", STR_PAD_RIGHT);
                $F634_DATO_10 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_11 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_12 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_13 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_14 = str_pad('',50, " ", STR_PAD_RIGHT);
                $F634_DATO_15 = str_pad('',50, " ", STR_PAD_RIGHT);

                $Linea =  "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_ACTUALIZA_REG.$F633_ID_TERCERO.
                $F633_ID_SUCURSAL.$F633_IND_TIPO_PAGO.$F633_ID_BANCO.$F633_NUMERO_CUENTA.$F633_TIPO_CUENTA.$F634_ID_FORMATO.
                $F202_IND_FORMA_PAGO.$F202_PE_DEFAULT.$F634_DATO_01.$F634_DATO_02.$F634_DATO_03.$F634_DATO_04.$F634_DATO_05.
                $F634_DATO_06.$F634_DATO_07.$F634_DATO_08.$F634_DATO_09.$F634_DATO_10.$F634_DATO_11.$F634_DATO_12.$F634_DATO_13.
                $F634_DATO_14.$F634_DATO_15."</Linea>\r\n";

                $parameters .= $Linea;

                $Fin = "<Linea>000000399990001007</Linea>\r\n";
                $parameters .= $Fin;

                // $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";
                $parameters .= "</Datos>\r\n</Importar>";

                $response = $this->procesarPeticionSiesa($parameters);

                // $result = $client->call('ImportarXML',$parameters);
                // $Resultado = $result['printTipoError'];
                // if($Resultado == '1') {
                //     $response = '';
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
                return response()->json($response);
            }
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
		$xmlResponse = simplexml_load_string($response);
		$soapBody = $xmlResponse->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;
		$importResp = $soapBody->children('http://tempuri.org/')->ImportarXMLResponse;
		$Resultado = (string) $importResp->printTipoError;

        if ($Resultado == '1') {
			// Hay errores — parsear dataset interno
			$innerXml = simplexml_load_string((string) $importResp->ImportarXMLResult);
			$errores = [];

			$tablas = $innerXml->xpath('//*[local-name()="Table"]');

			foreach ($tablas as $row) {
				$f_detalle = (string) $row->f_detalle;
				$f_valor = (string) $row->f_valor;
				$f_nro_linea = (string) $row->f_nro_linea;

				if (!empty($f_detalle)) {
					$errores[] = [
						'nro_linea' => $f_nro_linea,
						'valor' => $f_valor,
						// utf8_encode está deprecated en PHP 8.2+, usar mb_convert_encoding
						'detalle' => mb_convert_encoding($f_detalle, 'UTF-8', 'ISO-8859-1'),
					];
				}
			}
			
            return $errores;
		}else {
            return 'ok';
        }
    }
}
