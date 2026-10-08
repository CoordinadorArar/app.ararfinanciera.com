<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Terceros extends Model
{
    use HasFactory;

    protected $fillable = [
        'NombresTercero',
        'ApellidosTercero',
        'IdTipoDocumento',
        'DocumentoTercero',
        'FechaExpedicionDocumento',
        'LugarExpedicionDocumento',
        'FechaNacimientoTercero',
        'TelefonoTercero',
        'EmailTercero',
        'DireccionDomicilioTercero',
        'CiudadDomicilioTercero',
        'DepartamentoDomicilioTercero',
        'IdPagaduria',
        'IngresosTercero',
    ];
    public $timestamps = false;

    public static function mostrarInfoTercero($idTercero='',$documentoTercero=''){
        $sql = "SELECT *,FechaTerceroNacimiento=convert(varchar,FechaNacimientoTercero)
                FROM Terceros WHERE IdTercero = ?";
        $binding = $idTercero;
        if($documentoTercero != ''){
            $sql = "SELECT * FROM Terceros WHERE DocumentoTercero = ?";
            $binding = $documentoTercero;
        }
        $tercero = DB::select($sql, [$binding]);
        return $tercero;
    }

    public static function guardarFormatoTratamientoDatos($idTercero,$idProceso,$archivo,$sizeFile,$aceptado,$ruta){
        /**Documento del cliente */
        $getDocument = DB::select("SELECT DocumentoTercero FROM Terceros WHERE IdTercero=?", [$idTercero]);
        $documento =  $getDocument[0]->DocumentoTercero;
        $hoy = date('Y-m-d H:i:s');
        $fecha = explode(' ',$hoy)[0];
        $hora = explode(' ',$hoy)[1];
        /**Usuario */
        $idUsuario = auth()->id();
        $documentoUsuario = DB::select("SELECT DocumentoUsuario FROM Users WHERE IdUsuario=?", [$idUsuario]);
        /**Insertar en base de datos Protdatos datos del documento subido */
        $protdatos = DB::connection('protdatos');
        $insertProtdatos = $protdatos->transaction(function() use ($protdatos,$fecha,$hora,$ruta,$sizeFile,$archivo,$documento,$documentoUsuario){
            $idComprobante = $protdatos->table('Comprobante')->insertGetId([
                'ComprobanteID' => 0,
                'FormatosID' => 1,
                'ComprobanteFecha' => $fecha.'T'.$hora,
                'Ruta' => $ruta,
                'size' => $sizeFile,
                'Name' => $archivo,
                'Type' => 'PDF',
                'Observacion' => '',
                'DatosDoc' => $documento,
                'UserIng' => $documentoUsuario[0]->DocumentoUsuario,
                'Siglas' => 'AR'
            ]);
            $protdatos->table('Comprobante')->where('ID',$idComprobante)->update(['ComprobanteID' => $idComprobante]);
            return $protdatos->table('Autorizacion')->insert([
                'AutorizacionFecha' => $fecha.'T'.$hora,
                'Estado' => 'SI',
                'ComprobanteID' => $idComprobante,
                'MedioID' => 5,
                'CompaniaID' => 3,
                'Observacion' => 'Documento Generado desde el sitio web',
                'DatosDoc' => $documento
            ]);
        });
        if($insertProtdatos){
            $registroAntiguo = DB::select("SELECT TOP 1 IdTratamiento,RutaFormato FROM TratamientoDatos WHERE IdProceso=? ORDER BY IdTratamiento DESC", [$idProceso]);
            if(empty($registroAntiguo)){
                /**Insertar datos de proteccion de datos en base de datos del aplicativo */
                return DB::table('TratamientoDatos')->insert([
                    'IdTercero' => $idTercero,
                    'IdProceso' => $idProceso,
                    'RutaFormato' => 'storage/app/public/tratamiento_datos/'.$archivo,
                    'Aceptado' => $aceptado,
                    'ContactoTelefonico' => 1,
                    'ContactoCorreo' => 1
                ]);
            }elseif($registroAntiguo[0]->RutaFormato == ''){
                return DB::table('TratamientoDatos')->where('IdTratamiento',$registroAntiguo[0]->IdTratamiento)->update([
                    'RutaFormato' => 'storage/app/public/tratamiento_datos/'.$archivo,
                ]);
            }
        }
    }
    /**Insertar las opciones que aceptó el cliente en el formulario que llegó a su correo */
    public static function insertarTratamientoDatosAceptados($idTercero,$datosAceptacion,$idProceso=null){
        $datos = json_decode($datosAceptacion);
        return DB::table('TratamientoDatos')->insert([
            'IdTercero' => $idTercero,
            'IdProceso' => $idProceso,
            'Aceptado' => $datos->checkData,
            'ContactoTelefonico' => $datos->checkTelefonoContact,
            'ContactoCorreo' => $datos->checkEmailContact
        ]);
    }
    /**Insertar tratamiento de datos desde documentacion de soporte */
    public static function insertarTratamientoDatos($idTercero,$ruta,$datosAceptacion,$idProceso){
        $datos = json_decode($datosAceptacion);
        if(DB::table('TratamientoDatos')->where('IdProceso',$idProceso)->exists()){
            return DB::table('TratamientoDatos')->where('IdProceso',$idProceso)->update(['RutaFormato' => $ruta]) > 0;
        }
        return DB::table('TratamientoDatos')->insert([
            'IdTercero' => $idTercero,
            'IdProceso' => $idProceso,
            'RutaFormato' => $ruta,
            'Aceptado' => $datos->checkData,
            'ContactoTelefonico' => $datos->checkTelefonoContact,
            'ContactoCorreo' => $datos->checkEmailContact
        ]);
    }
    /**Cargar clientes de Factoring */
    public static function LoadClientes($idcliente){
        if(trim((string) $idcliente) === ''){
            return [];
        }
        $sql = "SELECT IdCliente,DigitoVerificaCli,TipoIdentificacionCliente,NomCliente,ApeCliente,DirOficinaCliente AS Direccion,
        IdPais = '169',substring(CodDaneCiudad,1,2) As IdDepartamento,substring(CodDaneCiudad,3,5) As IdCiudad,
        TelOficinaCliente AS Telefono,TelMovilCliente AS Celular,EmailCliente As Email,FecNacimiento AS FechaNacimiento,FecAperturaCliente AS FechaIngreso
        FROM FactoringManagerDatos..Clientes a
        LEFT JOIN FactoringManagerDatos..CiudadesAct b ON a.IdCiudadOficinaCliente = b.IdCiudad
        WHERE IdCliente = ?";
        return DB::select($sql, [(string) $idcliente]);
    }

    public static function cuentaBancariaCliente($idCliente, $nit){
        $sql = "SELECT TOP 1 c.NumCuenta,c.TipoCuenta,e.Codigo
                FROM FactoringManagerDatos..CuentasTerceros c
                LEFT JOIN FactoringManagerDatos..EntidadesFinancieras e ON e.IdEntidadF = c.IdEntidadF
                WHERE c.IdTercero IN (?,?) AND ISNULL(c.NumCuenta,'') <> ''
                ORDER BY c.Principal DESC, c.FecModifica DESC";
        return DB::select($sql, [(string) $idCliente, (string) $nit]);
    }

    public static function estadoSiesaCliente($nit){
        $sql = "SELECT TOP 1 1 AS tercero,
                CASE WHEN EXISTS(SELECT 1 FROM UNOEEARAR..t201_mm_clientes WHERE f201_rowid_tercero = a.f200_rowid AND f201_id_cia = a.f200_id_cia AND f201_id_sucursal = '001') THEN 1 ELSE 0 END AS cliente,
                CASE WHEN EXISTS(SELECT 1 FROM UNOEEARAR..t202_mm_proveedores WHERE f202_rowid_tercero = a.f200_rowid AND f202_id_cia = a.f200_id_cia AND f202_id_sucursal = '001') THEN 1 ELSE 0 END AS proveedor,
                CASE WHEN EXISTS(SELECT 1 FROM UNOEEARAR..t046_mm_cliente_base_impuesto WHERE f046_rowid_tercero = a.f200_rowid AND f046_id_cia = a.f200_id_cia AND f046_id_sucursal = '001')
                      AND EXISTS(SELECT 1 FROM UNOEEARAR..t047_mm_cliente_base_retencion WHERE f047_rowid_tercero = a.f200_rowid AND f047_id_cia = a.f200_id_cia AND f047_id_sucursal = '001') THEN 1 ELSE 0 END AS impuestos_cliente,
                CASE WHEN EXISTS(SELECT 1 FROM UNOEEARAR..t049_mm_prv_base_imp WHERE f049_rowid_tercero = a.f200_rowid AND f049_id_cia = a.f200_id_cia AND f049_id_sucursal = '001') THEN 1 ELSE 0 END AS impuestos_proveedor,
                CASE WHEN EXISTS(SELECT 1 FROM UNOEEARAR..t633_pe_prov_cuenta p JOIN UNOEEARAR..t634_pe_formato_prov_cuenta f ON f.f634_rowid_pe_prov_cuenta = p.f633_rowid
                      WHERE p.f633_rowid_proveedor = a.f200_rowid AND p.f633_id_cia = a.f200_id_cia AND f.f634_id_formato = 41) THEN 1 ELSE 0 END AS pago_bancolombia,
                CASE WHEN EXISTS(SELECT 1 FROM UNOEEARAR..t633_pe_prov_cuenta p JOIN UNOEEARAR..t634_pe_formato_prov_cuenta f ON f.f634_rowid_pe_prov_cuenta = p.f633_rowid
                      WHERE p.f633_rowid_proveedor = a.f200_rowid AND p.f633_id_cia = a.f200_id_cia AND f.f634_id_formato = 7) THEN 1 ELSE 0 END AS pago_bogota
                FROM UNOEEARAR..t200_mm_terceros a
                WHERE a.f200_id = ? AND a.f200_id_cia = ?";
        $fila = DB::select($sql, [(string) $nit, config('services.siesa.id_cia')]);
        return array_map('boolval', $fila ? (array) $fila[0] : array_fill_keys(['tercero','cliente','proveedor','impuestos_cliente','impuestos_proveedor','pago_bancolombia','pago_bogota'], 0));
    }

    public static function listadoClientesSiesa($busqueda, $estado, $pagina, $porPagina){
        $bindings = [config('services.siesa.id_cia')];
        $filtro = '';
        if(trim((string) $busqueda) !== ''){
            $patron = '%'.str_replace(['[','%','_'], ['[[]','[%]','[_]'], trim($busqueda)).'%';
            $filtro = " AND (c.IdCliente LIKE ? OR c.NomCliente + ' ' + ISNULL(c.ApeCliente,'') LIKE ?)";
            array_push($bindings, $patron, $patron);
        }
        $cte = "WITH base AS (
                SELECT c.IdCliente,c.DigitoVerificaCli,c.NomCliente,c.ApeCliente,c.FecModifica,
                CASE WHEN a.f200_rowid IS NULL THEN 0 ELSE 1 END AS Tercero,
                CASE WHEN EXISTS(SELECT 1 FROM UNOEEARAR..t201_mm_clientes WHERE f201_rowid_tercero = a.f200_rowid AND f201_id_cia = a.f200_id_cia AND f201_id_sucursal = '001') THEN 1 ELSE 0 END AS Cliente,
                CASE WHEN EXISTS(SELECT 1 FROM UNOEEARAR..t202_mm_proveedores WHERE f202_rowid_tercero = a.f200_rowid AND f202_id_cia = a.f200_id_cia AND f202_id_sucursal = '001') THEN 1 ELSE 0 END AS Proveedor
                FROM FactoringManagerDatos..Clientes c
                CROSS APPLY (SELECT CASE WHEN CHARINDEX('-',c.IdCliente) > 0 THEN LEFT(c.IdCliente,CHARINDEX('-',c.IdCliente)-1) ELSE c.IdCliente END AS Nit) n
                OUTER APPLY (SELECT TOP 1 f200_rowid,f200_id_cia FROM UNOEEARAR..t200_mm_terceros WHERE f200_id = n.Nit COLLATE Modern_Spanish_CI_AS AND f200_id_cia = ?) a
                WHERE c.FecModifica >= '20220101'".$filtro."
            ), est AS (
                SELECT *, CASE WHEN Tercero + Cliente + Proveedor = 3 THEN 'completo' WHEN Tercero + Cliente + Proveedor = 0 THEN 'pendiente' ELSE 'parcial' END AS Estado FROM base
            ) ";
        $contadores = ['pendiente' => 0, 'parcial' => 0, 'completo' => 0];
        foreach(DB::select($cte."SELECT Estado, COUNT(*) AS N FROM est GROUP BY Estado", $bindings) as $fila){
            $contadores[$fila->Estado] = (int) $fila->N;
        }
        $where = '';
        if($estado){
            $where = ' WHERE Estado = ?';
            $bindings[] = $estado;
        }
        $offset = ((int) $pagina - 1) * (int) $porPagina;
        $registros = DB::select($cte."SELECT * FROM est".$where." ORDER BY FecModifica DESC, IdCliente OFFSET ".$offset." ROWS FETCH NEXT ".(int) $porPagina." ROWS ONLY", $bindings);
        return [
            'registros' => $registros,
            'total' => $estado ? $contadores[$estado] : array_sum($contadores),
            'contadores' => $contadores,
        ];
    }
}
