<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Admin extends Model
{
    use HasFactory;
    /**Obtener menus */
    public static function obtenerMenus(){
        $sql = "SELECT * FROM Menus ORDER BY IdMenu";
        $menus = DB::select($sql);
        return $menus;
    }
    /**Obtener submenus según rol del usuario */
    public static function obtenerSubMenus($idRol=''){
        $sql = "SELECT s.* FROM submenus s ";
        $bindings = [];
        if ($idRol != '') {
            $sql .= " INNER JOIN permisosroles p ON s.IdSubmenu=p.IdSubmenu ";
        }
        $sql .= " WHERE s.EstadoSubmenu=1 ";
        if ($idRol != '') {
            $sql .= " AND p.IdRoles=? ";
            $bindings[] = $idRol;
        }
        $sql .= " ORDER BY IdMenu";
        $submenus = DB::select($sql, $bindings);
        return $submenus;
    }

    public static function perfilUsuario($idUsuario=''){
        $id = $idUsuario != '' ? $idUsuario : auth()->id();
        $sql = "SELECT * FROM users WHERE IdUsuario=?";
        $user = DB::select($sql, [$id]);
        return $user;
    }
    /**Mostrar información de la pagaduria seleccionada y la configuración usada para hallar el cupo de la misma */
    public static function mostrarInfoPagaduria($idPagaduria,$tipoDescuento='',$idConfiguracion=''){
        $sql = "SELECT * FROM Pagadurias p JOIN CuposConfigCalculos c ON p.IdPagaduria=c.IdPagaduria WHERE p.IdPagaduria=?";
        $bindings = [$idPagaduria];
        if ($tipoDescuento != '') {
            $sql .= " AND c.TipoDescuentoMaximo=? ";
            $bindings[] = $tipoDescuento;
        }
        if ($idConfiguracion != '') {
            $sql .= " AND c.IdConfigCalculo=? ";
            $bindings[] = $idConfiguracion;
        }
        $pagaduria = DB::select($sql, $bindings);
        return $pagaduria;
    }
    /**Mostrar rubros de la pagaduria seleccionada */
    public static function mostrarRubrosPagaduria($idPagaduria){
        $sql = "SELECT * FROM PagaduriasRubros WHERE IdPagaduria = ? ORDER BY NombreRubro ASC";
        $rubros = DB::select($sql, [$idPagaduria]);
        return $rubros;
    }

    public static function guardarPagaduriaInfo($nombrePagaduria,$accion='',$idConfiguracion='',$configuracion=''){
        if($accion != ''){
            return DB::table('CuposConfigCalculos')->where('IdConfigCalculo',$idConfiguracion)->update([
                'Configuracion' => $configuracion
            ]);
        }else{
            return DB::table('pagadurias')->insert(['NombrePagaduria'=>$nombrePagaduria]);
        }
    }
    /** */
    public static function guardarRubroConfiguracion($nombreRubro,$idPagaduria){
        return DB::table('PagaduriasRubros')->insert([
            'IdPagaduria' => $idPagaduria,
            'NombreRubro' => $nombreRubro
        ]);
    }
    /**Actualizar información del usuario seleccionado */
    public static function editarUsuarios($idUsuario,$nombre,$documento,$email){
        return DB::table('users')->where('IdUsuario',$idUsuario)->update([
            'nombreUsuario' => $nombre,
            'documentoUsuario' => $documento,
            'email' => $email
        ]);
    }
    /**Validar si el usuario no tiene rol asignado (sucede al crearse el usuario, se genera sin rol) */
    public static function checkRol($idUsuario){
        $sql = "SELECT * FROM rolusuario WHERE IdUsuario = ?";
        $rol = DB::select($sql, [$idUsuario]);
        return $rol;
    }
    /**Insertar el rol para el usuario seleccionado, si es nuevo pues aun no tiene rol relacionado */
    public static function insertRol($idUsuario,$idRol){
        return DB::table('rolusuario')->insert([
            'IdUsuario' => $idUsuario,
            'IdRol' => $idRol
        ]);
    }
    /**Cambiar rol de un usuario seleccionado */
    public static function editRol($idUsuario,$idRol){
        return DB::table('rolusuario')->where('IdUsuario',$idUsuario)->update([
            'IdRol' => $idRol
        ]);
    }
    /**Activa o inactiva un usuario */
    public static function changeStateUser($idUsuario,$estado){
        $estadoUsuario = ($estado == 0)? false : true ;
        return DB::table('users')->where('idUsuario',$idUsuario)->update([
            'estadoUsuario' => $estadoUsuario
        ]);
    }
    /**Guardar variable */
    public static function guardarVariable($idVariable='',$nombreVariable,$valorVariable){
        if($idVariable != ''){
            return DB::table('ValoresVariables')->where('IdValorVariable',$idVariable)->update([
                'NombreValorVariable' => $nombreVariable,
                'ValorVariable' => $valorVariable
            ]);
        }else{
            return DB::table('ValoresVariables')->insert([
                'NombreValorVariable' => $nombreVariable,
                'ValorVariable' => $valorVariable
            ]);
        }
    }
    /**Permisos roles */
    public static function mostrarPermisosRoles($idPermisoRol='',$idRol='',$idSubmenu=''){
        $sql = "SELECT * FROM PermisosRoles WHERE 1=1 ";
        $bindings = [];
        if ($idPermisoRol != '') {
            $sql .= " AND IdPermisoRoles = ?";
            $bindings[] = $idPermisoRol;
        }
        if ($idRol != '') {
            $sql .= " AND IdRoles = ?";
            $bindings[] = $idRol;
        }
        if ($idSubmenu != '') {
            $sql .= " AND IdSubmenu = ?";
            $bindings[] = $idSubmenu;
        }
        return DB::select($sql, $bindings);
    }
    /**Guardar permiso de rol */
    public static function guardarPermisoRol($idRol,$idSubmenu){
        $sql_consultar = "SELECT * FROM PermisosRoles WHERE IdRoles = ? AND IdSubmenu = ?";
        $consultar = DB::select($sql_consultar, [$idRol, $idSubmenu]);
        if(!$consultar){
            return DB::table('PermisosRoles')->insert([
                'IdRoles' => $idRol,
                'IdSubmenu' => $idSubmenu
            ]);
        }
    }
    /**Eliminar permiso de rol */
    public static function eliminarPermisoRol($idRol, $idSubmenu){
        $sql_consultar = "SELECT * FROM PermisosRoles WHERE IdRoles = ? AND IdSubmenu = ?";
        $consultar = DB::select($sql_consultar, [$idRol, $idSubmenu]);
        if($consultar){
            return DB::table('PermisosRoles')->where(['IdRoles' => $idRol, 'IdSubmenu' => $idSubmenu])->delete();
        }
    }
}
