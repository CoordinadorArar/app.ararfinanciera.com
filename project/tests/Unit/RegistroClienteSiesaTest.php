<?php

namespace Tests\Unit;

use App\Services\Siesa\ClienteSoapSiesa;
use App\Services\Siesa\RegistroClienteSiesa;
use PHPUnit\Framework\TestCase;

class RegistroClienteSiesaTest extends TestCase
{
    const LARGOS = [
        'tercero' => [905],
        'cliente' => [1045],
        'proveedor' => [723],
        'impuestos_cliente' => [46, 46],
        'impuestos_proveedor' => [46],
        'pago_bancolombia' => [839],
        'pago_bogota' => [839],
    ];

    private function cliente(array $cambios = [])
    {
        return $cambios + [
            'IdCliente' => '1234567890',
            'DigitoVerificaCli' => null,
            'TipoIdentificacionCliente' => 1,
            'NomCliente' => 'JUAN CARLOS',
            'ApeCliente' => 'PEREZ GOMEZ',
            'Direccion' => 'CALLE 10 20 30',
            'IdDepartamento' => '68',
            'IdCiudad' => '001',
            'Telefono' => '6071234567',
            'Celular' => '3001234567',
            'Email' => 'juan.perez@example.com',
            'FechaNacimiento' => '1980-01-15 00:00:00',
            'FechaIngreso' => '2023-03-01 00:00:00',
        ];
    }

    private function cuenta()
    {
        return ['Codigo' => '07', 'NumCuenta' => '12345678901', 'TipoCuenta' => 1];
    }

    private function golden($paso)
    {
        return array_map(function ($linea) {
            return rtrim($linea, "\r");
        }, file(__DIR__.'/../fixtures/siesa/'.$paso.'.txt', FILE_IGNORE_NEW_LINES));
    }

    private function todos()
    {
        return array_fill_keys(array_keys(RegistroClienteSiesa::PASOS), true);
    }

    public function test_los_registros_coinciden_con_el_layout_del_codigo_anterior()
    {
        $datos = RegistroClienteSiesa::datos($this->cliente(), $this->cuenta());
        foreach (array_keys(RegistroClienteSiesa::PASOS) as $paso) {
            $this->assertSame($this->golden($paso), RegistroClienteSiesa::lineas($paso, $datos), $paso);
        }
    }

    public function test_tildes_minusculas_y_espacios_producen_el_mismo_registro()
    {
        $datos = RegistroClienteSiesa::datos($this->cliente(['NomCliente' => ' juan  cárlos ', 'ApeCliente' => 'Pérez   Gómez', 'Direccion' => 'calle 10 20 30']), $this->cuenta());
        foreach (array_keys(RegistroClienteSiesa::PASOS) as $paso) {
            $this->assertSame($this->golden($paso), RegistroClienteSiesa::lineas($paso, $datos), $paso);
        }
    }

    public function test_longitudes_totales_no_cambian_con_tildes_enie_textos_largos_ni_un_solo_apellido()
    {
        $variantes = [
            $this->cliente(),
            $this->cliente(['NomCliente' => 'MARÍA JOSÉ ÑÚÑEZ', 'ApeCliente' => 'MUÑOZ']),
            $this->cliente(['NomCliente' => str_repeat('ÁÉÍÓÚÑ ', 30), 'ApeCliente' => str_repeat('PEÑA ', 20), 'Direccion' => str_repeat('CARRERA ÑANDÚ # 45-67 ', 10), 'Email' => str_repeat('a', 300).'@x.co']),
            $this->cliente(['ApeCliente' => '']),
            $this->cliente(['ApeCliente' => null, 'NomCliente' => null, 'Direccion' => null]),
        ];
        foreach ($variantes as $cliente) {
            $datos = RegistroClienteSiesa::datos($cliente, $this->cuenta());
            foreach (self::LARGOS as $paso => $largos) {
                $lineas = RegistroClienteSiesa::lineas($paso, $datos);
                $this->assertSame($largos, array_map('mb_strlen', $lineas), $paso);
                foreach ($lineas as $linea) {
                    $this->assertMatchesRegularExpression('/^[\x20-\x7E]*$/', $linea, $paso);
                }
            }
        }
    }

