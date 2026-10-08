<?php

namespace App\Services\Centrales;

use RuntimeException;

class CentralException extends RuntimeException
{
    public $codigo;

    public function __construct($codigo, $mensaje)
    {
        parent::__construct($mensaje);
        $this->codigo = $codigo;
    }
}
