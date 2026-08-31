<?php
require_once("./ConectorServer.php");
require_once("./lib/nusoap.php");
$cfgSiesa = require __DIR__.'/config.php';
$obj = new ConectorServer1();

$idfactura = '20974';
$tipodocumento = 'FEX';

        $client = new nusoap_client('http://172.28.254.20/WSUNOEE/WSUNOEE.asmx?wsdl','wsdl'); 
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = true;

        $numero = 0;

        $FactoringFactura = $obj->FactoringFactura('20974','FEX');

        foreach ($FactoringFactura as $key => $value) {
            if($numero != $value['idfactura']){
            $numero = $value['idfactura'];
        
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
            $parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>prueba</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".$cfgSiesa['siesa']['clave']."</Clave>\r\n<Datos>\r\n";
        
            // Real
            /*$parameters = "<tem:ImportarXML>\r\n<!--Optional:-->\r\n<tem:pvstrDatos><![CDATA[<?xml version='1.0' encoding='utf-8'?>\r\n<Importar>\r\n<NombreConexion>real</NombreConexion>\r\n<IdCia>7</IdCia>\r\n<Usuario>web_rotacion</Usuario>\r\n<Clave>".$cfgSiesa['siesa']['clave']."</Clave>\r\n<Datos>\r\n"; */

            $cabecera = "<Linea>000000100000001".$F_CIA."</Linea>\r\n";
            $parameters .= $cabecera;
            
            if($FactoringFactura != 0){
                foreach ($FactoringFactura as $key => $value) {
                    if($numero == $value['idfactura']){
                    $F350_ID_TIPO_DOCTO = $value['tipoDocumento'];
                    $F350_CONSEC_DOCTO = str_pad($value['idfactura'],8,"0",STR_PAD_LEFT);
                    $F350_FECHA = $value['fecdocumento']->format('Ymd');
                    
                    if(preg_match("/-/",$value['idtercero'])){
                        $F350_ID_TERCERO =  substr($value['idtercero'], 0, -2);
                    }
                    else{
                        $F350_ID_TERCERO =  $value['idtercero'];
                    }

                    $F350_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                    $F350_IND_ESTADO = 1;

                    $F350_NOTAS = str_pad($value['Nota'],255," ",STR_PAD_RIGHT);
                    }
                }

                $a = 2;
            

            $Linea .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F_CONSEC_AUTO_REG.
            $CO_PRINCIPAL.$F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F350_FECHA.$F350_ID_TERCERO.$F350_ID_CLASE_DOCTO.$F350_IND_ESTADO.$F350_IND_IMPRESION.$F350_NOTAS."</Linea>\r\n";

            $parameters .= $Linea;

                $Linea1 = '';

                foreach ($FactoringFactura as $value) {
                if($numero == $value['idfactura']){
                if($value['tipo'] == 'M'){
                    // MOVIMIENTO CONTABLE
                    $a++;

                    $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                    $F_TIPO_REG = '0351';
                    $F_SUBTIPO_REG = '00';
                    $F_VERSION_REG = '02';
                    $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                    $F350_ID_CO = '001';
                    $F350_ID_TIPO_DOCTO = $value['tipoDocumento'];
                    $F350_CONSEC_DOCTO = str_pad($value['idfactura'],8,"0",STR_PAD_LEFT);
                    $F351_ID_AUXILIAR = str_pad($value['cuenta'],20, " ", STR_PAD_RIGHT);
                    if(preg_match("/-/",$value['idtercero'])){
                        $F350_ID_TERCERO =  substr($value['idtercero'], 0, -2);
                    }
                    else{
                        $F350_ID_TERCERO =  $value['idtercero'];
                    }
                    $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15, " ", STR_PAD_RIGHT);
                    $F351_ID_CO_MOV = '001';
                    $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);


                    $F351_ID_CCOSTO = str_pad($value['centrocosto'],15, " ", STR_PAD_RIGHT);


                    $F351_ID_FE = str_pad('',10, " ", STR_PAD_RIGHT);

                    if($value['Debito'] != 0){
                        $F351_VALOR_DB_SIGNO = '+';
                        $F351_VALOR_DB_VALOR = str_pad($value['Debito'],20,"0",STR_PAD_LEFT);
                        $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;
                    }
                    else{
                        $F351_VALOR_DB = '+000000000000000.0000';
                    }

                    if($value['credito'] != 0){
                        $F351_VALOR_CR_SIGNO = '+';
                        $F351_VALOR_CR_VALOR = str_pad($value['credito'],20,"0",STR_PAD_LEFT);
                        $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR;
                    }
                    else{
                        $F351_VALOR_CR = '+000000000000000.0000';
                    }

                    $F351_VALOR_DB_ALT = '+000000000000000.0000';
                    $F351_VALOR_CR_ALT = '+000000000000000.0000';

                    if($value['Base'] != 0){
                        $F351_BASE_GRAVABLE_SIGNO = '+';
                        $F351_VALOR_GRAVABLE_VALOR = str_pad($value['Base'],20,"0",STR_PAD_LEFT);
                        $F351_BASE_GRAVABLE	 = $F351_BASE_GRAVABLE_SIGNO.$F351_VALOR_GRAVABLE_VALOR;
                    }
                    else{
                        $F351_BASE_GRAVABLE	 = '+000000000000000.0000';
                    }

                    $F351_DOCTO_BANCO = str_pad('',2, " ", STR_PAD_RIGHT);

                    $F351_NRO_DOCTO_BANCO = '00000000';

                    $F351_NOTAS = str_pad($value['Nota'],255," ",STR_PAD_RIGHT);
                    
                    $Linea1 .= "<Linea>".$F_NUMERO_REG.$F_TIPO_REG.$F_SUBTIPO_REG.$F_VERSION_REG.$F_CIA.$F350_ID_CO.
                    $F350_ID_TIPO_DOCTO.$F350_CONSEC_DOCTO.$F351_ID_AUXILIAR.$F351_ID_TERCERO.$F351_ID_CO_MOV.$F351_ID_UN.
                    $F351_ID_CCOSTO.$F351_ID_FE.$F351_VALOR_DB.$F351_VALOR_CR.$F351_VALOR_DB_ALT.$F351_VALOR_CR_ALT.$F351_BASE_GRAVABLE.
                    $F351_DOCTO_BANCO.$F351_NRO_DOCTO_BANCO.$F351_NOTAS."</Linea>\r\n";   
                    
                    //print_r($F351_VALOR_DB." ".$F351_VALOR_CR." ".$F351_BASE_GRAVABLE."\r\n");

                    }     
                }
                }

                //print_r($Linea1);
                //exit();

                $parameters .= $Linea1;

                $Linea2 = '';
            
                foreach ($FactoringFactura as $key => $value) {
                    if($numero == $value['idfactura']){
                    if($value['tipo'] == 'C'){
                        $a++;
                        $F_NUMERO_REG = str_pad($a,7, "0", STR_PAD_LEFT);
                        $F_TIPO_REG = '0351';
                        $F_SUBTIPO_REG = '01';
                        $F_VERSION_REG = '02';
                        $F_CIA = '007'; // COMPAÑIA 007 (ARAR Financiera)
                        $F350_ID_CO = '001';
                        $F350_ID_TIPO_DOCTO = $value['tipoDocumento'];
                        $F350_CONSEC_DOCTO = str_pad($value['idfactura'],8,"0",STR_PAD_LEFT);
                        $F351_ID_AUXILIAR = str_pad($value['cuenta'],20, " ", STR_PAD_RIGHT);
                        $F350_FECHA = $value['fecdocumento']->format('Ymd');
                        
                        if(preg_match("/-/",$value['idtercero'])){
                            $F351_ID_TERCERO =  substr($value['idtercero'], 0, -2);
                        }
                        else{
                            $F351_ID_TERCERO =  $value['idtercero'];
                        }

                        $F351_ID_TERCERO = str_pad($F350_ID_TERCERO,15," ", STR_PAD_RIGHT);
                        $F351_ID_CO_MOV = '001';
                        $F351_ID_UN	 = str_pad('01',20, " ", STR_PAD_RIGHT);
                        $F351_ID_CCOSTO = str_pad('',15, " ", STR_PAD_RIGHT);

                        $F351_VALOR_DB_SIGNO = '+';
                        $F351_VALOR_DB_VALOR = str_pad($value['Debito'],20,"0",STR_PAD_LEFT);
                        $F351_VALOR_DB = $F351_VALOR_DB_SIGNO.$F351_VALOR_DB_VALOR;

                        $F351_VALOR_CR_SIGNO = '+';
                        $F351_VALOR_CR_VALOR = str_pad($value['idtercero'],15,"0",STR_PAD_LEFT);
                        $F351_VALOR_CR_VALOR1 = str_pad('.',5,"0",STR_PAD_RIGHT);
                        $F351_VALOR_CR = $F351_VALOR_CR_SIGNO.$F351_VALOR_CR_VALOR.$F351_VALOR_CR_VALOR1;

                        $F351_VALOR_DB_ALT = '+000000000000000.0000';
                        $F351_VALOR_CR_ALT = '+000000000000000.0000';

                        $F351_NOTAS = str_pad($value['Nota'],255," ",STR_PAD_RIGHT);

                        $F353_ID_SUCURSAL = '001';
                        
                        $F353_ID_TIPO_DOCTO_CRUCE = str_pad($value['tipoDocumento'],3, " ", STR_PAD_RIGHT);

                        $F353_CONSEC_DOCTO_CRUCE = str_pad($value['idfactura'],8,"0",STR_PAD_LEFT);

                        $F353_NRO_CUOTA_CRUCE = str_pad('0',3, "0", STR_PAD_RIGHT);

                        $F353_FECHA_VCTO = $value['fechaVencimiento']->format('Ymd');

                        $F353_FECHA_DSCTO_PP =$value['fechaVencimiento']->format('Ymd');

                        $F353_VLR_DSCTO_PP = '+000000000000000.0000';

                        $F354_VALOR_APLICADO_PP = '+000000000000000.0000';

                        $F354_VALOR_APLICADO_PP_ALT = '+000000000000000.0000';

                        $F354_VALOR_APROVECHA = '+000000000000000.0000';

                        $F354_VALOR_APROVECHA_ALT = '+000000000000000.0000';

                        $F354_VALOR_RETENCION = '+000000000000000.0000';

                        $F354_VALOR_RETENCION_ALT = '+000000000000000.0000';

                        $F354_TERCERO_VEND = str_pad('900644447',15, " ", STR_PAD_RIGHT);

                        $F354_NOTAS = str_pad($value['Nota'],255," ",STR_PAD_RIGHT);

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

            $parameters .= "</Datos>\r\n</Importar>]]>\r\n</tem:pvstrDatos>\r\n<tem:printTipoError>1</tem:printTipoError>\r\n</tem:ImportarXML>";  

            //$parameters .= "\r\n_____________________________________________\r\n";
            //print_r($parameters);
            //exit();
            
            $result = $client->call('ImportarXML',$parameters);   
            var_dump($result);
            exit();
            $Resultado = $result['printTipoError'];

            if ($Resultado =='1') 
            {           ?>
                
                Lo Sentimos,  Se presentaron Errores en el proceso.
                
                
                <?php
                $return =  $result['ImportarXMLResult']['schema']['element']['complexType'];
                $errores = $result['ImportarXMLResult']['diffgram']['NewDataSet']['Table'];
                if (!empty($errores)) 
                {
                    
                    $f_detalle = $f_valor = $f_nro_linea = ''; 
                    foreach ($errores as $rows) 
                    {
                        if (!empty($rows['f_detalle'])) 
                        {
                            $f_detalle = $rows['f_detalle'];
                            $f_valor = $rows['f_valor'];
                            $f_nro_linea = $rows['f_nro_linea'];	
                            
                            
                            echo $f_nro_linea."\n";
                            print_r($f_valor)."\n";
                            echo utf8_encode($f_detalle)."\n"; 
                            

                        }	
                    }
                    $errores = $result['ImportarXMLResult']['diffgram']['NewDataSet'];
                    foreach ($errores as $rows) 
                    {
                        if (!empty($rows['f_detalle'])) 
                        {
                            $f_detalle = $rows['f_detalle'];
                            $f_valor = $rows['f_valor'];
                            $f_nro_linea = $rows['f_nro_linea'];	
                            
                            echo $f_nro_linea."\n"; 
                            print_r($f_valor)."\n"; 
                            echo utf8_encode($f_detalle)."\n"; 
                            
                        }	
                    }
                    
                }
            }				
            else
            {
                $valida = 1;
            }
        }
    }

    echo $valida;  
?>