    public function test_posiciones_clave_del_tercero()
    {
        $datos = RegistroClienteSiesa::datos($this->cliente(['NomCliente' => 'María José', 'ApeCliente' => 'Muñoz']));
        $linea = RegistroClienteSiesa::lineas('tercero', $datos)[0];
        $this->assertSame('0000002020000080071', substr($linea, 0, 19));
        $this->assertSame(str_pad('1234567890', 15), substr($linea, 19, 15));
        $this->assertSame(str_pad('1234567890', 25), substr($linea, 34, 25));
        $this->assertSame('0  C1', substr($linea, 59, 5));
        $this->assertSame(str_pad('MARIA JOSE MUNOZ', 100), substr($linea, 64, 100));
        $this->assertSame(str_pad('MUNOZ', 29), substr($linea, 164, 29));
        $this->assertSame(str_repeat(' ', 29), substr($linea, 193, 29));
        $this->assertSame(str_pad('MARIA JOSE', 40), substr($linea, 222, 40));
        $this->assertSame('100000', substr($linea, 312, 6));
        $this->assertSame('16968001', substr($linea, 488, 8));
        $this->assertSame('19800115', substr($linea, 841, 8));
        $this->assertSame(str_pad('3001234567', 50), substr($linea, 855, 50));
    }

    public function test_posiciones_clave_de_cliente_proveedor_y_pagos()
    {
        $datos = RegistroClienteSiesa::datos($this->cliente(), $this->cuenta());
        $cliente = RegistroClienteSiesa::lineas('cliente', $datos)[0];
        $this->assertSame('20230301', substr($cliente, 949, 8));
        $this->assertSame(str_pad('JUAN CARLOS PEREZ GOMEZ', 40), substr($cliente, 38, 40));
        $proveedor = RegistroClienteSiesa::lineas('proveedor', $datos)[0];
        $this->assertSame('20230301', substr($proveedor, 690, 8));
        $this->assertSame(str_pad('3001234567', 20), substr($proveedor, 590, 20));
        foreach (['pago_bancolombia' => '41', 'pago_bogota' => '7'] as $paso => $formato) {
            $pago = RegistroClienteSiesa::lineas($paso, $datos)[0];
            $this->assertSame(str_pad('07', 10), substr($pago, 38, 10));
            $this->assertSame(str_pad('12345678901', 30), substr($pago, 48, 30));
            $this->assertSame('2', substr($pago, 78, 1));
            $this->assertSame(str_pad($formato, 8), substr($pago, 79, 8));
            $this->assertSame(str_pad('12345678901', 50), substr($pago, 239, 50));
        }
        $this->assertSame(str_pad('1234567890', 50), substr(RegistroClienteSiesa::lineas('pago_bancolombia', $datos)[0], 89, 50));
        $this->assertSame(str_pad('1234567890', 50), substr(RegistroClienteSiesa::lineas('pago_bogota', $datos)[0], 139, 50));
    }

    public function test_nit_sin_digito_de_verificacion_en_todos_los_registros()
    {
        $datos = RegistroClienteSiesa::datos($this->cliente(['IdCliente' => '900508834-2', 'TipoIdentificacionCliente' => 2]), $this->cuenta());
        $this->assertSame('900508834', $datos['nit']);
        $this->assertSame('2', $datos['dv']);
        foreach (array_keys(RegistroClienteSiesa::PASOS) as $paso) {
            foreach (RegistroClienteSiesa::lineas($paso, $datos) as $linea) {
                $this->assertSame(str_pad('900508834', 15), substr($linea, 19, 15), $paso);
            }
        }
        $tercero = RegistroClienteSiesa::lineas('tercero', $datos)[0];
        $this->assertSame('2  N2', substr($tercero, 59, 5));
        $this->assertSame(str_repeat(' ', 98), substr($tercero, 164, 98));
    }

