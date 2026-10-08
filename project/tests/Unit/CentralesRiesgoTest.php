<?php

namespace Tests\Unit;

use App\Services\Centrales\CentralException;
use App\Services\Centrales\CentralesRiesgo;
use App\Services\Centrales\DatacreditoProveedor;
use App\Services\Centrales\ResultadoCentral;
use App\Services\Centrales\SimuladoProveedor;
use App\Services\Centrales\TransUnionProveedor;
use PHPUnit\Framework\TestCase;

class CentralesRiesgoTest extends TestCase
{
    private function config($simulado = false)
    {
        $config = require __DIR__.'/../../config/centrales.php';
        $config['simulado_habilitado'] = $simulado;
        foreach (['wsdl', 'usuario', 'password', 'key_path', 'cert_path'] as $clave) {
            $config['proveedores']['transunion'][$clave] = '';
        }
        foreach (['endpoint', 'usuario', 'password', 'cert_path', 'key_path'] as $clave) {
            $config['proveedores']['datacredito'][$clave] = '';
        }
        $config['proveedores']['transunion']['montos_en_miles'] = true;
        $config['proveedores']['datacredito']['montos_en_miles'] = true;
        return $config;
    }

    private function transunion(array $cambios = [])
    {
        return new TransUnionProveedor(array_merge($this->config()['proveedores']['transunion'], $cambios));
    }

    private function datacredito(array $cambios = [])
    {
        return new DatacreditoProveedor(array_merge($this->config()['proveedores']['datacredito'], $cambios));
    }

    private function tercero($documento = '1000000001')
    {
        return ['documento' => $documento, 'tipoDocumento' => 1, 'primerApellido' => 'PRUEBA', 'nombre' => 'PERSONA PRUEBA', 'fechaExpedicion' => '2005-02-01'];
    }

    private function fixture()
    {
        return file_get_contents(__DIR__.'/../fixtures/centrales/transunion-consulta.xml');
    }

    public function test_parser_transunion_con_fixture()
    {
        $resultado = $this->transunion()->interpretar($this->fixture());
        $datos = $resultado->toArray();
        $this->assertSame('transunion', $datos['proveedor']);
        $this->assertFalse($datos['simulado']);
        $this->assertSame('pesos', $datos['unidad']);
        $this->assertSame('miles_de_pesos', $datos['unidadOrigen']);
        $this->assertSame('PRUEBA ANONIMA UNO', $datos['datosBasicos']['nombre']);
        $this->assertSame('VIGENTE', $datos['datosBasicos']['estadoDocumento']);
        $this->assertSame('07/10/2026 10:15:00', $datos['datosBasicos']['fechaInforme']);
        $this->assertSame(['valor' => 640, 'rangoMin' => null, 'rangoMax' => null, 'nivel' => null], $datos['score']);
        $this->assertCount(5, $datos['obligaciones']);
        $this->assertSame(['al_dia', 'mora', 'mora', 'cerrada', 'al_dia'], array_column($datos['obligaciones'], 'estado'));
        $this->assertSame(['financiero', 'financiero', 'financiero', 'financiero', 'real'], array_column($datos['obligaciones'], 'sector'));
        $this->assertSame(9000000.0, $datos['obligaciones'][0]['saldo']);
        $this->assertSame(60, $datos['obligaciones'][1]['diasMora']);
        $this->assertSame(['obligacionesAlDia' => 2, 'obligacionesMora' => 2, 'saldoTotal' => 16000000.0, 'valorMora' => 700000.0, 'cuotaMensual' => 850000.0], $datos['totales']);
        $this->assertCount(2, $datos['resumen']);
        $this->assertSame(14500000.0, $datos['resumen'][0]['saldoTotal']);
        $this->assertSame(2, $datos['resumen'][0]['obligacionesMora']);
        $this->assertSame([['entidad' => 'BANCO FICTICIO UNO', 'fecha' => '01/09/2026', 'motivo' => 'ESTUDIO DE CREDITO'], ['entidad' => 'FINANCIERA FICTICIA TRES', 'fecha' => '15/08/2026', 'motivo' => null]], $datos['huella']);
        $this->assertContains('Tiene 2 obligación(es) en mora por $700.000.', $datos['alertas']);
        $this->assertSame($this->fixture(), $resultado->cruda);
    }

