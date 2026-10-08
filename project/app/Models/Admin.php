<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Admin extends Model
{
    use HasFactory;

    /**Conexión de identidad: usuarios, roles, menús y permisos viven siempre en producción */
    private static function db(){
        return DB::connection('identidad');
    }
    /**Obtener menus */
    public static function obtenerMenus(){
        $sql = "SELECT * FROM Menus ORDER BY Orden, IdMenu";
        $menus = self::db()->select($sql);
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
        $submenus = self::db()->select($sql, $bindings);
        return $submenus;
    }

    public static function perfilUsuario($idUsuario=''){
        $id = $idUsuario != '' ? $idUsuario : auth()->id();
        $sql = "SELECT * FROM users WHERE IdUsuario=?";
        $user = self::db()->select($sql, [$id]);
        return $user;
    }
    /**Mostrar información de la pagaduria seleccionada y la configuración usada para hallar el cupo de la misma */
    public static function mostrarInfoPagaduria($idPagaduria,$tipoDescuento='',$idConfiguracion=''){
        $sql = "SELECT p.*,c.IdConfigCalculo,c.Configuracion,c.TipoDescuentoMaximo FROM Pagadurias p LEFT JOIN CuposConfigCalculos c ON p.IdPagaduria=c.IdPagaduria";
        $bindings = [];
        if ($tipoDescuento != '') {
            $sql .= " AND c.TipoDescuentoMaximo=? ";
            $bindings[] = $tipoDescuento;
        }
        if ($idConfiguracion != '') {
            $sql .= " AND c.IdConfigCalculo=? ";
            $bindings[] = $idConfiguracion;
        }
        $sql .= " WHERE p.IdPagaduria=? ORDER BY c.IdConfigCalculo";
        $bindings[] = $idPagaduria;
        $pagaduria = DB::select($sql, $bindings);
        return $pagaduria;
    }
    public static function pagaduria($idPagaduria){
        return DB::table('Pagadurias')->where('IdPagaduria',$idPagaduria)->first();
    }
    public static function formulasPagaduria($idPagaduria){
        return DB::table('CuposConfigCalculos')->where('IdPagaduria',$idPagaduria)->orderBy('TipoDescuentoMaximo')->orderBy('IdConfigCalculo')->get(['IdConfigCalculo','TipoDescuentoMaximo','Configuracion'])->all();
    }
    public static function reglasEdadPagaduria($idPagaduria){
        return DB::table('PagaduriasReglasEdad')->where('IdPagaduria',$idPagaduria)->orderBy('EdadMin')->get()->all();
    }
    public static function guardarPagaduria($idPagaduria,$datos){
        return DB::transaction(function() use ($idPagaduria,$datos){
            if($idPagaduria){
                $actual = (array) self::pagaduria($idPagaduria);
                DB::table('Pagadurias')->where('IdPagaduria',$idPagaduria)->update($datos);
                foreach($datos as $campo => $valor){
                    self::auditar('Pagadurias',$idPagaduria,$campo,$actual[$campo],$valor);
                }
            }else{
                $idPagaduria = DB::table('Pagadurias')->insertGetId($datos,'IdPagaduria');
                self::auditar('Pagadurias',$idPagaduria,'*',null,json_encode($datos,JSON_UNESCAPED_UNICODE));
                foreach([[18,70,120,0.003],[71,74,48,0.003],[75,99,48,0.005625]] as $regla){
                    self::guardarReglaEdad('',$idPagaduria,['EdadMin'=>$regla[0],'EdadMax'=>$regla[1],'PlazoMaximo'=>$regla[2],'PorcentajeSeguro'=>$regla[3]]);
                }
            }
            $tipos = DB::table('CuposConfigCalculos')->where('IdPagaduria',$idPagaduria)->pluck('TipoDescuentoMaximo')->map(function($tipo){ return trim($tipo); })->all();
            $requeridos = self::pagaduria($idPagaduria)->UsaReglaSMMLV ? ['%','$'] : (count($tipos) ? [] : ['%']);
            foreach(array_diff($requeridos,$tipos) as $tipo){
                $idConfiguracion = DB::table('CuposConfigCalculos')->insertGetId(['IdPagaduria'=>$idPagaduria,'Configuracion'=>'','TipoDescuentoMaximo'=>$tipo],'IdConfigCalculo');
                self::auditar('CuposConfigCalculos',$idConfiguracion,'*',null,json_encode(['IdPagaduria'=>(int)$idPagaduria,'TipoDescuentoMaximo'=>$tipo]));
            }
            return $idPagaduria;
        });
    }
    public static function guardarReglaEdad($idReglaEdad,$idPagaduria,$datos){
        $datos['updated_at'] = now();
        if($idReglaEdad != ''){
            $actual = (array) DB::table('PagaduriasReglasEdad')->where('IdReglaEdad',$idReglaEdad)->first();
            DB::table('PagaduriasReglasEdad')->where('IdReglaEdad',$idReglaEdad)->where('IdPagaduria',$idPagaduria)->update($datos);
            foreach(['EdadMin','EdadMax','PlazoMaximo','PorcentajeSeguro'] as $campo){
                self::auditar('PagaduriasReglasEdad',$idReglaEdad,$campo,$actual[$campo],$datos[$campo]);
            }
            return $idReglaEdad;
        }
        $datos = array_merge(['IdPagaduria'=>(int)$idPagaduria],$datos,['created_at'=>$datos['updated_at']]);
        $idReglaEdad = DB::table('PagaduriasReglasEdad')->insertGetId($datos,'IdReglaEdad');
        self::auditar('PagaduriasReglasEdad',$idReglaEdad,'*',null,json_encode(array_diff_key($datos,['created_at'=>1,'updated_at'=>1])));
        return $idReglaEdad;
    }
    public static function eliminarReglaEdad($idReglaEdad,$idPagaduria){
        $regla = DB::table('PagaduriasReglasEdad')->where('IdReglaEdad',$idReglaEdad)->where('IdPagaduria',$idPagaduria)->first();
        if(!$regla){
            return false;
        }
        DB::table('PagaduriasReglasEdad')->where('IdReglaEdad',$idReglaEdad)->delete();
        self::auditar('PagaduriasReglasEdad',$idReglaEdad,'*',json_encode(['IdPagaduria'=>(int)$idPagaduria,'EdadMin'=>(int)$regla->EdadMin,'EdadMax'=>(int)$regla->EdadMax,'PlazoMaximo'=>(int)$regla->PlazoMaximo,'PorcentajeSeguro'=>(float)$regla->PorcentajeSeguro]),null);
        return true;
    }
    public static function eliminarRubro($idRubro,$idPagaduria){
        $rubro = DB::table('PagaduriasRubros')->where('IdRubro',$idRubro)->where('IdPagaduria',$idPagaduria)->first();
        DB::table('PagaduriasRubros')->where('IdRubro',$idRubro)->delete();
        self::auditar('PagaduriasRubros',$idRubro,'*',json_encode(['IdPagaduria'=>(int)$idPagaduria,'NombreRubro'=>$rubro->NombreRubro],JSON_UNESCAPED_UNICODE),null);
        return true;
    }
    public static function auditar($tabla,$idRegistro,$campo,$anterior,$nuevo){
        if((is_numeric($anterior) && is_numeric($nuevo)) ? $anterior == $nuevo : (string) $anterior === (string) $nuevo){
            return;
        }
        DB::table('ConfiguracionAuditoria')->insert([
            'Tabla' => $tabla,
            'IdRegistro' => $idRegistro,
            'Campo' => $campo,
            'ValorAnterior' => $anterior,
            'ValorNuevo' => $nuevo,
            'IdUsuario' => auth()->id()
        ]);
    }
    public static function ultimaAuditoriaPagaduria($idPagaduria){
        $patron = '{"IdPagaduria":'.(int)$idPagaduria.',%';
        $sql = "SELECT TOP 1 a.* FROM ConfiguracionAuditoria a
                WHERE (a.Tabla='Pagadurias' AND a.IdRegistro=?)
                   OR (a.Tabla='CuposConfigCalculos' AND a.IdRegistro IN (SELECT IdConfigCalculo FROM CuposConfigCalculos WHERE IdPagaduria=?))
                   OR (a.Tabla='PagaduriasRubros' AND a.IdRegistro IN (SELECT IdRubro FROM PagaduriasRubros WHERE IdPagaduria=?))
                   OR (a.Tabla='PagaduriasReglasEdad' AND a.IdRegistro IN (SELECT IdReglaEdad FROM PagaduriasReglasEdad WHERE IdPagaduria=?))
                   OR (a.Tabla IN ('PagaduriasRubros','PagaduriasReglasEdad') AND (a.ValorAnterior LIKE ? OR a.ValorNuevo LIKE ?))
                ORDER BY a.Fecha DESC, a.Id DESC";
        $auditoria = DB::select($sql,[$idPagaduria,$idPagaduria,$idPagaduria,$idPagaduria,$patron,$patron]);
        if(count($auditoria) == 0){
            return null;
        }
        $usuario = $auditoria[0]->IdUsuario ? self::perfilUsuario($auditoria[0]->IdUsuario) : [];
        return [
            'fecha' => $auditoria[0]->Fecha,
            'idUsuario' => $auditoria[0]->IdUsuario,
            'usuario' => count($usuario) ? $usuario[0]->nombreUsuario : null,
            'tabla' => $auditoria[0]->Tabla,
            'campo' => $auditoria[0]->Campo
        ];
    }
    /**Mostrar rubros de la pagaduria seleccionada */
    public static function mostrarRubrosPagaduria($idPagaduria){
        $sql = "SELECT * FROM PagaduriasRubros WHERE IdPagaduria = ? ORDER BY NombreRubro ASC";
        $rubros = DB::select($sql, [$idPagaduria]);
        return $rubros;
    }

    public static function guardarPagaduriaInfo($nombrePagaduria,$accion='',$idConfiguracion='',$configuracion='',$idPagaduria=''){
        if($accion != ''){
            $anterior = DB::table('CuposConfigCalculos')->where('IdConfigCalculo',$idConfiguracion)->where('IdPagaduria',$idPagaduria)->value('Configuracion');
            $guardar = DB::table('CuposConfigCalculos')->where('IdConfigCalculo',$idConfiguracion)->where('IdPagaduria',$idPagaduria)->update([
                'Configuracion' => $configuracion
            ]);
            if($guardar){
                self::auditar('CuposConfigCalculos',$idConfiguracion,'Configuracion',$anterior,$configuracion);
            }
            return $guardar;
        }else{
            return DB::table('pagadurias')->insert(['NombrePagaduria'=>$nombrePagaduria]);
        }
    }
    /** */
    public static function guardarRubroConfiguracion($nombreRubro,$idPagaduria){
        $idRubro = DB::table('PagaduriasRubros')->insertGetId([
            'IdPagaduria' => $idPagaduria,
            'NombreRubro' => $nombreRubro
        ],'IdRubro');
        self::auditar('PagaduriasRubros',$idRubro,'*',null,json_encode(['IdPagaduria'=>(int)$idPagaduria,'NombreRubro'=>$nombreRubro],JSON_UNESCAPED_UNICODE));
        return $idRubro;
    }
    /**Actualizar información del usuario seleccionado */
    public static function editarUsuarios($idUsuario,$nombre,$documento,$email){
        return self::db()->table('users')->where('IdUsuario',$idUsuario)->update([
            'nombreUsuario' => $nombre,
            'documentoUsuario' => $documento,
            'email' => $email
        ]);
    }
    /**Validar si el usuario no tiene rol asignado (sucede al crearse el usuario, se genera sin rol) */
    public static function checkRol($idUsuario){
        $sql = "SELECT * FROM rolusuario WHERE IdUsuario = ?";
        $rol = self::db()->select($sql, [$idUsuario]);
        return $rol;
    }
    /**Insertar el rol para el usuario seleccionado, si es nuevo pues aun no tiene rol relacionado */
    public static function insertRol($idUsuario,$idRol){
        return self::db()->table('rolusuario')->insert([
            'IdUsuario' => $idUsuario,
            'IdRol' => $idRol
        ]);
    }
    /**Cambiar rol de un usuario seleccionado */
    public static function editRol($idUsuario,$idRol){
        return self::db()->table('rolusuario')->where('IdUsuario',$idUsuario)->update([
            'IdRol' => $idRol
        ]);
    }
    /**Activa o inactiva un usuario */
    public static function changeStateUser($idUsuario,$estado){
        $estadoUsuario = ($estado == 0)? false : true ;
        return self::db()->table('users')->where('idUsuario',$idUsuario)->update([
            'estadoUsuario' => $estadoUsuario
        ]);
    }
    /**Guardar variable */
    public static function guardarVariable($idVariable='',$nombreVariable,$valorVariable){
        if($idVariable != ''){
            $actual = DB::table('ValoresVariables')->where('IdValorVariable',$idVariable)->first();
            $guardar = DB::table('ValoresVariables')->where('IdValorVariable',$idVariable)->update([
                'NombreValorVariable' => $nombreVariable,
                'ValorVariable' => $valorVariable
            ]);
            if($guardar && $actual){
                self::auditar('ValoresVariables',$idVariable,'NombreValorVariable',$actual->NombreValorVariable,$nombreVariable);
                self::auditar('ValoresVariables',$idVariable,'ValorVariable',$actual->ValorVariable,$valorVariable);
            }
            return $guardar;
        }else{
            $idVariable = DB::table('ValoresVariables')->insertGetId([
                'NombreValorVariable' => $nombreVariable,
                'ValorVariable' => $valorVariable
            ],'IdValorVariable');
            self::auditar('ValoresVariables',$idVariable,'*',null,json_encode(['NombreValorVariable'=>$nombreVariable,'ValorVariable'=>$valorVariable],JSON_UNESCAPED_UNICODE));
            return $idVariable;
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
        return self::db()->select($sql, $bindings);
    }
    /**Guardar permiso de rol */
    public static function guardarPermisoRol($idRol,$idSubmenu){
        $sql_consultar = "SELECT * FROM PermisosRoles WHERE IdRoles = ? AND IdSubmenu = ?";
        $consultar = self::db()->select($sql_consultar, [$idRol, $idSubmenu]);
        if(!$consultar){
            return self::db()->table('PermisosRoles')->insert([
                'IdRoles' => $idRol,
                'IdSubmenu' => $idSubmenu
            ]);
        }
    }
    /**Eliminar permiso de rol */
    public static function eliminarPermisoRol($idRol, $idSubmenu){
        $sql_consultar = "SELECT * FROM PermisosRoles WHERE IdRoles = ? AND IdSubmenu = ?";
        $consultar = self::db()->select($sql_consultar, [$idRol, $idSubmenu]);
        if($consultar){
            return self::db()->table('PermisosRoles')->where(['IdRoles' => $idRol, 'IdSubmenu' => $idSubmenu])->delete();
        }
    }
}
