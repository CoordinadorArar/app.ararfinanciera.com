<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Registra el módulo de deterioro en el menú lateral y concede el permiso a
 * los roles que lo necesitan.
 *
 * No existe pantalla para crear menús: se hace por inserción. RutaSubmenu debe
 * coincidir exactamente con el path de la ruta, con la barra inicial, porque es
 * la clave que usan CheckSubmenuPermission y CheckDeterioroPermiso. La columna
 * admite 30 caracteres, y la ruta más larga del módulo mide exactamente 30.
 *
 * Los submenús de acción (/deterioro-accion-*) van con EstadoSubmenu = 0: no se
 * dibujan en el menú, existen solo para colgar de ellos el permiso por acción.
 *
 * Es idempotente.
 */
class DeterioroMenuSeeder extends Seeder
{
    /** Roles que reciben el permiso: Administrador, Gerente y Contador. */
    const ROLES_CONSULTA = [1, 2, 7];
    const ROLES_CALCULO = [1, 2, 7];

    public function run()
    {
        $idMenu = DB::table('Menus')->where('NombreMenu', 'Deterioro Cartera')->value('IdMenu');
        if (!$idMenu) {
            $idMenu = DB::table('Menus')->insertGetId([
                'NombreMenu' => 'Deterioro Cartera',
                'RutaMenu' => '#',
                'CodigoMenu' => '<i class="fas fa-chart-line"></i>',
            ], 'IdMenu');
        }

        $paginas = [
            ['Cortes', '/deterioro-cortes', '<i class="fas fa-calendar-check"></i>'],
            ['Resumen del corte', '/deterioro-resumen', '<i class="fas fa-table-cells"></i>'],
            ['Detalle por operación', '/deterioro-detalle-operaciones', '<i class="fas fa-list-ul"></i>'],
        ];
        $acciones = [
            ['Consultar deterioro', '/deterioro-accion-consultar'],
            ['Calcular deterioro', '/deterioro-accion-calcular'],
        ];

        $idsConsulta = [];
        $idsCalculo = [];

        foreach ($paginas as $p) {
            $idsConsulta[] = $this->submenu($idMenu, $p[0], $p[1], $p[2], 1);
        }
        $idsConsulta[] = $this->submenu($idMenu, $acciones[0][0], $acciones[0][1], '', 0);
        $idsCalculo[] = $this->submenu($idMenu, $acciones[1][0], $acciones[1][1], '', 0);

        foreach (self::ROLES_CONSULTA as $rol) {
            foreach ($idsConsulta as $id) {
                $this->permiso($rol, $id);
            }
        }
        foreach (self::ROLES_CALCULO as $rol) {
            foreach ($idsCalculo as $id) {
                $this->permiso($rol, $id);
            }
        }
    }

    private function submenu($idMenu, $nombre, $ruta, $icono, $estado)
    {
        $id = DB::table('Submenus')->where('RutaSubmenu', $ruta)->value('IdSubmenu');
        if ($id) {
            return $id;
        }
        return DB::table('Submenus')->insertGetId([
            'IdMenu' => $idMenu,
            'NombreSubmenu' => $nombre,
            'RutaSubmenu' => $ruta,
            'CodigoSubmenu' => $icono,
            'EstadoSubmenu' => $estado,
        ], 'IdSubmenu');
    }

    private function permiso($idRol, $idSubmenu)
    {
        $existe = DB::table('PermisosRoles')
            ->where('IdRoles', $idRol)->where('IdSubmenu', $idSubmenu)->exists();
        if (!$existe) {
            DB::table('PermisosRoles')->insert(['IdRoles' => $idRol, 'IdSubmenu' => $idSubmenu]);
        }
    }
}
