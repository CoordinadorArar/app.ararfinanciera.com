<?php

namespace Tests\Unit;

use App\Services\CalculadoraCredito;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalculadoraCreditoTest extends TestCase
{
    const HOY = '2026-10-07';

    private function calculadora($tasa = 2.13)
    {
        return new CalculadoraCredito([
            ['EdadMin' => 18, 'EdadMax' => 70, 'PlazoMaximo' => 120, 'PorcentajeSeguro' => 0.003],
            ['EdadMin' => 71, 'EdadMax' => 74, 'PlazoMaximo' => 48, 'PorcentajeSeguro' => 0.003],
            ['EdadMin' => 75, 'EdadMax' => 99, 'PlazoMaximo' => 48, 'PorcentajeSeguro' => 0.005625],
        ], $tasa);
    }

    public function test_factor_con_tasa_cero_es_uno_sobre_n()
    {
        $this->assertSame(1 / 24, CalculadoraCredito::factor(0, 24));
    }

    public function test_factor_de_anualidad()
    {
        $i = 0.0213;
        $esperado = $i * pow(1 + $i, 12) / (pow(1 + $i, 12) - 1);
        $this->assertEqualsWithDelta($esperado, CalculadoraCredito::factor(2.13, 12), 1e-12);
    }

    public function test_regla_por_edad_en_las_fronteras()
    {
        $calculadora = $this->calculadora();
        $this->assertSame(120, $calculadora->plazos('1956-10-07', self::HOY)['plazoMaximo']);
        $this->assertSame(48, $calculadora->plazos('1955-10-07', self::HOY)['plazoMaximo']);
        $this->assertSame(0.003, $calculadora->plazos('1952-10-07', self::HOY)['porcentajeSeguro']);
        $this->assertSame(0.005625, $calculadora->plazos('1951-10-07', self::HOY)['porcentajeSeguro']);
        $this->assertSame(range(1, 48), $calculadora->plazos('1951-10-07', self::HOY)['plazos']);
    }

    public function test_edad_fuera_de_las_reglas()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La edad no está dentro de las reglas de la pagaduría.');
        $this->calculadora()->calcular(1000000, 12, '2010-01-01', self::HOY);
    }

    public function test_edad_exacta_alrededor_del_cumpleanos()
    {
        $this->assertSame(69, CalculadoraCredito::edad('1956-10-08', self::HOY));
        $this->assertSame(70, CalculadoraCredito::edad('1956-10-07', self::HOY));
        $this->assertSame(70, CalculadoraCredito::edad('1956-10-06', self::HOY));
        $this->assertSame(120, $this->calculadora()->plazos('1955-10-08', self::HOY)['plazoMaximo']);
        $this->assertSame(48, $this->calculadora()->plazos('1955-10-06', self::HOY)['plazoMaximo']);
    }

    public function test_plazo_mayor_al_maximo()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El plazo máximo para la edad del cliente es de 48 meses.');
        $this->calculadora()->calcular(1000000, 49, '1952-01-01', self::HOY);
    }

    public function test_validaciones_de_monto_plazo_y_tasa()
    {
        foreach ([[0, 12, 2], ['-5', 12, 2], [1000000, 0, 2], [1000000, '1.5', 2], [1000000, 12, -1]] as $caso) {
            try {
                $this->calculadora($caso[2])->calcular($caso[0], $caso[1], '1980-01-01', self::HOY);
                $this->fail('Debió rechazar '.json_encode($caso));
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_calculo_y_redondeo_a_pesos()
    {
        $calculo = $this->calculadora()->calcular('$ 10,000,000', 60, '1980-01-01', self::HOY);
        $esperada = (int) round(10000000 * CalculadoraCredito::factor(2.13, 60));
        $this->assertSame($esperada, $calculo['cuota']);
        $this->assertSame(30000, $calculo['seguro']);
        $this->assertSame($esperada + 30000, $calculo['cuotaTotal']);
        $this->assertSame(10000000, $calculo['monto']);
        $this->assertIsInt($calculo['cuota']);
        $this->assertSame(17, $this->calculadora(0)->calcular(100, 6, '1980-01-01', self::HOY)['cuota']);
    }

    public function test_amortizacion_cierra_en_cero()
    {
        foreach ([2.13, 0] as $tasa) {
            $calculo = $this->calculadora($tasa)->calcular(7654321, 37, '1980-01-01', self::HOY);
            $tabla = CalculadoraCredito::amortizacion($calculo);
            $this->assertCount(37, $tabla);
            $this->assertSame(0, end($tabla)['saldo']);
            $this->assertSame(7654321, array_sum(array_column($tabla, 'capital')));
            $this->assertSame($calculo['cuota'], $tabla[0]['cuota']);
            $this->assertSame($calculo['cuotaTotal'], $tabla[0]['cuotaTotal']);
        }
    }

    public function test_cuota_mayor_al_cupo_bloquea_con_monto_maximo()
    {
        $calculo = $this->calculadora()->calcular(10000000, 60, '1980-01-01', self::HOY);
        $this->assertNull(CalculadoraCredito::bloqueoCupo($calculo, $calculo['cuotaTotal']));
        $bloqueo = CalculadoraCredito::bloqueoCupo($calculo, 200000);
        $this->assertSame(200000, $bloqueo['cupo']);
        $this->assertSame($calculo['cuotaTotal'], $bloqueo['cuota']);
        $this->assertSame((int) floor(200000 / (CalculadoraCredito::factor(2.13, 60) + 0.003)), $bloqueo['montoMaximo']);
        $this->assertNotEmpty($bloqueo['message']);
        $ajustado = $this->calculadora()->calcular($bloqueo['montoMaximo'], 60, '1980-01-01', self::HOY);
        $this->assertLessThanOrEqual(200000 + 1, $ajustado['cuotaTotal']);
    }

    public function test_monto_maximo_con_tasa_cero_y_cupo_negativo()
    {
        $this->assertSame(1000, CalculadoraCredito::montoMaximo(100, 1 / 10, 0));
        $this->assertSame(0, CalculadoraCredito::montoMaximo(-500, 0.05, 0.003));
    }

    public function test_seleccion_de_formula_por_regla_smmlv()
    {
        $this->assertSame('', CalculadoraCredito::tipoFormula(0, 2, 9000000, 1423500));
        $this->assertSame('$', CalculadoraCredito::tipoFormula(1, 2, 2847000, 1423500));
        $this->assertSame('%', CalculadoraCredito::tipoFormula(1, 2, 2847001, 1423500));
        $this->assertSame('$', CalculadoraCredito::tipoFormula(1, 3, 4000000, 1423500));
        $this->expectException(InvalidArgumentException::class);
        CalculadoraCredito::tipoFormula(1, 2, null, 1423500);
    }
}
