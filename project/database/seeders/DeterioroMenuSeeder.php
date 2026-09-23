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
 * NombreSubmenu tiene el mismo tope de 30: pasarse no trunca en silencio, lanza
 * excepción y aborta el seeder, de modo que el rótulo se deja con margen.
 *
 * Los submenús de acción (/deterioro-accion-*) van con EstadoSubmenu = 0: no se
 * dibujan en el menú, existen solo para colgar de ellos el permiso por acción.
 * Desde el 15 de septiembre de 2026 eso vale también para siete de las ocho
 * páginas: en el menú sólo se dibuja Cortes. El porqué está junto a la lista.
 *
 * Es idempotente.
 *
 * Escribe siempre sobre la conexión 'identidad', nunca sobre la conexión por
 * defecto, porque es de ahí y sólo de ahí que leen CheckSubmenuPermission,
 * CheckDeterioroPermiso y Admin::obtenerSubMenus(). Ambiente::aplicar() no
 * conmuta 'identidad' a propósito: la identidad es única y vive en producción,
 * de modo que sembrar estas filas en ArarFinanciera_PRUEBAS no daría ni menú ni
 * permiso a nadie, sólo filas huérfanas.
 *
 * Que el seeder no dependa del ambiente es justamente lo que se busca: antes
 * usaba la conexión por defecto y acertaba por casualidad, y una corrida con
 * Ambiente::aplicar('demo') ya dejó una vez filas sembradas donde no servían.
 *
 * NO RECONCILIA `EstadoSubmenu`, Y ES DELIBERADO. submenu() inserta con el valor
 * declarado y, si la ruta ya existe, devuelve su id sin tocar ninguna columna.
 * Las demás columnas se derivan del código —si la base difiere, la base está
 * mal—, pero `EstadoSubmenu` es estado operativo: su valor correcto no lo decide
 * este archivo sino si la ruta existe en la rama desplegada. El 14 de septiembre
 * de 2026 las cinco páginas de las fases 4 a 7a se apagaron en producción porque
 * su código todavía no estaba allí, y un seeder que sobreescribiera la columna
 * las devolvería al menú —con 404— la próxima vez que alguien lo corriera por
 * cualquier otro motivo. Encenderlas es una decisión con fecha y responsable, y
 * vive en documentacion/deterioro-fase7-visibilidad.sql.
 */
class DeterioroMenuSeeder extends Seeder
{
    /** Roles que reciben el permiso: Administrador, Gerente y Contador. */
    const ROLES_CONSULTA = [1, 2, 7];
    const ROLES_CALCULO = [1, 2, 7];
    // Mismo conjunto que ROLES_CALCULO: decisión revisable. Marcar o levantar
    // una suspensión cambia la base de deterioro y el gasto del período, así
    // que no hereda el permiso de consulta ni el de cálculo por defecto.
    const ROLES_SUSPENSION = [1, 2, 7];
    // Explicar una partida de conciliación es un acto contable con autor, así
    // que tampoco hereda el permiso de consulta. Misma decisión revisable.
    const ROLES_CONCILIACION = [1, 2, 7];
    // Clasificar una baja es del mismo orden que explicar una partida.
    const ROLES_CLASIFICAR = [1, 2, 7];
    // Cerrar un corte lo congela: desde ahí ninguna otra escritura del módulo
    // lo toca. Mismo conjunto que el cálculo, decisión revisable.
    const ROLES_CIERRE = [1, 2, 7];
    // Forzar el cierre con salvedades es permiso propio y NO se hereda de
    // cerrar: cerrar un corte limpio y cerrarlo dejando constancia de que algo
    // estaba mal no son la misma decisión. Sólo Administrador y Gerente.
    const ROLES_FORZAR_CIERRE = [1, 2];
    // Reabrir un corte cerrado deshace la inmutabilidad de un mes ya reportado.
    // Es la acción más delicada del módulo y no comparte permiso con cerrar.
    const ROLES_REAPERTURA = [1, 2];
    // Leer la bitácora es consulta, pero de la actuación de otros usuarios.
    const ROLES_AUDITORIA = [1, 2, 7];
    // Exportar es de sólo lectura, pero el archivo sale del sitio: el plano del
    // asiento llega a contabilidad y el detalle lleva la cartera completa. Por
    // eso no hereda el permiso de consulta. Mismo conjunto, decisión revisable.
    const ROLES_EXPORTAR = [1, 2, 7];

    /** Mismo criterio que Admin::db(): la identidad no se conmuta. */
    private function db()
    {
        return DB::connection('identidad');
    }

