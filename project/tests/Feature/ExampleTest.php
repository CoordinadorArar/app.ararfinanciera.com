<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_inicio_sin_sesion_redirige_al_login()
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
