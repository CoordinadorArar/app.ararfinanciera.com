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
 *
 * La regla vive una sola vez, en evaluar(). El middleware la usa para abortar
 * con 403 y las pantallas la consultan por tiene() para no dibujar acciones que
 * el servidor va a rechazar. Duplicarla dejaría que pantalla y servidor
 * divergieran, y el día que divergieran la pantalla ofrecería un botón que el
 * servidor rechaza.
 *
 * El gate de pantalla es cosmético: la seguridad sigue estando en el middleware.
 */
class CheckDeterioroPermiso
{
    /**
     * Resuelve el permiso y devuelve el motivo cuando no lo hay, para que el
     * middleware pueda distinguir "el módulo no está registrado" de "no tienes
     * rol" y de "tu rol no tiene esta acción".
     */
    public static function evaluar($accion, $idUsuario)
    {
        $submenu = DB::connection('identidad')->table('Submenus')
            ->where('RutaSubmenu', '/deterioro-accion-'.$accion)
            ->first();

        // Si la acción todavía no está registrada, se exige al menos permiso
        // sobre alguna pantalla del módulo, en vez de dejar pasar.
        if (!$submenu) {
            $submenu = DB::connection('identidad')->table('Submenus')->where('RutaSubmenu', '/deterioro-cortes')->first();
        }
        if (!$submenu) {
            return ['ok' => false, 'mensaje' => 'El módulo de deterioro no está registrado en el menú.'];
        }

        $rol = DB::connection('identidad')->table('RolUsuario')->where('IdUsuario', $idUsuario)->first();
        if (!$rol) {
            return ['ok' => false, 'mensaje' => 'No tienes un rol asignado. Contacta al administrador.'];
        }

        $tienePermiso = DB::connection('identidad')->table('PermisosRoles')
            ->where('IdRoles', $rol->IdRol)
            ->where('IdSubmenu', $submenu->IdSubmenu)
            ->exists();

        if (!$tienePermiso) {
            return ['ok' => false, 'mensaje' => 'No tienes permiso para esta acción del módulo de deterioro.'];
        }

        return ['ok' => true, 'mensaje' => null];
    }

    /** Misma regla que el middleware, en booleano, para las pantallas. */
    public static function tiene($accion, $idUsuario = null)
    {
        return self::evaluar($accion, $idUsuario === null ? auth()->id() : $idUsuario)['ok'];
    }

    public function handle($request, Closure $next, $accion = 'consultar')
    {
        $permiso = self::evaluar($accion, auth()->id());
        if (!$permiso['ok']) {
            abort(403, $permiso['mensaje']);
        }

        return $next($request);
    }
}
