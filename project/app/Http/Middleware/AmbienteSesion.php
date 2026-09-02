<?php

namespace App\Http\Middleware;

use App\Support\Ambiente;
use Closure;

/**
 * Aplica a la petición el ambiente de datos guardado en la sesión.
 *
 * Va justo después de la sesión y antes de la autenticación: el guard debe
 * hidratarse ya con las conexiones definitivas.
 */
class AmbienteSesion
{
    public function handle($request, Closure $next)
    {
        Ambiente::aplicar($request->session()->get(Ambiente::CLAVE));

        return $next($request);
    }
}
