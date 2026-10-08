<?php

namespace Tests\Unit;

use App\Services\FlujoProceso;
use PHPUnit\Framework\TestCase;

class FlujoProcesoTest extends TestCase
{
    const ROLES = [1, 2, 3, 4, 5, 6, 7, 8];

    const PERMITIDAS = [
        '1-2' => [1, 2, 6],
        '2-3' => [1, 2, 4, 5],
        '3-4' => [1, 2, 5],
        '4-5' => [1, 2, 3],
        '1-0' => [1, 2, 6],
        '2-0' => [1, 2, 4, 5],
        '3-0' => [1, 2, 5],
        '4-0' => [1, 2, 3],
    ];

    private function flujo()
    {
        return new FlujoProceso(require __DIR__.'/../../config/procesos.php');
    }

    private function condiciones()
    {
        return ['tratamientoCompleto' => true, 'documentosPendientes' => [], 'centralesVigente' => true];
    }

    public function test_matriz_de_transiciones_por_rol()
    {
        $flujo = $this->flujo();
        foreach (range(0, 5) as $actual) {
            foreach (range(0, 5) as $nuevo) {
                foreach (self::ROLES as $rol) {
                    $error = $flujo->validar($actual, $nuevo, [$rol], 3, $this->condiciones());
                    $clave = $actual.'-'.$nuevo;
                    if (!isset(self::PERMITIDAS[$clave])) {
                        $this->assertSame(422, $error[0] ?? null, "$clave rol $rol debe ser transición inexistente");
                    } elseif (in_array($rol, self::PERMITIDAS[$clave])) {
                        $this->assertNull($error, "$clave rol $rol debe permitirse");
                    } else {
                        $this->assertSame(403, $error[0] ?? null, "$clave rol $rol debe negarse por rol");
                    }
                }
            }
        }
    }

    public function test_administrador_puede_toda_transicion_definida()
    {
        $flujo = $this->flujo();
        foreach (array_keys(self::PERMITIDAS) as $clave) {
            [$actual, $nuevo] = explode('-', $clave);
            $this->assertNull($flujo->validar($actual, $nuevo, [1], 2, $this->condiciones()));
        }
    }

    public function test_varios_roles_suman_permisos()
    {
        $this->assertNull($this->flujo()->validar(4, 5, [6, 3], null, $this->condiciones()));
        $this->assertSame(403, $this->flujo()->validar(4, 5, [6, 7], null, $this->condiciones())[0]);
    }

    public function test_no_se_puede_salir_de_estados_finales_ni_saltar_etapas()
    {
        $flujo = $this->flujo();
        $this->assertSame(422, $flujo->validar(5, 0, [1], 1, $this->condiciones())[0]);
        $this->assertSame(422, $flujo->validar(0, 1, [1], null, $this->condiciones())[0]);
        $this->assertSame(422, $flujo->validar(2, 4, [1], null, $this->condiciones())[0]);
        $this->assertSame(422, $flujo->validar(3, 2, [1], null, $this->condiciones())[0]);
        $this->assertSame(422, $flujo->validar(3, 3, [1], null, $this->condiciones())[0]);
    }

    public function test_rechazo_exige_motivo()
    {
        $flujo = $this->flujo();
        foreach ([1, 2, 3, 4] as $actual) {
            $error = $flujo->validar($actual, 0, [1], null, $this->condiciones());
            $this->assertSame(422, $error[0]);
            $this->assertStringContainsString('motivo', $error[1]);
            $this->assertNull($flujo->validar($actual, 0, [1], 4, $this->condiciones()));
        }
    }

    public function test_avanzar_no_exige_motivo()
    {
        $this->assertNull($this->flujo()->validar(4, 5, [3], null, $this->condiciones()));
    }

    public function test_paso_a_documentos_exige_tratamiento_completo()
    {
        $error = $this->flujo()->validar(2, 3, [5], null, ['tratamientoCompleto' => false]);
        $this->assertSame(422, $error[0]);
        $this->assertStringContainsString('tratamiento de datos', $error[1]);
        $this->assertNull($this->flujo()->validar(2, 3, [5], null, ['tratamientoCompleto' => true, 'centralesVigente' => true]));
    }

