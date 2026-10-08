<?php

namespace Tests\Unit;

use App\Services\EvaluadorFormula;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EvaluadorFormulaTest extends TestCase
{
    public function test_evalua_formula_valida()
    {
        $resultado = EvaluadorFormula::evaluar('(|ingresos|-|salud|)|/|2|-|deducciones', ['ingresos' => '1423500', 'salud' => '$57,000', 'deducciones' => '100000']);

        $this->assertSame(583250, $resultado['resultado']);
        $this->assertSame('(1423500-57000)/2-100000', $resultado['operacion']);
    }

    public function test_x_se_interpreta_como_multiplicacion_y_digitos_consecutivos_forman_un_numero()
    {
        $resultado = EvaluadorFormula::evaluar('ingresos|x|1|0|-|5', ['ingresos' => '3']);

        $this->assertSame(25, $resultado['resultado']);
    }

    public function test_respeta_precedencia()
    {
        $resultado = EvaluadorFormula::evaluar('2|+|3|*|4', []);

        $this->assertSame(14, $resultado['resultado']);
    }

    public function test_rechaza_inyeccion_de_codigo()
    {
        $this->expectException(InvalidArgumentException::class);

        EvaluadorFormula::evaluar("1;system('dir')", []);
    }

    public function test_rechaza_inyeccion_en_valor()
    {
        $this->expectException(InvalidArgumentException::class);

        EvaluadorFormula::evaluar('ingresos|-|1', ['ingresos' => "1;system('dir')"]);
    }

    public function test_rechaza_parentesis_desbalanceados()
    {
        $this->expectException(InvalidArgumentException::class);

        EvaluadorFormula::evaluar('(|ingresos|-|1', ['ingresos' => '10']);
    }

    public function test_rechaza_parentesis_de_cierre_sobrante()
    {
        $this->expectException(InvalidArgumentException::class);

        EvaluadorFormula::evaluar('ingresos|-|1|)', ['ingresos' => '10']);
    }

    public function test_rechaza_division_por_cero()
    {
        $this->expectException(InvalidArgumentException::class);

        EvaluadorFormula::evaluar('ingresos|/|(|salud|-|salud|)', ['ingresos' => '10', 'salud' => '5']);
    }

    public function test_reemplaza_por_token_exacto_con_nombres_solapados()
    {
        $resultado = EvaluadorFormula::evaluar('ingresos|+|ingresosExtras', ['ingresos' => '100', 'ingresosExtras' => '50']);

        $this->assertSame(150, $resultado['resultado']);
        $this->assertSame('100+50', $resultado['operacion']);
    }

    public function test_acepta_nombres_con_tildes_y_enie()
    {
        $resultado = EvaluadorFormula::evaluar('ingresos|-|bonificación|-|compañia', ['ingresos' => '1000', 'bonificación' => '100', 'compañia' => '50']);

        $this->assertSame(850, $resultado['resultado']);
    }

    public function test_rechaza_nombre_no_presente_en_el_mapa()
    {
        $this->expectException(InvalidArgumentException::class);

        EvaluadorFormula::evaluar('ingresos|+|ingresosExtras', ['ingresos' => '100']);
    }

    public function test_rechaza_valor_no_numerico()
    {
        $this->expectException(InvalidArgumentException::class);

        EvaluadorFormula::evaluar('ingresos|-|1', ['ingresos' => 'abc']);
    }

    public function test_validar_configuracion_sintactica()
    {
        $this->assertTrue(EvaluadorFormula::validar('(|ingresos|-|salud|)|/|2', ['ingresos', 'salud']));

        $this->expectException(InvalidArgumentException::class);
        EvaluadorFormula::validar('(|ingresos|-|)|/|2', ['ingresos']);
    }

    public function test_validar_puede_ignorar_mayusculas()
    {
        $this->assertTrue(EvaluadorFormula::validar('ingresos|+|distincion|X|2', ['ingresos', 'Distincion'], true));

        $this->expectException(InvalidArgumentException::class);
        EvaluadorFormula::validar('ingresos|+|distincion', ['ingresos', 'Distincion']);
    }

    public function test_valores_operacion_recupera_rubros_guardados()
    {
        $configuracion = '(|ingresos|*|0.5|)|-|serviciosMedicos|X|1|0|-|deducciones';
        $evaluacion = EvaluadorFormula::evaluar($configuracion, ['ingresos' => 2000000, 'serviciosMedicos' => 68000, 'deducciones' => -5]);

        $this->assertSame(['ingresos' => '2000000', 'serviciosMedicos' => '68000', 'deducciones' => '-5'], EvaluadorFormula::valoresOperacion($configuracion, $evaluacion['operacion']));
        $this->assertSame([], EvaluadorFormula::valoresOperacion($configuracion, '(1*0.5)'));
        $this->assertSame([], EvaluadorFormula::valoresOperacion(null, null));
    }
}
