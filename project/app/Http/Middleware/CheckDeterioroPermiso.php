<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Verifica el permiso de una acción del módulo de deterioro.
 *
 * El middleware submenu.permiso solo cubre las rutas GET de vista, y además
 * deja pasar cualquier ruta que no encuentre en Submenus. Los endpoints POST
 * del sitio quedan protegidos únicamente por 'auth', de modo que cualquier
 * usuario autenticado podría dispararlos. En un módulo contable eso no basta.
 *
 * Se reutiliza el modelo de permisos existente: cada acción tiene un submenú
 * técnico '/deterioro-accion-<accion>' en la tabla Submenus, y el permiso se
 * concede por rol desde la pantalla de gestión del sitio.
 *
 * Uso: ->middleware('deterioro.permiso:calcular')
 */
class CheckDeterioroPermiso
{
    public function handle($request, Closure $next, $accion = 'consultar')
    {
        $submenu = DB::table('Submenus')
            ->where('RutaSubmenu', '/deterioro-accion-'.$accion)
            ->first();

        // Si la acción todavía no está registrada, se exige al menos permiso
        // sobre alguna pantalla del módulo, en vez de dejar pasar.
        if (!$submenu) {
            $submenu = DB::table('Submenus')->where('RutaSubmenu', '/deterioro-cortes')->first();
        }
        if (!$submenu) {
            abort(403, 'El módulo de deterioro no está registrado en el menú.');
        }

        $rol = DB::table('RolUsuario')->where('IdUsuario', auth()->id())->first();
        if (!$rol) {
            abort(403, 'No tienes un rol asignado. Contacta al administrador.');
        }

        $tienePermiso = DB::table('PermisosRoles')
            ->where('IdRoles', $rol->IdRol)
            ->where('IdSubmenu', $submenu->IdSubmenu)
            ->exists();

        if (!$tienePermiso) {
            abort(403, 'No tienes permiso para esta acción del módulo de deterioro.');
        }

        return $next($request);
    }
}
