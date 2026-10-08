<?php

namespace App\Services\Centrales;

use SoapFault;

class TransUnionProveedor implements ProveedorCentral
{
    const ESTADOS_SECTOR = ['AlDia' => 'al_dia', 'EnMora' => 'mora', 'Mora' => 'mora', 'Extinguidas' => 'cerrada', 'Extinguida' => 'cerrada'];

    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function configurado(): bool
    {
        foreach (['wsdl', 'usuario', 'password', 'key_path', 'cert_path'] as $clave) {
            if (trim((string) ($this->config[$clave] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    private function cliente()
    {
        if (!$this->configurado()) {
            throw new CentralException('sin_credenciales', 'TransUnion no tiene credenciales configuradas.');
        }
        if (!class_exists('SoapClient')) {
            throw new CentralException('conexion', 'La extensión SOAP de PHP no está habilitada en el servidor.');
        }
        if (!is_readable($this->config['key_path']) || !is_readable($this->config['cert_path'])) {
            throw new CentralException('sin_credenciales', 'No se encuentra la llave o el certificado de TransUnion.');
        }
        $timeout = (int) ($this->config['timeout'] ?? 30);
        $verificar = (bool) ($this->config['verify_peer'] ?? true);
        try {
            return new TransUnionSoapClient($this->config['wsdl'], [
                'login' => $this->config['usuario'],
                'password' => $this->config['password'],
                'cache_wsdl' => WSDL_CACHE_NONE,
                'exceptions' => true,
                'trace' => false,
                'connection_timeout' => $timeout,
                'stream_context' => stream_context_create([
                    'ssl' => ['verify_peer' => $verificar, 'verify_peer_name' => $verificar],
                    'http' => ['timeout' => $timeout],
                ]),
            ], $this->config['key_path'], $this->config['cert_path']);
        } catch (SoapFault $e) {
            throw new CentralException('conexion', 'No fue posible conectar con TransUnion: '.$e->getMessage());
        }
    }

    public function probarConexion(): array
    {
        try {
            $funciones = implode(' ', $this->cliente()->__getFunctions() ?: []);
        } catch (CentralException $e) {
            return ['ok' => false, 'codigo' => $e->codigo, 'mensaje' => $e->getMessage()];
        }
        $ok = strpos($funciones, 'consultaXml') !== false;
        return ['ok' => $ok, 'codigo' => $ok ? null : 'respuesta_invalida', 'mensaje' => $ok ? 'Conexión establecida: el servicio expone consultaXml.' : 'El servicio no expone la operación consultaXml.'];
    }

    public function consultar(array $tercero): ResultadoCentral
    {
        $tipo = $this->config['tipos_documento'][(int) $tercero['tipoDocumento']] ?? null;
        if ($tipo === null) {
            throw new CentralException('datos_invalidos', 'El tipo de documento del tercero no está homologado para TransUnion.');
        }
        $cliente = $this->cliente();
        $anterior = ini_set('default_socket_timeout', (string) (int) ($this->config['timeout'] ?? 30));
        try {
            $respuesta = $cliente->consultaXml([
                'codigoInformacion' => (string) $this->config['codigo_informacion'],
                'motivoConsulta' => (string) $this->config['motivo_consulta'],
                'numeroIdentificacion' => (string) $tercero['documento'],
                'primerApellido' => $tercero['primerApellido'],
                'tipoIdentificacion' => (string) $tipo,
            ]);
        } catch (SoapFault $e) {
            throw new CentralException('conexion', 'TransUnion respondió con error: '.$e->getMessage());
        } finally {
            ini_set('default_socket_timeout', $anterior);
        }
        return $this->interpretar((string) $respuesta);
    }

    public function interpretar($xml)
    {
        $previo = libxml_use_internal_errors(true);
        $raiz = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        $tercero = $raiz === false ? null : ($raiz->getName() === 'Tercero' ? $raiz : $raiz->Tercero);
        if (!$tercero || !$tercero->children()->count()) {
            throw new CentralException('respuesta_invalida', 'La respuesta de TransUnion no contiene la información del tercero.');
        }
        $factor = empty($this->config['montos_en_miles']) ? 1 : 1000;
        return new ResultadoCentral('transunion', [
            'unidadOrigen' => $factor === 1000 ? 'miles_de_pesos' : 'pesos',
            'datosBasicos' => [
                'nombre' => ResultadoCentral::texto($tercero->NombreTitular),
                'documento' => ResultadoCentral::texto($tercero->NumeroIdentificacion),
                'tipoDocumento' => ResultadoCentral::texto($tercero->TipoIdentificacion),
                'estadoDocumento' => ResultadoCentral::texto($tercero->Estado),
                'fechaExpedicion' => ResultadoCentral::texto($tercero->FechaExpedicion),
                'lugarExpedicion' => ResultadoCentral::texto($tercero->LugarExpedicion),
                'rangoEdad' => ResultadoCentral::texto($tercero->RangoEdad),
                'actividadEconomica' => ResultadoCentral::texto($tercero->CodigoCiiu),
                'numeroInforme' => ResultadoCentral::texto($tercero->NumeroInforme),
                'fechaInforme' => ResultadoCentral::texto($tercero->Fecha.' '.$tercero->Hora),
            ],
            'resumen' => $this->resumen($tercero, $factor),
            'score' => $this->score($tercero),
            'obligaciones' => $this->obligaciones($tercero, $factor),
            'huella' => $this->huella($tercero),
        ], $xml);
    }

    private function nodos($nodo, $ruta)
    {
        foreach (explode('/', $ruta) as $paso) {
            if (!isset($nodo->{$paso})) {
                return [];
            }
            $nodo = $nodo->{$paso};
        }
        return $nodo;
    }

    private function primero($nodo, array $campos)
    {
        if (!is_object($nodo)) {
            return null;
        }
        foreach ($campos as $campo) {
            if (($valor = ResultadoCentral::texto($nodo->{$campo})) !== null) {
                return $valor;
            }
        }
        return null;
    }

    private function resumen($tercero, $factor)
    {
        $filas = [];
        foreach ($this->nodos($tercero, 'Consolidado/ResumenPrincipal/Registro') as $registro) {
            $filas[] = [
                'paquete' => ResultadoCentral::texto($registro->PaqueteInformacion),
                'obligaciones' => ResultadoCentral::entero($registro->NumeroObligaciones),
                'saldoTotal' => ResultadoCentral::monto($registro->TotalSaldo, $factor),
                'participacionDeuda' => ResultadoCentral::monto($registro->ParticipacionDeuda),
                'obligacionesAlDia' => ResultadoCentral::entero($registro->NumeroObligacionesDia),
                'saldoAlDia' => ResultadoCentral::monto($registro->SaldoObligacionesDia, $factor),
                'cuotaAlDia' => ResultadoCentral::monto($registro->CuotaObligacionesDia, $factor),
                'obligacionesMora' => ResultadoCentral::entero($registro->CantidadObligacionesMora),
                'saldoMora' => ResultadoCentral::monto($registro->SaldoObligacionesMora, $factor),
                'cuotaMora' => ResultadoCentral::monto($registro->CuotaObligacionesMora, $factor),
                'valorMora' => ResultadoCentral::monto($registro->ValorMora, $factor),
            ];
        }
        return $filas;
    }

    private function score($tercero)
    {
        $nodo = $this->nodos($tercero, 'Score') ?: $this->nodos($tercero, 'Consolidado/Score');
        $valor = ResultadoCentral::entero($this->primero($nodo, ['Puntaje', 'Valor', 'Score']));
        if ($valor === null) {
            return null;
        }
        return [
            'valor' => $valor,
            'rangoMin' => ResultadoCentral::entero($this->primero($nodo, ['RangoMinimo', 'Minimo'])),
            'rangoMax' => ResultadoCentral::entero($this->primero($nodo, ['RangoMaximo', 'Maximo'])),
            'nivel' => $this->primero($nodo, ['Calificacion', 'Nivel']),
        ];
    }

    private function obligaciones($tercero, $factor)
    {
        $obligaciones = [];
        foreach ($tercero->children() as $nombre => $sector) {
            if (!preg_match('/^Sector(.+?)(AlDia|EnMora|Mora|Extinguidas|Extinguida)?$/', $nombre, $partes)) {
                continue;
            }
            foreach ($sector->Obligacion as $obligacion) {
                $valorMora = ResultadoCentral::monto($obligacion->ValorMora, $factor);
                $obligaciones[] = [
                    'entidad' => ResultadoCentral::texto($obligacion->NombreEntidad),
                    'tipo' => $this->primero($obligacion, ['ModalidadCredito', 'TipoContrato', 'LineaCredito']),
                    'sector' => lcfirst($partes[1]),
                    'estado' => self::ESTADOS_SECTOR[$partes[2] ?? ''] ?? ($valorMora > 0 ? 'mora' : 'al_dia'),
                    'numero' => ResultadoCentral::texto($obligacion->NumeroObligacion),
                    'calidad' => ResultadoCentral::texto($obligacion->Calidad),
                    'estadoReportado' => ResultadoCentral::texto($obligacion->EstadoObligacion),
                    'saldo' => ResultadoCentral::monto($this->primero($obligacion, ['SaldoObligacion', 'Saldo', 'ValorSaldo']), $factor),
                    'cuota' => ResultadoCentral::monto($obligacion->ValorCuota, $factor),
                    'valorInicial' => ResultadoCentral::monto($obligacion->ValorInicial, $factor),
                    'valorMora' => $valorMora,
                    'diasMora' => ResultadoCentral::entero($this->primero($obligacion, ['EdadMora', 'AlturaMora', 'DiasMora'])),
                    'cuotasMora' => ResultadoCentral::entero($obligacion->NumeroCuotasMora),
                    'fechaApertura' => ResultadoCentral::texto($obligacion->FechaApertura),
                    'fechaCorte' => ResultadoCentral::texto($obligacion->FechaCorte),
                ];
            }
        }
        return $obligaciones;
    }

    private function huella($tercero)
    {
        $huella = [];
        foreach ($this->nodos($tercero, 'HuellaConsulta/Consulta') as $consulta) {
            $huella[] = [
                'entidad' => ResultadoCentral::texto($consulta->NombreEntidad),
                'fecha' => ResultadoCentral::texto($consulta->FechaConsulta),
                'motivo' => ResultadoCentral::texto($consulta->MotivoConsulta),
            ];
        }
        return $huella;
    }
}