    public function test_nit_y_dv()
    {
        $this->assertSame(['nit' => '900508834', 'dv' => '2'], RegistroClienteSiesa::nitDv(' 900508834-2 '));
        $this->assertSame(['nit' => '900508834', 'dv' => '2'], RegistroClienteSiesa::nitDv('900508834', '2'));
        $this->assertSame(['nit' => '900508834', 'dv' => '2'], RegistroClienteSiesa::nitDv('900508834'));
        $this->assertSame(['nit' => 'AB123', 'dv' => null], RegistroClienteSiesa::nitDv('ab123'));
    }

    public function test_apellidos_y_normalizacion()
    {
        $this->assertSame(['MUNOZ', ''], RegistroClienteSiesa::apellidos('Muñoz'));
        $this->assertSame(['PEREZ', 'GOMEZ'], RegistroClienteSiesa::apellidos('pérez gómez'));
        $this->assertSame(['DE LA HOZ', 'GOMEZ'], RegistroClienteSiesa::apellidos('de la Hoz Gómez'));
        $this->assertSame(['PENA', 'DEL RIO ACUNA'], RegistroClienteSiesa::apellidos('Peña del Río Acuña'));
        $this->assertSame(['', ''], RegistroClienteSiesa::apellidos(null));
        $this->assertSame('NANDU CANON', RegistroClienteSiesa::normalizar("  ñandú\tcañón "));
        $this->assertSame('CALLE 5 N 10 APTO 2 ? OK', RegistroClienteSiesa::normalizar('Calle 5 N° 10 Apto 2º ¿? 😀 ok€'));
        $this->assertMatchesRegularExpression('/^[\x20-\x7E]*$/', RegistroClienteSiesa::normalizar("ÆØ«»©®™\u{00A0}—…“”‘’•"));
        $this->assertSame('ÑÑ   ', RegistroClienteSiesa::campo('ÑÑ', 5));
        $this->assertSame('   AB', RegistroClienteSiesa::campo('AB', 5, true));
        $this->assertSame('ÁÉÍ', RegistroClienteSiesa::campo('ÁÉÍÓÚ', 3));
    }

    public function test_estados_de_los_pasos()
    {
        $sinSiesa = array_fill_keys(array_keys(RegistroClienteSiesa::PASOS), false);
        $datos = RegistroClienteSiesa::datos($this->cliente(['FechaNacimiento' => null]));
        $pasos = collect(RegistroClienteSiesa::pasos($datos, $sinSiesa))->keyBy('clave');
        $this->assertSame('bloqueado', $pasos['tercero']['estado']);
        $this->assertContains('Falta la fecha de nacimiento.', $pasos['tercero']['motivos']);
        $this->assertSame('bloqueado', $pasos['cliente']['estado']);
        $this->assertSame(['Requiere que Tercero (0200) exista en SIESA.'], $pasos['cliente']['motivos']);
        $this->assertSame('omitido', $pasos['pago_bancolombia']['estado']);
        $this->assertSame('omitido', $pasos['pago_bogota']['estado']);

        $datos = RegistroClienteSiesa::datos($this->cliente(), $this->cuenta());
        $pasos = collect(RegistroClienteSiesa::pasos($datos, ['tercero' => true] + $sinSiesa))->keyBy('clave');
        $this->assertSame('hecho', $pasos['tercero']['estado']);
        $this->assertSame('listo', $pasos['cliente']['estado']);
        $this->assertSame('listo', $pasos['proveedor']['estado']);
        $this->assertSame('bloqueado', $pasos['impuestos_cliente']['estado']);
        $this->assertSame(['Requiere que Proveedor (0202) exista en SIESA.'], $pasos['pago_bancolombia']['motivos']);

        $pasos = collect(RegistroClienteSiesa::pasos($datos, ['impuestos_cliente' => false] + $this->todos()))->keyBy('clave');
        $this->assertSame('listo', $pasos['impuestos_cliente']['estado']);
        $this->assertSame('hecho', $pasos['pago_bogota']['estado']);

        $datos = RegistroClienteSiesa::datos($this->cliente(['TipoIdentificacionCliente' => 3, 'Direccion' => '', 'IdCiudad' => null]));
        $motivos = RegistroClienteSiesa::pasos($datos, $sinSiesa)[0]['motivos'];
        $this->assertContains('El tipo de identificación no está homologado con SIESA.', $motivos);
        $this->assertContains('Falta la dirección.', $motivos);
        $this->assertContains('Falta la ciudad (código DANE de departamento y ciudad).', $motivos);

        $datos = RegistroClienteSiesa::datos($this->cliente(['IdCliente' => '900508834-3', 'TipoIdentificacionCliente' => 2, 'FechaNacimiento' => null]));
        $this->assertSame(['El dígito de verificación no corresponde al NIT.'], RegistroClienteSiesa::pasos($datos, $sinSiesa)[0]['motivos']);
    }

