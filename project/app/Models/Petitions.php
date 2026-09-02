<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Petitions extends Model
{
    use HasFactory;

    public static function mostrarPagadurias(){
        $sql = "SELECT * FROM Pagadurias ORDER BY NombrePagaduria ASC";
        $pagadurias = DB::select($sql);
        return $pagadurias;
    }

    public static function mostrarDepartamentos(){
        $sql = "SELECT * FROM Departamentos ORDER BY NombreDepartamento ASC";
        $departamentos = DB::select($sql);
        return $departamentos;
    }

    public static function mostrarCiudades($idDepartamento){
        $sql = "SELECT * FROM Municipios WHERE IdDepartamento = ? ORDER BY NombreMunicipio ASC";
        $municipios = DB::select($sql, [$idDepartamento]);
        return $municipios;
    }

    public static function mostrarTiposDocumentos(){
        $sql = "SELECT * FROM TiposDocumentos";
        $tipos = DB::select($sql);
        return $tipos;
    }

    public static function mostrarSalarioMinimo(){
        $sql = "SELECT * FROM ValoresVariables WHERE NombreValorVariable = 'SalarioMinimoMensual'";
        $salario = DB::select($sql);
        return $salario;
    }

    public static function totalUsuarios($idUsuario=''){
        $id = auth()->id();
        $sql = "SELECT u.IdUsuario,u.nombreUsuario,u.email,u.documentoUsuario,u.estadoUsuario,u.created_at,r.IdRol,r.NombreRol,r.EstadoRol
                FROM users u LEFT JOIN rolusuario ru ON u.idUsuario=ru.IdUsuario LEFT JOIN roles r ON ru.IdRol=r.IdRol WHERE u.IdUsuario!=?";
        $bindings = [$id];
        if ($idUsuario != '') {
            $sql .= " AND u.idUsuario=?";
            $bindings[] = $idUsuario;
        }
        $users = DB::connection('identidad')->select($sql, $bindings);
        return $users;
    }

    public static function mostrarRoles($id=''){
        $sql = "SELECT * FROM roles ";
        $bindings = [];
        if ($id != '') {
            $sql .= " WHERE IdRol=? ";
            $bindings[] = $id;
        }
        $roles = DB::connection('identidad')->select($sql, $bindings);
        return $roles;
    }

    public static function mostrarAsesores(){
        $sql = "SELECT u.IdUsuario,u.nombreUsuario,u.email,u.documentoUsuario,u.estadoUsuario,u.created_at,r.IdRol,r.NombreRol,r.EstadoRol
                FROM users u LEFT JOIN rolusuario ru ON u.idUsuario=ru.IdUsuario LEFT JOIN roles r ON ru.IdRol=r.IdRol WHERE r.IdRol=4";
        $asesores = DB::connection('identidad')->select($sql);
        return $asesores;
    }
    /**Trae los datos de la tabla de valores variables */
    public static function mostrarValorVariable($valor){
        $sql = "SELECT * FROM ValoresVariables WHERE NombreValorVariable=?";
        $resultado = DB::select($sql, [$valor]);
        return $resultado;
    }
    /**Mostrar valores variables de tabla eje: salario minimo, tasa de interes */
    public static function mostrarValoresVariables($id=''){
        $sql = "SELECT * FROM ValoresVariables ";
        $bindings = [];
        if ($id != '') {
            $sql .= " WHERE IdValorVariable=? ";
            $bindings[] = $id;
        }
        $valores = DB::select($sql, $bindings);
        return $valores;
    }
}
