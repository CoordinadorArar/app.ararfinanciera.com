<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class CheckSubmenuAccion
{
    public function handle($request, Closure $next, ...$rutas)
    {
        $submenus = DB::connection('identidad')->table('Submenus')->whereIn('RutaSubmenu', $rutas)->pluck('IdSubmenu');

        if ($submenus->isEmpty()) {
            return $next($request);
        }

        $roles = DB::connection('identidad')->table('RolUsuario')->where('IdUsuario', auth()->id())->pluck('IdRol');

        if ($roles->isEmpty()) {
            return $this->denegar($request, 'No tienes un rol asignado. Contacta al administrador.');
        }

        $tienePermiso = DB::connection('identidad')->table('PermisosRoles')
            ->whereIn('IdRoles', $roles)
            ->whereIn('IdSubmenu', $submenus)
            ->exists();

        if (!$tienePermiso) {
            return $this->denegar($request, 'No tienes permiso para realizar esta acción.');
        }

        return $next($request);
    }

    private function denegar($request, $mensaje)
    {
        if (!$request->isMethod('get') || $request->ajax() || $request->expectsJson()) {
            return response()->json(['message' => $mensaje], 403);
        }

        abort(403, $mensaje);
    }
}