    public function test_xml_con_encabezado_fin_escape_y_clave_enmascarada()
    {
        $conexion = ['conexion' => 'Pruebas', 'id_cia' => 7, 'usuario' => 'usuario', 'clave' => 'secreta'];
        $datos = RegistroClienteSiesa::datos($this->cliente(['Direccion' => 'CALLE 1 & 2 <B>']));
        $xml = RegistroClienteSiesa::xml(RegistroClienteSiesa::lineas('impuestos_cliente', $datos), $conexion, true);
        $this->assertStringContainsString('<Clave>********</Clave>', $xml);
        $this->assertStringNotContainsString('secreta', $xml);
        $this->assertStringContainsString("<Linea>000000100000001007</Linea>\r\n", $xml);
        $this->assertStringContainsString("<Linea>000000499990001007</Linea>\r\n</Datos>\r\n</Importar>", $xml);
        $xml = RegistroClienteSiesa::xml(RegistroClienteSiesa::lineas('tercero', $datos), $conexion);
        $this->assertStringContainsString('<Clave>secreta</Clave>', $xml);
        $this->assertStringContainsString('<Linea>000000399990001007</Linea>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertSame(905, mb_strlen((string) simplexml_load_string($xml)->Datos->Linea[1]));
    }

    public function test_interpretar_respuestas_de_siesa_sin_echo_ni_excepciones()
    {
        $exito = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ImportarXMLResponse xmlns="http://tempuri.org/"><ImportarXMLResult/><printTipoError>0</printTipoError></ImportarXMLResponse></soap:Body></soap:Envelope>';
        $this->assertSame(['ok' => true, 'detalle' => 'SIESA importó el paso sin errores.', 'errores' => []], ClienteSoapSiesa::interpretar($exito));

        $error = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ImportarXMLResponse xmlns="http://tempuri.org/"><ImportarXMLResult><diffgr:diffgram xmlns:diffgr="urn:schemas-microsoft-com:xml-diffgram-v1"><NewDataSet xmlns=""><Table><f_nro_linea>2</f_nro_linea><f_valor>1234567890</f_valor><f_detalle>El tercero ya existe</f_detalle></Table></NewDataSet></diffgr:diffgram></ImportarXMLResult><printTipoError>1</printTipoError></ImportarXMLResponse></soap:Body></soap:Envelope>';
        $resultado = ClienteSoapSiesa::interpretar($error);
        $this->assertFalse($resultado['ok']);
        $this->assertSame([['nroLinea' => '2', 'valor' => '1234567890', 'detalle' => 'El tercero ya existe']], $resultado['errores']);

        $this->assertFalse(ClienteSoapSiesa::interpretar('<html>error')['ok']);
        $this->assertFalse(ClienteSoapSiesa::interpretar('')['ok']);
        $this->assertFalse(ClienteSoapSiesa::interpretar('<a><b/></a>')['ok']);
    }
}
