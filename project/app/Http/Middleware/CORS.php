<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CORS
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Se usa $response->headers->set() en vez de ->header() porque este middleware corre
        // en TODAS las peticiones, y ->header() no existe en respuestas de archivo/descarga
        // (Symfony\Component\HttpFoundation\BinaryFileResponse), solo en Illuminate\Http\Response.
        // ->headers->set() sí está disponible en cualquier tipo de respuesta.
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-type, X-Auth-Token, Autorization, Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, DELETE');

        return $response;
    }
}