    public function test_transunion_un_solo_registro_y_sin_nodos_opcionales()
    {
        $xml = '<CIFIN><Tercero><NombreTitular>UNO SOLO</NombreTitular><Estado>CANCELADA POR MUERTE</Estado>'
            .'<Consolidado><ResumenPrincipal><Registro><PaqueteInformacion>FINANCIERO</PaqueteInformacion><TotalSaldo>100</TotalSaldo></Registro></ResumenPrincipal></Consolidado>'
            .'<SectorCooperativoAlDia><Obligacion><NombreEntidad>COOP</NombreEntidad><SaldoObligacion>1,500</SaldoObligacion><ValorCuota>50</ValorCuota></Obligacion></SectorCooperativoAlDia>'
            .'</Tercero></CIFIN>';
        $datos = $this->transunion()->interpretar($xml)->toArray();
        $this->assertCount(1, $datos['resumen']);
        $this->assertSame(100000.0, $datos['resumen'][0]['saldoTotal']);
        $this->assertCount(1, $datos['obligaciones']);
        $this->assertSame('cooperativo', $datos['obligaciones'][0]['sector']);
        $this->assertSame(1500000.0, $datos['obligaciones'][0]['saldo']);
        $this->assertNull($datos['score']);
        $this->assertSame([], $datos['huella']);
        $this->assertContains('El documento figura en estado "CANCELADA POR MUERTE".', $datos['alertas']);
    }

    public function test_transunion_montos_en_pesos_y_raiz_tercero()
    {
        $datos = $this->transunion(['montos_en_miles' => false])->interpretar('<Tercero><SectorFinancieroAlDia><Obligacion><SaldoObligacion>9000</SaldoObligacion></Obligacion></SectorFinancieroAlDia></Tercero>')->toArray();
        $this->assertSame('pesos', $datos['unidadOrigen']);
        $this->assertSame(9000.0, $datos['obligaciones'][0]['saldo']);
    }

    public function test_transunion_respuesta_invalida()
    {
        foreach (['no es xml', '<CIFIN><Error>Clave vencida</Error></CIFIN>', '<CIFIN><Tercero/></CIFIN>'] as $xml) {
            try {
                $this->transunion()->interpretar($xml);
                $this->fail('Debió fallar: '.$xml);
            } catch (CentralException $e) {
                $this->assertSame('respuesta_invalida', $e->codigo);
            }
        }
    }

    public function test_transunion_sin_credenciales_no_llama_al_servicio()
    {
        $proveedor = $this->transunion();
        $this->assertFalse($proveedor->configurado());
        $this->assertSame('sin_credenciales', $proveedor->probarConexion()['codigo']);
        try {
            $proveedor->consultar($this->tercero());
            $this->fail('Debió fallar');
        } catch (CentralException $e) {
            $this->assertSame('sin_credenciales', $e->codigo);
        }
        try {
            $proveedor->consultar(['tipoDocumento' => 99] + $this->tercero());
            $this->fail('Debió fallar');
        } catch (CentralException $e) {
            $this->assertSame('datos_invalidos', $e->codigo);
        }
    }

    public function test_normalizacion_de_montos()
    {
        $this->assertSame(1234.0, ResultadoCentral::monto('1,234'));
        $this->assertSame(1234000.0, ResultadoCentral::monto('1234', 1000));
        $this->assertSame(-5.5, ResultadoCentral::monto(' -5.5 '));
        $this->assertNull(ResultadoCentral::monto(''));
        $this->assertNull(ResultadoCentral::monto('N/A'));
        $this->assertNull(ResultadoCentral::texto('   '));
        $this->assertNull(ResultadoCentral::entero('x'));
    }

