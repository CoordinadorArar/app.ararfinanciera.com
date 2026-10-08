<?php

namespace Tests\Feature;

use App\Models\Procesos;
use App\Services\FlujoProceso;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InicioResumenTest extends TestCase
{
    private function resumen(array $roles, $idUsuario = 77)
    {
        $resultado = null;
        $consultas = DB::connection()->pretend(function () use ($roles, $idUsuario, &$resultado) {
            $resultado = Procesos::resumenInicio($idUsuario, $roles, FlujoProceso::instancia());
        });
        return [$resultado, array_column($consultas, 'query')];
    }

    public function test_estados_pendientes_por_rol_segun_transiciones()
    {
        $esperados = [1 => [1, 2, 3, 4], 2 => [1, 2, 3, 4], 3 => [4], 4 => [2], 5 => [2, 3], 6 => [1], 7 => [], 8 => []];
        foreach ($esperados as $rol => $estados) {
            [$resumen] = $this->resumen([$rol]);
            $this->assertSame($estados, $resumen['estadosPendientes'], 'Rol '.$rol);
        }
    }

    public function test_contadores_cubren_los_estados_visibles_en_cero_sin_datos()
    {
        [$resumen] = $this->resumen([4]);
        $this->assertSame([2 => 0, 3 => 0], $resumen['contadores']);
        $this->assertSame([], $resumen['pendientes']);
        [$resumen] = $this->resumen([1]);
        $this->assertSame([0, 1, 2, 3, 4, 5], array_keys($resumen['contadores']));
    }

    public function test_consultas_agrupadas_limitadas_y_con_visibilidad_del_asesor()
    {
        [, $consultas] = $this->resumen([6], 77);
        $this->assertCount(2, $consultas);
        $this->assertStringContainsString('COUNT(*)', $consultas[0]);
        $this->assertStringContainsString('group by', strtolower($consultas[0]));
        $this->assertStringContainsString('top 10', strtolower($consultas[1]));
        foreach ($consultas as $sql) {
            $this->assertStringContainsString('[ProcesosHistorial]', $sql);
        }
        [, $consultas] = $this->resumen([2]);
        foreach ($consultas as $sql) {
            $this->assertStringNotContainsString('ProcesosHistorial', $sql);
        }
    }

    public function test_rol_sin_transiciones_no_consulta_pendientes()
    {
        [$resumen, $consultas] = $this->resumen([7]);
        $this->assertCount(1, $consultas);
        $this->assertSame([], $resumen['contadores']);
    }

    public function test_sin_sesion_responde_401_json()
    {
        $this->postJson('/inicio-resumen')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Tu sesión expiró. Ingresa de nuevo.']);
        $this->post('/inicio-resumen', [], ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => '*/*'])
            ->assertStatus(401)
            ->assertJson(['message' => 'Tu sesión expiró. Ingresa de nuevo.']);
    }

    public function test_sin_sesion_y_sin_json_redirige_al_login()
    {
        $this->post('/inicio-resumen')->assertRedirect(route('login'));
    }

    public function test_token_vencido_responde_419_json()
    {
        Route::middleware('web')->post('/prueba-token-vencido', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
        $this->postJson('/prueba-token-vencido')
            ->assertStatus(419)
            ->assertExactJson(['message' => 'Tu sesión expiró. Recarga la página e ingresa de nuevo.']);
        $this->post('/prueba-token-vencido')->assertStatus(419)->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }
}
