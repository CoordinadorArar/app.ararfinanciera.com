<?php

namespace App\Services\Centrales;

interface ProveedorCentral
{
    public function consultar(array $tercero): ResultadoCentral;

    public function probarConexion(): array;

    public function configurado(): bool;
}
