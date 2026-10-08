<?php

namespace Tests\Feature;

use App\Services\Siesa\ClienteSoapSiesa;
use Tests\TestCase;

class SiesaEnvioDeshabilitadoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.siesa.envio_habilitado' => false, 'services.siesa.url' => 'http://127.0.0.1:9/no-enviar']);
    }

    public function test_el_interruptor_vale_false_sin_la_variable_de_entorno()
    {
        $clave = 'SIESA_ENVIO_HABILITADO';
        $previo = [getenv($clave), $_ENV[$clave] ?? null, $_SERVER[$clave] ?? null];
        putenv($clave);
        unset($_ENV[$clave], $_SERVER[$clave]);
        try {
            $this->assertNull(env($clave));
            $this->assertFalse((require config_path('services.php'))['siesa']['envio_habilitado']);
        } finally {
            if ($previo[0] !== false) {
                putenv($clave.'='.$previo[0]);
            }
            if ($previo[1] !== null) {
                $_ENV[$clave] = $previo[1];
            }
            if ($previo[2] !== null) {
                $_SERVER[$clave] = $previo[2];
            }
        }
    }

    public function test_con_el_interruptor_apagado_enviar_no_sale_a_la_red()
    {
        $this->assertFalse(ClienteSoapSiesa::habilitado());
        $this->assertSame(['ok' => false, 'detalle' => ClienteSoapSiesa::MENSAJE_DESHABILITADO, 'errores' => []], ClienteSoapSiesa::enviar('<Importar/>'));
    }

    public function test_ejecutar_paso_responde_422_sin_enviar()
    {
        $this->withoutMiddleware()
            ->postJson('/siesa-ejecutar-paso', ['idCliente' => '1234567890', 'paso' => 'tercero'])
            ->assertStatus(422)
            ->assertJson(['message' => ClienteSoapSiesa::MENSAJE_DESHABILITADO]);
    }

    public function test_ejecutar_paso_valida_parametros()
    {
        $this->withoutMiddleware()
            ->postJson('/siesa-ejecutar-paso', ['idCliente' => '12 34', 'paso' => 'otro'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['idCliente', 'paso']);
    }
}
