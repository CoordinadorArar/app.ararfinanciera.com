<?php

namespace App\Services\Centrales;

use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

class DatacreditoProveedor implements ProveedorCentral
{
    const CUENTAS = ['TarjetaCredito' => 'tarjeta_credito', 'CuentaCartera' => 'cartera', 'CuentaAhorro' => 'cuenta_ahorro', 'CuentaCorriente' => 'cuenta_corriente'];

    const SECTORES = ['1' => 'financiero', '2' => 'cooperativo', '3' => 'real', '4' => 'telecomunicaciones'];

    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function configurado(): bool
    {
        foreach (['endpoint', 'usuario', 'password'] as $clave) {
            if (trim((string) ($this->config[$clave] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    private function http()
    {
        if (!$this->configurado()) {
            throw new CentralException('sin_credenciales', 'DataCrédito no tiene credenciales configuradas.');
        }
        $opciones = ['verify' => (bool) ($this->config['verify_peer'] ?? true)];
        if (trim((string) ($this->config['cert_path'] ?? '')) !== '') {
            if (!is_readable($this->config['cert_path'])) {
                throw new CentralException('sin_credenciales', 'No se encuentra el certificado de DataCrédito.');
            }
            $opciones['cert'] = trim((string) ($this->config['key_password'] ?? '')) !== '' ? [$this->config['cert_path'], $this->config['key_password']] : $this->config['cert_path'];
            if (trim((string) ($this->config['key_path'] ?? '')) !== '') {
                $opciones['ssl_key'] = trim((string) ($this->config['key_password'] ?? '')) !== '' ? [$this->config['key_path'], $this->config['key_password']] : $this->config['key_path'];
            }
        }
        return Http::withOptions($opciones)->timeout((int) ($this->config['timeout'] ?? 30));
    }

    public function probarConexion(): array
    {
        try {
            $respuesta = $this->http()->get($this->config['endpoint'].'?wsdl');
        } catch (CentralException $e) {
            return ['ok' => false, 'codigo' => $e->codigo, 'mensaje' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'codigo' => 'conexion', 'mensaje' => 'No fue posible conectar con DataCrédito: '.$e->getMessage()];
        }
        $ok = $respuesta->successful() && strpos($respuesta->body(), $this->config['operacion']) !== false;
        return ['ok' => $ok, 'codigo' => $ok ? null : 'respuesta_invalida', 'mensaje' => $ok ? 'Conexión establecida: el servicio expone '.$this->config['operacion'].'.' : 'El servicio respondió HTTP '.$respuesta->status().' sin la operación '.$this->config['operacion'].'.'];
    }

    public function solicitud(array $tercero, $tipo)
    {
        $campos = [
            'clave' => $this->config['password'],
            'identificacion' => $tercero['documento'],
            'primerApellido' => $tercero['primerApellido'],
            'producto' => $this->config['producto'],
            'tipoIdentificacion' => $tipo,
            'usuario' => $this->config['usuario'],
        ];
        if (trim((string) ($this->config['codigo_suscriptor'] ?? '')) !== '') {
            $campos['codigoSuscriptor'] = $this->config['codigo_suscriptor'];
        }
        $cuerpo = '';
        foreach ($campos as $campo => $valor) {
            $cuerpo .= '<'.$campo.'>'.htmlspecialchars((string) $valor, ENT_XML1).'</'.$campo.'>';
        }
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="'.htmlspecialchars($this->config['namespace'], ENT_XML1).'"><soapenv:Header/><soapenv:Body><ws:'.$this->config['operacion'].'><solicitud>'.$cuerpo.'</solicitud></ws:'.$this->config['operacion'].'></soapenv:Body></soapenv:Envelope>';
    }

    public function consultar(array $tercero): ResultadoCentral
    {
        $tipo = $this->config['tipos_documento'][(int) $tercero['tipoDocumento']] ?? null;
        if ($tipo === null) {
            throw new CentralException('datos_invalidos', 'El tipo de documento del tercero no está homologado para DataCrédito.');
        }
        $http = $this->http();
        try {
            $respuesta = $http->withHeaders(['SOAPAction' => '""'])->withBody($this->solicitud($tercero, $tipo), 'text/xml; charset=utf-8')->post($this->config['endpoint']);
        } catch (Throwable $e) {
            throw new CentralException('conexion', 'No fue posible conectar con DataCrédito: '.$e->getMessage());
        }
        if (!$respuesta->successful()) {
            throw new CentralException('conexion', 'DataCrédito respondió HTTP '.$respuesta->status().'.');
        }
        return $this->interpretar($respuesta->body());
    }

    public function interpretar($xml)
    {
        $informe = $this->informe($xml);
        if ($informe === null) {
            throw new CentralException('respuesta_invalida', 'La respuesta de DataCrédito no contiene un informe de historia de crédito.');
        }
        $factor = empty($this->config['montos_en_miles']) ? 1 : 1000;
        return new ResultadoCentral('datacredito', array_merge($this->mapear($informe, $factor), [
            'unidadOrigen' => $factor === 1000 ? 'miles_de_pesos' : 'pesos',
        ]), $xml);
    }

    private function cargar($xml)
    {
        $previo = libxml_use_internal_errors(true);
        $raiz = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        return $raiz === false ? null : $raiz;
    }

    private function informe($xml)
    {
        $raiz = $this->cargar($xml);
        if ($raiz === null) {
            return null;
        }
        $informe = $raiz->xpath('//*[local-name()="Informe"]');
        if ($informe) {
            return $informe[0];
        }
        foreach ($raiz->xpath('//*[not(*)]') as $hoja) {
            $texto = trim((string) $hoja);
            if ($texto !== '' && $texto[0] === '<' && ($interno = $this->cargar($texto)) && ($informe = $interno->xpath('//*[local-name()="Informe"]'))) {
                return $informe[0];
            }
        }
        return null;
    }

    private function atributo(SimpleXMLElement $nodo = null, $nombre = null)
    {
        return $nodo === null ? null : ResultadoCentral::texto($nodo[$nombre] ?? null);
    }

    private function primerHijo(SimpleXMLElement $nodo, $ruta)
    {
        $nodos = $nodo->xpath($ruta);
        return $nodos ? $nodos[0] : null;
    }

    public function mapear(SimpleXMLElement $informe, $factor)
    {
        $persona = $this->primerHijo($informe, '*[local-name()="NaturalNacional" or local-name()="NaturalExtranjera"]');
        $identificacion = $persona ? $this->primerHijo($persona, '*[local-name()="Identificacion"]') : null;
        $edad = $persona ? $this->primerHijo($persona, '*[local-name()="Edad"]') : null;
        $score = $this->primerHijo($informe, '*[local-name()="Score"]');
        $obligaciones = [];
        foreach (self::CUENTAS as $elemento => $tipo) {
            foreach ($informe->xpath('*[local-name()="'.$elemento.'"]') as $cuenta) {
                $valor = $this->primerHijo($cuenta, '*[local-name()="Valores"]/*[local-name()="Valor"]');
                $valorMora = ResultadoCentral::monto($this->atributo($valor, 'saldoMora'), $factor);
                $diasMora = ResultadoCentral::entero($this->atributo($valor, 'diasMora'));
                $obligaciones[] = [
                    'entidad' => $this->atributo($cuenta, 'entidad'),
                    'tipo' => $tipo,
                    'sector' => self::SECTORES[$this->atributo($cuenta, 'sector')] ?? $this->atributo($cuenta, 'sector'),
                    'estado' => ($valorMora > 0 || $diasMora > 0) ? 'mora' : 'al_dia',
                    'numero' => $this->atributo($cuenta, 'numero'),
                    'calidad' => $this->atributo($cuenta, 'calidad'),
                    'estadoReportado' => $this->atributo($this->primerHijo($cuenta, '*[local-name()="Estados"]/*[local-name()="EstadoCuenta"]'), 'codigo'),
                    'saldo' => ResultadoCentral::monto($this->atributo($valor, 'saldoActual'), $factor),
                    'cuota' => ResultadoCentral::monto($this->atributo($valor, 'cuota'), $factor),
                    'valorInicial' => ResultadoCentral::monto($this->atributo($valor, 'valorInicial'), $factor),
                    'valorMora' => $valorMora,
                    'diasMora' => $diasMora,
                    'cuotasMora' => ResultadoCentral::entero($this->atributo($valor, 'cuotasMora')),
                    'fechaApertura' => $this->atributo($cuenta, 'fechaApertura'),
                    'fechaCorte' => $this->atributo($valor, 'fecha'),
                ];
            }
        }
        $huella = [];
        foreach ($informe->xpath('*[local-name()="Consulta"]') as $consulta) {
            $huella[] = ['entidad' => $this->atributo($consulta, 'entidad'), 'fecha' => $this->atributo($consulta, 'fecha'), 'motivo' => $this->atributo($consulta, 'razon')];
        }
        $puntaje = ResultadoCentral::entero($this->atributo($score, 'puntaje'));
        return [
            'datosBasicos' => [
                'nombre' => $this->atributo($persona, 'nombreCompleto') ?: trim($this->atributo($persona, 'nombres').' '.$this->atributo($persona, 'primerApellido').' '.$this->atributo($persona, 'segundoApellido')),
                'documento' => $this->atributo($identificacion, 'numero') ?: $this->atributo($informe, 'identificacionDigitada'),
                'estadoDocumento' => $this->atributo($identificacion, 'estado'),
                'fechaExpedicion' => $this->atributo($identificacion, 'fechaExpedicion'),
                'lugarExpedicion' => $this->atributo($identificacion, 'ciudad'),
                'rangoEdad' => $edad ? trim($this->atributo($edad, 'min').'-'.$this->atributo($edad, 'max'), '-') : null,
                'fechaInforme' => $this->atributo($informe, 'fechaConsulta'),
                'codigoRespuesta' => $this->atributo($informe, 'respuesta'),
            ],
            'score' => $puntaje === null ? null : ['valor' => $puntaje, 'rangoMin' => null, 'rangoMax' => null, 'nivel' => null],
            'obligaciones' => $obligaciones,
            'huella' => $huella,
        ];
    }
}
