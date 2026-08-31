<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Contable extends Model
{
    use HasFactory;
    protected $connection = 'factoring';

    /**Convierte una lista de ids separada por comas en placeholders "?,?,?" + su array de bindings */
    private static function listaPlaceholders($listaIds)
    {
        $ids = array_filter(array_map('trim', explode(',', (string) $listaIds)), function ($v) {
            return $v !== '';
        });
        $ids = array_values($ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return [$placeholders, $ids];
    }

    public static function consultarDocumentosContables($tipoDocumento,$fechaInicial,$fechaFinal){
        $sql = "SELECT distinct factoring=a.idfactura, siesa=b.f350_consec_docto,a.tipoDocumento
                FROM v_facturas_factoring a
                LEFT JOIN UNOEEARAR..t350_co_docto_contable b  ON a.idfactura = b.f350_consec_docto
                AND b.f350_id_tipo_docto =a.tipoDocumento COLLATE Modern_Spanish_CI_AS AND b.f350_id_cia=7
                WHERE a.tipoDocumento IN (?) AND CONVERT(DATE,a.fecdocumento) >= ? AND CONVERT(DATE,a.fecdocumento) <= ?
                AND a.idfactura NOT IN('68177','68185','68190')
                GROUP BY a.idfactura, b.f350_consec_docto,a.tipoDocumento HAVING b.f350_consec_docto IS NULL
                ORDER BY factoring ASC";
        return DB::select($sql, [$tipoDocumento, $fechaInicial, $fechaFinal]);
    }

    public static function consultarDocumentosContablesNotas($tipoDocumento,$fechaInicial,$fechaFinal){
        $sql = "SELECT distinct factoring=a.idnotacredito, siesa=b.f350_consec_docto,a.tipoDocumento
                FROM v_NotasCreditos_factoring a
                LEFT JOIN UNOEEARAR..t350_co_docto_contable b  ON a.idnotacredito = b.f350_consec_docto
                AND b.f350_id_tipo_docto =a.tipoDocumento COLLATE Modern_Spanish_CI_AS AND b.f350_id_cia=7
                WHERE a.tipoDocumento IN (?) AND CONVERT(DATE,a.fecdocumento) >= ? AND CONVERT(DATE,a.fecdocumento) <= ?
                GROUP BY a.idnotacredito, b.f350_consec_docto,a.tipoDocumento HAVING b.f350_consec_docto IS NULL
                ORDER BY factoring ASC";
        return DB::select($sql, [$tipoDocumento, $fechaInicial, $fechaFinal]);
    }

    public static function factoringFactura($idOperacion,$tipoDocumento){
        [$placeholders, $ids] = self::listaPlaceholders($idOperacion);
        $sql = "SELECT tipoDocumento, idfactura,fecdocumento,idtercero,Nota=REPLACE(Nota, 'ñ' , 'N'),cuenta,convert(int,SUM(debito)) AS Debito,convert(int,sum(credito)) AS credito,centrocosto,tipo,
                sum(base) AS Base,fechaVencimiento
                FROM v_facturas_factoring WHERE idfactura in ($placeholders) AND tipoDocumento IN (?)
                GROUP BY tipoDocumento,idfactura,fecdocumento,idtercero,Nota,cuenta,centrocosto,tipo,fechaVencimiento
                ORDER BY tipoDocumento,idfactura ASC";
        return DB::select($sql, array_merge($ids, [$tipoDocumento]));
    }

    public static function factoringNotaCredito($idOperacion,$tipoDocumento){
        [$placeholders, $ids] = self::listaPlaceholders($idOperacion);
        $sql = "SELECT tipoDocumento, idnotacredito,fecdocumento,idtercero,Nota=REPLACE(Nota, 'ñ' , 'N'),cuenta,convert(int,SUM(debito)) AS Debito,Convert(int,sum(credito)) AS credito,centrocosto,tipo,
                sum(base) AS Base,fechaVencimiento
                FROM v_NotasCreditos_factoring WHERE idnotacredito in ($placeholders) AND tipoDocumento IN (?)
                GROUP BY tipoDocumento,idnotacredito,fecdocumento,idtercero,Nota,cuenta,centrocosto,tipo,fechaVencimiento
                ORDER BY tipoDocumento,idnotacredito ASC";
        return DB::select($sql, array_merge($ids, [$tipoDocumento]));
    }

    public static function consultarReclasificaciones($operacion){
        $sql = "SELECT DISTINCT tipdocumento=a.TipoDocumento, a.IdOperacion,siesa=''
                FROM V_Operaciones_Factoring a
                WHERE a.TipoDocumento = 'ope' AND a.IdOperacion=?
                GROUP BY a.TipoDocumento, a.IdOperacion ";
        return DB::select($sql, [$operacion]);
    }

    public static function busquedaOperacion($idOperacion){
        [$placeholders, $ids] = self::listaPlaceholders($idOperacion);
        $sql = "SELECT TipoDocumento,IdOperacion,Cuota,IdCliente,FecOperacion,FecVencimiento,cuenta,Debito,Credito,Tipo,vendedor,Nota=REPLACE(Nota, 'ñ' , 'N'),FecModifica
                FROM v_operaciones_factoring_reclasificar a
                WHERE IdOperacion IN ($placeholders)";
        return DB::select($sql, $ids);
    }

    public static function datosOperaciones($fechaInicial='',$fechaFinal=''){
        $sql = "SELECT distinct tipdocumento=a.TipoDocumento, a.IdOperacion,siesa=b.idoperacion
                FROM V_Operaciones_Factoring a
                LEFT JOIN BD_ARAR..v_operaciones_siesa b  ON a.TipoDocumento = b.tipo COLLATE Modern_Spanish_CI_AS AND  a.IdOperacion=b.idoperacion
                WHERE a.TipoDocumento = 'ope' AND CONVERT(DATE,FecOperacion)>=?";
        $bindings = [$fechaInicial];
        if($fechaFinal != ''){
            $sql .= " AND CONVERT(DATE,FecOperacion)<=? ";
            $bindings[] = $fechaFinal;
        }
        $sql .= " GROUP BY a.TipoDocumento, a.IdOperacion,b.idoperacion HAVING b.idoperacion IS NULL";
        return DB::select($sql, $bindings);
    }

    public static function busquedaOperacionId($idOperacion){
        [$placeholders, $ids] = self::listaPlaceholders($idOperacion);
        $sql = "SELECT TipoDocumento,IdOperacion,Cuota,IdCliente,FecOperacion,FecVencimiento,cuenta,Debito,Credito,Tipo,vendedor,Nota = REPLACE(Nota, 'ñ' , 'N'),FecModifica
                FROM V_Operaciones_Factoring a
                WHERE IdOperacion IN ($placeholders)";
        return DB::select($sql, $ids);
    }

    public static function mostrarCuponesGenerados(){
        $sql = "SELECT cd.*, c.NomCliente, c.ApeCliente, NombreUsuarioRegistro = CONCAT(u.Nombres,' ',u.Apellidos)  FROM CuponesDetalles cd
                INNER JOIN FactoringManagerDatos..Clientes c ON c.IdCliente = cd.IdCliente
                LEFT JOIN Usuario u ON u.DocUsuario = cd.UsuarioRegistro
                ORDER BY cd.IdCupon, Consecutivo ASC";
        return DB::select($sql);
    }

    public static function mostrarBancos(){
        $sql = "SELECT * FROM Bancos";
        return DB::connection('factoring')->select($sql);
    }

    public static function getOperacionById($idOperacion){
        $sql = " SELECT o.*, cli.NomCliente, cli.ApeCliente, cli.DirOficinaCliente, cli.TelDomicilioCliente, cli.TelMovilCliente
                    FROM Operaciones o
                    INNER JOIN Clientes cli ON cli.IdCliente = o.IdCliente
                    WHERE o.IdOperacion = ? ";
        return DB::connection('factoring')->select($sql, [$idOperacion]);
    }

    public static function addCabeceraCupones($usuario){
        $insert = DB::table('Cupones')->insert(['UsuarioRegistro'=>$usuario]);
        if ($insert){
            $sql = "SELECT TOP 1 * FROM Cupones ORDER BY IdCupon DESC";
            $cupon = DB::select($sql);
            return $cupon[0]->IdCupon;
        }else{
            return 0;
        }
    }

    public static function addDetalleCupones($idCupon,$consecutivo,$idOperacion,$idCliente,$valorCupon,$fechaLimiteCupon,$usuarioRegistro,$fechaRegistro){
        return DB::table('CuponesDetalles')->insert([
            'Consecutivo' => $consecutivo,
            'IdCupon' => $idCupon,
            'IdOperacion' => $idOperacion,
            'IdCliente' => $idCliente,
            'ValorCupon' => $valorCupon,
            'FechaLimiteCupon' => $fechaLimiteCupon,
            'UsuarioRegistro' => $usuarioRegistro,
            'FechaRegistro' => $fechaRegistro
        ]);
    }

    public static function deleteCabeceraAndDetalleCupones($idCupon){
        $delete = DB::delete("DELETE FROM CuponesDetalles WHERE IdCupon = ?", [$idCupon]);

        if($delete){
            return DB::delete("DELETE FROM Cupones WHERE IdCupon = ?", [$idCupon]);
        }
        return 'error';
    }
}
