<?php

namespace App\Services\Centrales;

class SimuladoProveedor implements ProveedorCentral
{
    const PERFILES = ['bueno', 'bueno', 'bueno', 'bueno', 'regular', 'regular', 'regular', 'malo', 'malo', 'sin_historial'];

    public function __construct(array $config = [])
    {
    }

    public function configurado(): bool
    {
        return true;
    }

    public function probarConexion(): array
    {
        return ['ok' => true, 'codigo' => null, 'mensaje' => 'Proveedor simulado: no realiza conexiones externas.'];
    }

    public static function perfil($documento)
    {
        $digitos = preg_replace('/\D/', '', (string) $documento);
        return self::PERFILES[$digitos === '' ? 9 : (int) substr($digitos, -1)];
    }

    private function obligacion($entidad, $tipo, $sector, $estado, $saldo, $cuota, $valorMora = 0, $diasMora = 0, $apertura = '2022-03-15')
    {
        return [
            'entidad' => $entidad, 'tipo' => $tipo, 'sector' => $sector, 'estado' => $estado, 'numero' => null, 'calidad' => 'PRIN',
            'estadoReportado' => $estado === 'cerrada' ? 'CANCELADA' : ($estado === 'mora' ? 'EN MORA' : 'AL DIA'),
            'saldo' => (float) $saldo, 'cuota' => (float) $cuota, 'valorInicial' => null, 'valorMora' => (float) $valorMora,
            'diasMora' => $diasMora, 'cuotasMora' => $diasMora ? intdiv($diasMora, 30) : 0, 'fechaApertura' => $apertura, 'fechaCorte' => '2026-08-31',
        ];
    }

    public function consultar(array $tercero): ResultadoCentral
    {
        $perfil = self::perfil($tercero['documento'] ?? '');
        $escenarios = [
            'bueno' => [
                'score' => ['valor' => 820, 'rangoMin' => 150, 'rangoMax' => 950, 'nivel' => 'alto'],
                'obligaciones' => [
                    $this->obligacion('BANCO SIMULADO A', 'libranza', 'financiero', 'al_dia', 8500000, 350000),
                    $this->obligacion('TARJETAS SIMULADAS B', 'tarjeta_credito', 'financiero', 'al_dia', 1200000, 150000, 0, 0, '2020-07-01'),
                    $this->obligacion('COOPERATIVA SIMULADA C', 'consumo', 'cooperativo', 'cerrada', 0, 0, 0, 0, '2018-01-10'),
                ],
                'huella' => [['entidad' => 'BANCO SIMULADO A', 'fecha' => '2026-08-20', 'motivo' => 'Estudio de crédito']],
            ],
            'regular' => [
                'score' => ['valor' => 610, 'rangoMin' => 150, 'rangoMax' => 950, 'nivel' => 'medio'],
                'obligaciones' => [
                    $this->obligacion('BANCO SIMULADO A', 'libranza', 'financiero', 'al_dia', 12000000, 520000),
                    $this->obligacion('TARJETAS SIMULADAS B', 'tarjeta_credito', 'financiero', 'mora', 2300000, 180000, 180000, 30, '2021-02-01'),
                ],
                'huella' => [
                    ['entidad' => 'BANCO SIMULADO A', 'fecha' => '2026-09-02', 'motivo' => 'Estudio de crédito'],
                    ['entidad' => 'FINANCIERA SIMULADA D', 'fecha' => '2026-08-11', 'motivo' => 'Estudio de crédito'],
                    ['entidad' => 'TARJETAS SIMULADAS B', 'fecha' => '2026-06-30', 'motivo' => 'Seguimiento'],
                ],
            ],
            'malo' => [
                'score' => ['valor' => 380, 'rangoMin' => 150, 'rangoMax' => 950, 'nivel' => 'bajo'],
                'obligaciones' => [
                    $this->obligacion('FINANCIERA SIMULADA D', 'consumo', 'financiero', 'mora', 5400000, 410000, 1640000, 120, '2023-05-20'),
                    $this->obligacion('TELCO SIMULADA E', 'servicios', 'real', 'mora', 380000, 95000, 380000, 180, '2024-01-05'),
                    $this->obligacion('BANCO SIMULADO A', 'libranza', 'financiero', 'al_dia', 3000000, 210000),
                ],
                'huella' => [
                    ['entidad' => 'FINANCIERA SIMULADA D', 'fecha' => '2026-09-25', 'motivo' => 'Estudio de crédito'],
                    ['entidad' => 'BANCO SIMULADO A', 'fecha' => '2026-09-18', 'motivo' => 'Estudio de crédito'],
                    ['entidad' => 'COOPERATIVA SIMULADA C', 'fecha' => '2026-09-01', 'motivo' => 'Estudio de crédito'],
                ],
            ],
            'sin_historial' => ['score' => null, 'obligaciones' => [], 'huella' => []],
        ];
        $datos = $escenarios[$perfil] + [
            'unidadOrigen' => 'pesos',
            'alertas' => ['Resultado simulado (perfil '.$perfil.'): no proviene de una central de riesgo.'],
            'datosBasicos' => [
                'nombre' => $tercero['nombre'] ?? null,
                'documento' => (string) ($tercero['documento'] ?? ''),
                'estadoDocumento' => 'VIGENTE',
                'fechaExpedicion' => $tercero['fechaExpedicion'] ?? null,
                'perfilSimulado' => $perfil,
            ],
        ];
        return new ResultadoCentral('simulado', $datos, json_encode(['simulado' => true, 'perfil' => $perfil, 'documento' => (string) ($tercero['documento'] ?? '')]), true);
    }
}