    public function test_simulado_determinista_por_ultimo_digito()
    {
        $proveedor = new SimuladoProveedor();
        $this->assertTrue($proveedor->configurado());
        $this->assertTrue($proveedor->probarConexion()['ok']);
        $this->assertSame('bueno', SimuladoProveedor::perfil('1000000000'));
        $this->assertSame('regular', SimuladoProveedor::perfil('1000000005'));
        $this->assertSame('malo', SimuladoProveedor::perfil('1000000008'));
        $this->assertSame('sin_historial', SimuladoProveedor::perfil('1000000009'));

        $bueno = $proveedor->consultar($this->tercero('1000000001'));
        $this->assertTrue($bueno->simulado);
        $this->assertSame('simulado', $bueno->proveedor);
        $this->assertSame(820, $bueno->score['valor']);
        $this->assertSame(0, $bueno->totales()['obligacionesMora']);
        $this->assertSame(true, json_decode($bueno->cruda, true)['simulado']);

        $malo = $proveedor->consultar($this->tercero('1000000007'))->toArray();
        $this->assertSame(2, $malo['totales']['obligacionesMora']);
        $this->assertSame(2020000.0, $malo['totales']['valorMora']);
        $this->assertSame('bajo', $malo['score']['nivel']);

        $sinHistorial = $proveedor->consultar($this->tercero('1000000009'))->toArray();
        $this->assertSame([], $sinHistorial['obligaciones']);
        $this->assertNull($sinHistorial['score']);
        $this->assertContains('Sin historial crediticio reportado.', $sinHistorial['alertas']);

        $a = $proveedor->consultar($this->tercero('1000000004'))->toArray();
        $b = $proveedor->consultar($this->tercero('1000000004'))->toArray();
        unset($a['fechaConsulta'], $b['fechaConsulta']);
        $this->assertSame($a, $b);
    }

    public function test_vigencia_y_reutilizacion()
    {
        $this->assertSame('2026-11-06 10:00:00', CentralesRiesgo::vigenteHasta('2026-10-07 10:00:00', 30));
        $vigente = ['exitosa' => true, 'vigenteHasta' => '2026-11-06 10:00:00'];
        $this->assertTrue(CentralesRiesgo::reutilizable($vigente, '2026-11-06 09:59:59'));
        $this->assertFalse(CentralesRiesgo::reutilizable($vigente, '2026-11-06 10:00:00'));
        $this->assertFalse(CentralesRiesgo::reutilizable($vigente, '2026-10-08 00:00:00', true));
        $this->assertFalse(CentralesRiesgo::reutilizable(['exitosa' => false] + $vigente, '2026-10-08 00:00:00'));
        $this->assertFalse(CentralesRiesgo::reutilizable(null, '2026-10-08 00:00:00'));
        $ultimas = ['transunion' => $vigente, 'datacredito' => ['exitosa' => true, 'vigenteHasta' => '2026-10-01 00:00:00']];
        $this->assertSame(['transunion' => 'reutilizar', 'datacredito' => 'consultar', 'simulado' => 'consultar'], CentralesRiesgo::plan(['transunion', 'datacredito', 'simulado'], $ultimas, '2026-10-08 00:00:00'));
        $this->assertSame(['transunion' => 'consultar'], CentralesRiesgo::plan(['transunion'], $ultimas, '2026-10-08 00:00:00', true));
    }

    public function test_disponibilidad_de_proveedores()
    {
        $centrales = new CentralesRiesgo($this->config(false));
        $this->assertSame(['transunion', 'datacredito'], $centrales->claves());
        $this->assertSame([], $centrales->disponibles());
        $this->assertFalse($centrales->existe('simulado'));
        $this->assertSame(30, $centrales->vigenciaDias());
        $this->assertSame('transunion', $centrales->predeterminado());
        $this->assertFalse($centrales->predeterminadoDisponible());
        $this->assertNull((new CentralesRiesgo($this->config(), ['predeterminado' => '']))->predeterminado());
        $this->assertFalse((new CentralesRiesgo($this->config(), ['predeterminado' => '']))->predeterminadoDisponible());
        $this->assertFalse((new CentralesRiesgo($this->config(false), ['predeterminado' => 'simulado']))->predeterminadoDisponible());
        $this->assertNotNull($centrales->pendienteValidar('datacredito'));

        $centrales = new CentralesRiesgo($this->config(true), ['predeterminado' => 'simulado', 'vigenciaDias' => '15', 'habilitado.transunion' => '0']);
        $this->assertSame(['transunion', 'datacredito', 'simulado'], $centrales->claves());
        $this->assertSame(['simulado'], $centrales->disponibles());
        $this->assertFalse($centrales->habilitado('transunion'));
        $this->assertTrue($centrales->habilitado('datacredito'));
        $this->assertSame('simulado', $centrales->predeterminado());
        $this->assertTrue($centrales->predeterminadoDisponible());
        $this->assertSame(15, $centrales->vigenciaDias());
        $this->assertSame('deshabilitado', $centrales->ejecutar('transunion', $this->tercero())['codigo']);
        $this->assertSame('sin_credenciales', $centrales->ejecutar('datacredito', $this->tercero())['codigo']);
        $this->assertTrue($centrales->ejecutar('simulado', $this->tercero())['ok']);
    }

