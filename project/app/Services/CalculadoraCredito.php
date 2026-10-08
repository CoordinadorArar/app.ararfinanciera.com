<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CalculadoraCredito
{
    private $reglas;
    private $tasa;

    public function __construct(array $reglas, $tasa)
    {
        $this->reglas = array_map(function ($regla) {
            return (array) $regla;
        }, $reglas);
        $this->tasa = is_numeric($tasa) ? (float) $tasa : null;
    }

    public static function paraPagaduria($idPagaduria)
    {
        $reglas = DB::table('PagaduriasReglasEdad')->where('IdPagaduria', $idPagaduria)->orderBy('EdadMin')->get()->all();
        return new self($reglas, self::valorVariable('TasaInteres'));
    }

    public static function valorVariable($nombre)
    {
        return DB::table('ValoresVariables')->where('NombreValorVariable', $nombre)->value('ValorVariable');
    }

    public static function edad($fechaNacimiento, $hoy = null)
    {
        $nacimiento = Carbon::parse($fechaNacimiento)->startOfDay();
        $hoy = $hoy ? Carbon::parse($hoy)->startOfDay() : Carbon::today();
        if ($nacimiento->greaterThan($hoy)) {
            throw new InvalidArgumentException('La fecha de nacimiento no puede ser futura.');
        }
        return $nacimiento->diffInYears($hoy);
    }

    public function regla($edad)
    {
        foreach ($this->reglas as $regla) {
            if ($edad >= $regla['EdadMin'] && $edad <= $regla['EdadMax']) {
                return $regla;
            }
        }
        throw new InvalidArgumentException('La edad no está dentro de las reglas de la pagaduría.');
    }

    public function plazos($fechaNacimiento, $hoy = null)
    {
        $edad = self::edad($fechaNacimiento, $hoy);
        $regla = $this->regla($edad);
        return [
            'edad' => $edad,
            'plazoMaximo' => (int) $regla['PlazoMaximo'],
            'porcentajeSeguro' => (float) $regla['PorcentajeSeguro'],
            'plazos' => range(1, (int) $regla['PlazoMaximo']),
        ];
    }

    public static function factor($tasa, $plazo)
    {
        $i = $tasa / 100;
        if ($i == 0) {
            return 1 / $plazo;
        }
        $potencia = pow(1 + $i, $plazo);
        return $i * $potencia / ($potencia - 1);
    }

    public function calcular($monto, $plazo, $fechaNacimiento, $hoy = null)
    {
        $monto = EvaluadorFormula::limpiarValor($monto);
        if ($monto === null || $monto <= 0) {
            throw new InvalidArgumentException('El valor del crédito debe ser mayor a cero.');
        }
        if (!preg_match('/^\d+$/', trim((string) $plazo)) || (int) $plazo < 1) {
            throw new InvalidArgumentException('El número de cuotas debe ser un entero mayor o igual a 1.');
        }
        if ($this->tasa === null || $this->tasa < 0) {
            throw new InvalidArgumentException('La tasa de interés configurada no es válida.');
        }
        $plazo = (int) $plazo;
        $monto = self::redondear($monto);
        $limite = $this->plazos($fechaNacimiento, $hoy);
        if ($plazo > $limite['plazoMaximo']) {
            throw new InvalidArgumentException('El plazo máximo para la edad del cliente es de '.$limite['plazoMaximo'].' meses.');
        }
        $factor = self::factor($this->tasa, $plazo);
        $cuota = self::redondear($monto * $factor);
        $seguro = self::redondear($monto * $limite['porcentajeSeguro']);
        return [
            'monto' => $monto,
            'plazo' => $plazo,
            'tasa' => $this->tasa,
            'edad' => $limite['edad'],
            'plazoMaximo' => $limite['plazoMaximo'],
            'porcentajeSeguro' => $limite['porcentajeSeguro'],
            'factor' => $factor,
            'cuota' => $cuota,
            'seguro' => $seguro,
            'cuotaTotal' => $cuota + $seguro,
        ];
    }

    public static function montoMaximo($cupo, $factor, $porcentajeSeguro)
    {
        return max(0, (int) floor($cupo / ($factor + $porcentajeSeguro)));
    }

    public static function bloqueoCupo(array $calculo, $cupo)
    {
        if ($calculo['cuotaTotal'] <= $cupo) {
            return null;
        }
        return [
            'message' => 'La cuota total ($ '.number_format($calculo['cuotaTotal'], 0, ',', '.').') supera el cupo disponible ($ '.number_format($cupo, 0, ',', '.').').',
            'montoMaximo' => self::montoMaximo($cupo, $calculo['factor'], $calculo['porcentajeSeguro']),
            'cuota' => $calculo['cuotaTotal'],
            'cupo' => $cupo,
        ];
    }

    public static function amortizacion(array $calculo)
    {
        $i = $calculo['tasa'] / 100;
        $saldo = $calculo['monto'];
        $filas = [];
        for ($numero = 1; $numero <= $calculo['plazo']; $numero++) {
            $interes = self::redondear($saldo * $i);
            $capital = $numero == $calculo['plazo'] ? $saldo : $calculo['cuota'] - $interes;
            $saldo -= $capital;
            $filas[] = [
                'numero' => $numero,
                'cuota' => $capital + $interes,
                'capital' => $capital,
                'interes' => $interes,
                'seguro' => $calculo['seguro'],
                'cuotaTotal' => $capital + $interes + $calculo['seguro'],
                'saldo' => $saldo,
            ];
        }
        return $filas;
    }

    public static function tipoFormula($usaRegla, $umbral, $ingresos, $salarioMinimo)
    {
        if (!$usaRegla) {
            return '';
        }
        if (!is_numeric($ingresos) || !is_numeric($salarioMinimo) || !is_numeric($umbral)) {
            throw new InvalidArgumentException('Los ingresos y el salario mínimo deben ser numéricos para seleccionar la fórmula.');
        }
        return (float) $ingresos > (float) $umbral * (float) $salarioMinimo ? '%' : '$';
    }

    public static function formulaPagaduria($idPagaduria, $ingresos)
    {
        $pagaduria = DB::table('Pagadurias')->where('IdPagaduria', $idPagaduria)->first();
        if (!$pagaduria) {
            throw new InvalidArgumentException('La pagaduría seleccionada no existe.');
        }
        if (!$pagaduria->EstadoPagaduria) {
            throw new InvalidArgumentException('La pagaduría seleccionada está inactiva.');
        }
        $tipo = self::tipoFormula($pagaduria->UsaReglaSMMLV, $pagaduria->UmbralSMMLV, EvaluadorFormula::limpiarValor($ingresos), self::valorVariable('SalarioMinimoMensual'));
        $consulta = DB::table('CuposConfigCalculos')->where('IdPagaduria', $idPagaduria)->whereRaw("LTRIM(RTRIM(ISNULL(Configuracion,''))) <> ''");
        if ($tipo !== '') {
            $consulta->where('TipoDescuentoMaximo', $tipo);
        }
        $formula = $consulta->orderBy('IdConfigCalculo')->first();
        if (!$formula) {
            throw new InvalidArgumentException('La pagaduría seleccionada no tiene una fórmula de cupo configurada'.($tipo !== '' ? ' para ese nivel de ingresos.' : '.'));
        }
        return (object) array_merge((array) $pagaduria, (array) $formula);
    }

    private static function redondear($valor)
    {
        return (int) round($valor);
    }
}
