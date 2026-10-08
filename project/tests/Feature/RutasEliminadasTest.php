<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RutasEliminadasTest extends TestCase
{
    public function test_las_rutas_eliminadas_no_existen()
    {
        $rutas = ['cantidad-config-pagaduria', 'mostrar-rubros-pagaduria', 'historial-proceso', 'verificar-todos-los-documentos', 'mostrar-info-proceso', 'proceso-solo-info'];
        foreach ($rutas as $ruta) {
            $this->assertFalse(Route::has($ruta), $ruta);
            $this->assertContains($this->post('/'.$ruta)->getStatusCode(), [404, 405], $ruta);
        }
    }
}