    public function run()
    {
        $idMenu = $this->db()->table('Menus')->where('NombreMenu', 'Deterioro Cartera')->value('IdMenu');
        if (!$idMenu) {
            $idMenu = $this->db()->table('Menus')->insertGetId([
                'NombreMenu' => 'Deterioro Cartera',
                'RutaMenu' => '#',
                'CodigoMenu' => '<i class="fas fa-chart-line"></i>',
            ], 'IdMenu');
        }

        // Rótulo, ruta, icono y si se dibuja en el menú lateral.
        //
        // SÓLO CORTES SE DIBUJA, y las otras siete existen como submenú para
        // colgar de ellas el permiso, igual que las acciones. La razón es de
        // navegación, no de permisos: las siete son el detalle de un corte y
        // necesitan uno en la URL, de modo que al entrar sin él su JS redirige
        // a Cortes. Dibujarlas daría ocho entradas de menú que llevan todas al
        // mismo sitio. Se entra a ellas desde la fila del corte, que es como el
        // módulo ya funciona, y así nunca hay duda de qué mes se está mirando.
        $paginas = [
            ['Cortes', '/deterioro-cortes', '<i class="fas fa-calendar-check"></i>', 1],
            ['Resumen del corte', '/deterioro-resumen', '<i class="fas fa-table-cells"></i>', 0],
            ['Detalle por operación', '/deterioro-detalle-operaciones', '<i class="fas fa-list-ul"></i>', 0],
            ['Contable / fiscal', '/deterioro-contable-fiscal', '<i class="fas fa-scale-balanced"></i>', 0],
            ['Evolución', '/deterioro-evolucion', '<i class="fas fa-chart-line"></i>', 0],
            ['Suspensión de intereses', '/deterioro-suspensiones', '<i class="fas fa-pause-circle"></i>', 0],
            ['Conciliación SIESA', '/deterioro-conciliacion', '<i class="fas fa-code-compare"></i>', 0],
            ['Controles y cierre', '/deterioro-controles', '<i class="fas fa-lock"></i>', 0],
        ];

        // Rótulo, ruta técnica y roles que reciben la acción. La ruta más larga
        // es '/deterioro-accion-forzarCierre', que mide exactamente los 30
        // caracteres que admite la columna; por eso la acción de clasificar
        // bajas se llama 'clasificar' y no 'clasificarBaja', que se pasaría.
        $acciones = [
            ['Consultar deterioro', '/deterioro-accion-consultar', self::ROLES_CONSULTA],
            ['Calcular deterioro', '/deterioro-accion-calcular', self::ROLES_CALCULO],
            ['Suspender intereses', '/deterioro-accion-suspender', self::ROLES_SUSPENSION],
            ['Conciliar con SIESA', '/deterioro-accion-conciliar', self::ROLES_CONCILIACION],
            ['Clasificar bajas', '/deterioro-accion-clasificar', self::ROLES_CLASIFICAR],
            ['Cerrar corte', '/deterioro-accion-cerrar', self::ROLES_CIERRE],
            ['Forzar cierre con salvedad', '/deterioro-accion-forzarCierre', self::ROLES_FORZAR_CIERRE],
            ['Reabrir corte', '/deterioro-accion-reabrir', self::ROLES_REAPERTURA],
            ['Consultar bitácora', '/deterioro-accion-auditar', self::ROLES_AUDITORIA],
            ['Exportar', '/deterioro-accion-exportar', self::ROLES_EXPORTAR],
        ];

        $idsConsulta = [];
        foreach ($paginas as $p) {
            $idsConsulta[] = $this->submenu($idMenu, $p[0], $p[1], $p[2], $p[3]);
        }
        foreach (self::ROLES_CONSULTA as $rol) {
            foreach ($idsConsulta as $id) {
                $this->permiso($rol, $id);
            }
        }

        foreach ($acciones as $a) {
            $idSubmenu = $this->submenu($idMenu, $a[0], $a[1], '', 0);
            foreach ($a[2] as $rol) {
                $this->permiso($rol, $idSubmenu);
            }
        }
    }

    private function submenu($idMenu, $nombre, $ruta, $icono, $estado)
    {
        $id = $this->db()->table('Submenus')->where('RutaSubmenu', $ruta)->value('IdSubmenu');
        if ($id) {
            return $id;
        }
        return $this->db()->table('Submenus')->insertGetId([
            'IdMenu' => $idMenu,
            'NombreSubmenu' => $nombre,
            'RutaSubmenu' => $ruta,
            'CodigoSubmenu' => $icono,
            'EstadoSubmenu' => $estado,
        ], 'IdSubmenu');
    }

    private function permiso($idRol, $idSubmenu)
    {
        $existe = $this->db()->table('PermisosRoles')
            ->where('IdRoles', $idRol)->where('IdSubmenu', $idSubmenu)->exists();
        if (!$existe) {
            $this->db()->table('PermisosRoles')->insert(['IdRoles' => $idRol, 'IdSubmenu' => $idSubmenu]);
        }
    }
}