    public function test_datacredito_parser_y_solicitud()
    {
        $informe = '<Informes><Informe fechaConsulta="2026-10-07T10:00:00" respuesta="13" identificacionDigitada="1000000001">'
            .'<NaturalNacional nombres="PERSONA" primerApellido="PRUEBA" segundoApellido="DOS"><Identificacion estado="00" fechaExpedicion="2005-02-01" ciudad="CIUDAD" numero="1000000001"/><Edad min="36" max="45"/></NaturalNacional>'
            .'<TarjetaCredito entidad="BANCO X" numero="1234" fechaApertura="2020-01-01" sector="1"><Valores><Valor fecha="2026-08-31" saldoActual="2000" saldoMora="200" cuota="100" diasMora="30"/></Valores><Estados><EstadoCuenta codigo="01"/></Estados></TarjetaCredito>'
            .'<CuentaCartera entidad="COOP Y" sector="2"><Valores><Valor saldoActual="5000" saldoMora="0" cuota="250"/></Valores></CuentaCartera>'
            .'<Score tipo="M" puntaje="702"/><Consulta fecha="2026-09-01" entidad="BANCO X" razon="00"/></Informe></Informes>';
        foreach ([$informe, '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ns:consultarHC2Response xmlns:ns="urn:x"><return>'.htmlspecialchars($informe, ENT_XML1).'</return></ns:consultarHC2Response></soap:Body></soap:Envelope>'] as $xml) {
            $datos = $this->datacredito()->interpretar($xml)->toArray();
            $this->assertSame('datacredito', $datos['proveedor']);
            $this->assertSame('PERSONA PRUEBA DOS', $datos['datosBasicos']['nombre']);
            $this->assertSame('36-45', $datos['datosBasicos']['rangoEdad']);
            $this->assertSame(702, $datos['score']['valor']);
            $this->assertSame(['mora', 'al_dia'], array_column($datos['obligaciones'], 'estado'));
            $this->assertSame(['financiero', 'cooperativo'], array_column($datos['obligaciones'], 'sector'));
            $this->assertSame(['obligacionesAlDia' => 1, 'obligacionesMora' => 1, 'saldoTotal' => 7000000.0, 'valorMora' => 200000.0, 'cuotaMensual' => 350000.0], $datos['totales']);
            $this->assertSame([['entidad' => 'BANCO X', 'fecha' => '2026-09-01', 'motivo' => '00']], $datos['huella']);
        }
        $this->expectExceptionObject(new CentralException('respuesta_invalida', 'La respuesta de DataCrédito no contiene un informe de historia de crédito.'));
        $this->datacredito()->interpretar('<Envelope><Body><Fault>error</Fault></Body></Envelope>');
    }

    public function test_datacredito_sin_credenciales_y_solicitud_escapada()
    {
        $proveedor = $this->datacredito();
        $this->assertFalse($proveedor->configurado());
        $this->assertSame('sin_credenciales', $proveedor->probarConexion()['codigo']);
        $solicitud = $this->datacredito(['usuario' => 'u', 'password' => 'p<&>'])->solicitud(['primerApellido' => 'O\'NEIL&'] + $this->tercero(), '1');
        $this->assertNotFalse(simplexml_load_string($solicitud));
        $this->assertStringContainsString('<clave>p&lt;&amp;&gt;</clave>', $solicitud);
        $this->assertStringContainsString('<ws:consultarHC2>', $solicitud);
    }
}
