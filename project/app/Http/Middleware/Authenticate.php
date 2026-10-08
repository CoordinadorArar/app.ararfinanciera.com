<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        if ($request->user() && $request->user()->estadoUsuario != 1) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $mensaje = 'El usuario está inactivo, la sesión no puede continuar.';
            if (!$request->isMethod('get') || $request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => $mensaje, 'res' => 'inactivo'], 403);
            }
            return redirect()->route('login')->withErrors(['email' => $mensaje]);
        }

        return $next($request);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
