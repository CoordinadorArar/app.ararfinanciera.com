<?php

namespace App\Services\Centrales;

class ResultadoCentral
{
    public $proveedor;
    public $fechaConsulta;
    public $score;
    public $obligaciones;
    public $huella;
    public $alertas;
    public $datosBasicos;
    public $resumen;
    public $unidadOrigen;
    public $simulado;
    public $cruda;

    public function __construct($proveedor, array $datos, $cruda = null, $simulado = false)
    {
        $this->proveedor = $proveedor;
        $this->fechaConsulta = $datos['fechaConsulta'] ?? date('Y-m-d H:i:s');
        $this->score = $datos['score'] ?? null;
        $this->obligaciones = array_values($datos['obligaciones'] ?? []);
        $this->huella = array_values($datos['huella'] ?? []);
        $this->datosBasicos = $datos['datosBasicos'] ?? [];
        $this->resumen = $datos['resumen'] ?? [];
        $this->unidadOrigen = $datos['unidadOrigen'] ?? 'pesos';
        $this->simulado = (bool) $simulado;
        $this->cruda = $cruda;
        $this->alertas = array_values(array_unique(array_merge($datos['alertas'] ?? [], $this->alertasDerivadas())));
    }

    public function totales()
    {
        $totales = ['obligacionesAlDia' => 0, 'obligacionesMora' => 0, 'saldoTotal' => 0, 'valorMora' => 0, 'cuotaMensual' => 0];
        foreach ($this->obligaciones as $obligacion) {
            if ($obligacion['estado'] === 'cerrada') {
                continue;
            }
            $totales[$obligacion['estado'] === 'mora' ? 'obligacionesMora' : 'obligacionesAlDia']++;
            $totales['saldoTotal'] += $obligacion['saldo'] ?? 0;
            $totales['valorMora'] += $obligacion['valorMora'] ?? 0;
            $totales['cuotaMensual'] += $obligacion['cuota'] ?? 0;
        }
        return $totales;
    }

    private function alertasDerivadas()
    {
        $alertas = [];
        $totales = $this->totales();
        if ($totales['obligacionesMora'] > 0) {
            $alertas[] = 'Tiene '.$totales['obligacionesMora'].' obligación(es) en mora por $'.number_format($totales['valorMora'], 0, ',', '.').'.';
        }
        $estado = $this->datosBasicos['estadoDocumento'] ?? null;
        if ($estado !== null && stripos($estado, 'vigente') === false) {
            $alertas[] = 'El documento figura en estado "'.$estado.'".';
        }
        if (!$this->obligaciones && $this->score === null) {
            $alertas[] = 'Sin historial crediticio reportado.';
        }
        return $alertas;
    }

    public function toArray()
    {
        return [
            'proveedor' => $this->proveedor,
            'fechaConsulta' => $this->fechaConsulta,
            'simulado' => $this->simulado,
            'unidad' => 'pesos',
            'unidadOrigen' => $this->unidadOrigen,
            'score' => $this->score,
            'datosBasicos' => $this->datosBasicos,
            'totales' => $this->totales(),
            'resumen' => $this->resumen,
            'obligaciones' => $this->obligaciones,
            'huella' => $this->huella,
            'alertas' => $this->alertas,
        ];
    }

    public static function monto($valor, $factor = 1)
    {
        $valor = str_replace([',', ' ', '$'], '', trim((string) $valor));
        return is_numeric($valor) ? round($valor * $factor, 2) : null;
    }

    public static function texto($valor)
    {
        $valor = trim((string) $valor);
        return $valor === '' ? null : $valor;
    }

    public static function entero($valor)
    {
        $valor = trim((string) $valor);
        return is_numeric($valor) ? (int) $valor : null;
    }
}
