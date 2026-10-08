<?php

namespace App\Services\Siesa;

use Illuminate\Support\Facades\Log;

class ClienteSoapSiesa
{
    const MENSAJE_CONEXION = 'No fue posible conectar con SIESA. Intente de nuevo o contacte al administrador.';

    const MENSAJE_DESHABILITADO = 'El envío a SIESA está deshabilitado (modo solo vista previa).';

    public static function habilitado()
    {
        return (bool) config('services.siesa.envio_habilitado');
    }

    public static function conexion()
    {
        return [
            'conexion' => config('services.siesa.conexion'),
            'id_cia' => config('services.siesa.id_cia'),
            'usuario' => config('services.siesa.usuario'),
            'clave' => config('services.siesa.clave'),
        ];
    }

    public static function enviar($xml)
    {
        if (!self::habilitado()) {
            return ['ok' => false, 'detalle' => self::MENSAJE_DESHABILITADO, 'errores' => []];
        }
        $sobre = '<?xml version="1.0" encoding="utf-8"?>'
            .'<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Body><ImportarXML xmlns="http://tempuri.org/"><pvstrDatos><![CDATA['.$xml.']]></pvstrDatos><printTipoError>1</printTipoError></ImportarXML></soap:Body></soap:Envelope>';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => config('services.siesa.url'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $sobre,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: "http://tempuri.org/ImportarXML"', 'Content-Length: '.strlen($sobre)],
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        $respuesta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error || $codigo !== 200) {
            Log::warning('SIESA ImportarXML sin conexión', ['error' => $error, 'http' => $codigo]);
            return ['ok' => false, 'detalle' => self::MENSAJE_CONEXION, 'errores' => []];
        }
        return self::interpretar($respuesta);
    }

    public static function interpretar($respuesta)
    {
        $previo = libxml_use_internal_errors(true);
        $doc = simplexml_load_string((string) $respuesta);
        $tipo = $doc !== false ? $doc->xpath('//*[local-name()="printTipoError"]') : [];
        $tablas = $doc !== false ? $doc->xpath('//*[local-name()="Table"]') : [];
        $resultado = $doc !== false ? $doc->xpath('//*[local-name()="ImportarXMLResult"]') : [];
        if (!$tablas && $resultado && trim((string) $resultado[0]) !== '') {
            $interno = simplexml_load_string(trim((string) $resultado[0]));
            $tablas = $interno !== false ? $interno->xpath('//*[local-name()="Table"]') : [];
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        if ($doc === false || !$tipo) {
            return ['ok' => false, 'detalle' => 'La respuesta de SIESA no es válida.', 'errores' => []];
        }
        $errores = [];
        foreach ($tablas ?: [] as $tabla) {
            $valor = function ($nombre) use ($tabla) {
                $nodo = $tabla->xpath('*[local-name()="'.$nombre.'"]');
                return $nodo ? trim((string) $nodo[0]) : '';
            };
            if ($valor('f_detalle') !== '') {
                $errores[] = ['nroLinea' => $valor('f_nro_linea'), 'valor' => $valor('f_valor'), 'detalle' => $valor('f_detalle')];
            }
        }
        $ok = trim((string) $tipo[0]) !== '1' && !$errores;
        return ['ok' => $ok, 'detalle' => $ok ? 'SIESA importó el paso sin errores.' : 'SIESA reportó errores en la importación.', 'errores' => $errores];
    }
}
