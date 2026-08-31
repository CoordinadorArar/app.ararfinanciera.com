<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Verifica del lado del servidor que el rol del usuario tenga permiso sobre la página (submenú)
 * que está pidiendo, no solo que esté oculta/visible en el menú lateral.
 *
 * Si la ruta pedida no corresponde a ningún RutaSubmenu registrado, se deja pasar sin más
 * (no rompe rutas de vista que no están modeladas en el menú, ni los endpoints POST de acciones).
 */
class CheckSubmenuPermission
{
    public function handle($request, Closure $next)
    {
        $ruta = '/'.ltrim($request->path(), '/');

        $submenu = DB::table('Submenus')->where('RutaSubmenu', $ruta)->first();

        if (!$submenu) {
            return $next($request);
        }

        $idUsuario = auth()->id();
        $rol = DB::table('RolUsuario')->where('IdUsuario', $idUsuario)->first();

        if (!$rol) {
            abort(403, 'No tienes un rol asignado. Contacta al administrador.');
        }

        $tienePermiso = DB::table('PermisosRoles')
            ->where('IdRoles', $rol->IdRol)
            ->where('IdSubmenu', $submenu->IdSubmenu)
            ->exists();

        if (!$tienePermiso) {
            abort(403, 'No tienes permiso para acceder a esta página.');
        }

        return $next($request);
    }
}