    public function test_paso_a_documentos_exige_consulta_de_centrales_vigente()
    {
        foreach ([false, null] as $vigente) {
            $this->assertSame([422, 'Consulta al menos una central de riesgo antes de aprobar.'], $this->flujo()->validar(2, 3, [5], null, ['tratamientoCompleto' => true, 'centralesVigente' => $vigente]));
        }
        $this->assertStringContainsString('tratamiento de datos', $this->flujo()->validar(2, 3, [5], null, ['tratamientoCompleto' => false, 'centralesVigente' => false])[1]);
        $this->assertNull($this->flujo()->validar(2, 0, [5], 1, ['centralesVigente' => false]));
    }

    public function test_paso_a_aprobacion_exige_documentos_aprobados()
    {
        $error = $this->flujo()->validar(3, 4, [5], null, ['documentosPendientes' => ['Cedula', 'LibranzaPagare']]);
        $this->assertSame(422, $error[0]);
        $this->assertStringContainsString('Cedula, LibranzaPagare', $error[1]);
        $this->assertNull($this->flujo()->validar(3, 4, [5], null, ['documentosPendientes' => []]));
    }

    public function test_el_rol_se_valida_antes_que_las_precondiciones()
    {
        $this->assertSame(403, $this->flujo()->validar(3, 4, [6], null, ['documentosPendientes' => ['Cedula']])[0]);
    }

    public function test_visibilidad_por_rol()
    {
        $flujo = $this->flujo();
        $this->assertFalse($flujo->soloPropios([1]));
        $this->assertFalse($flujo->soloPropios([1, 6]));
        $this->assertFalse($flujo->soloPropios([2]));
        $this->assertTrue($flujo->soloPropios([6]));
        $this->assertFalse($flujo->soloPropios([6, 5]));
        $this->assertTrue($flujo->soloPropios([]));
        $this->assertSame([0, 1, 2, 3, 4, 5], $flujo->estadosVisibles([1]));
        $this->assertContains(4, $flujo->estadosVisibles([3]));
        $this->assertSame([2, 3], $flujo->estadosVisibles([4]));
        $this->assertSame([2, 3, 4], $flujo->estadosVisibles([4, 5]));
        $this->assertSame([], $flujo->estadosVisibles([7]));
    }

    public function test_acciones_y_accion_principal()
    {
        $flujo = $this->flujo();
        $this->assertSame('registro', $flujo->accionPrincipal(1, [6]));
        $this->assertSame('centrales', $flujo->accionPrincipal(2, [4]));
        $this->assertSame('cargarDocumentos', $flujo->accionPrincipal(3, [6]));
        $this->assertSame('aprobarDocumentos', $flujo->accionPrincipal(3, [5]));
        $this->assertSame('aprobarCredito', $flujo->accionPrincipal(4, [3]));
        $this->assertNull($flujo->accionPrincipal(4, [6]));
        $this->assertNull($flujo->accionPrincipal(5, [1]));
        $this->assertSame(['aprobarCredito', 'editarCredito', 'rechazar'], $flujo->accionesDisponibles(4, [3]));
        $this->assertSame(['reenviarCorreo'], $flujo->accionesDisponibles(5, [3]));
        $this->assertNotContains('rechazar', $flujo->accionesDisponibles(0, [1]));
        $this->assertSame([], $flujo->accionesDisponibles(3, [7]));
        $this->assertTrue($flujo->puedeAccion('cargarDocumentos', 3, [4]));
        $this->assertFalse($flujo->puedeAccion('cargarDocumentos', 4, [1]));
    }

    public function test_nombres_de_estado()
    {
        $flujo = $this->flujo();
        $this->assertSame('Aprobación de crédito', $flujo->nombreEstado(4));
        $this->assertNull($flujo->nombreEstado(null));
    }
}
