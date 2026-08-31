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

    public static function guardarFormatoTratamientoDatos($idTercero,$idProceso,$archivo,$sizeFile,$aceptado){
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
        $insertComprobante = DB::connection('protdatos')->table('Comprobante')->insert([
            'ComprobanteID' => 1,
            'FormatosID' => 1,
            'ComprobanteFecha' => $fecha.'T'.$hora,
            'Ruta' => base_path().'/storage/app/public/tratamiento_datos/'.$archivo,
            'size' => $sizeFile,
            'Name' => $archivo,
            'Type' => 'PDF',
            'Observacion' => '',
            'DatosDoc' => $documento,
            'UserIng' => $documentoUsuario[0]->DocumentoUsuario,
            'Siglas' => 'AR'
        ]);
        if($insertComprobante){
            /**Insertar autorización en base de Protdatos */
            $idComprobante = DB::connection('protdatos')->select("SELECT MAX(ComprobanteID)AS ComprobanteID FROM Comprobante WHERE DatosDoc=?", [$documento]);
            $insertProtdatos = DB::connection('protdatos')->table('Autorizacion')->insert([
                'AutorizacionFecha' => $fecha.'T'.$hora,
                'Estado' => 'SI',
                'ComprobanteID' => $idComprobante[0]->ComprobanteID,
                'MedioID' => 5,
                'CompaniaID' => 3,
                'Observacion' => 'Documento Generado desde el sitio web',
                'DatosDoc' => $documento
            ]);
            if($insertProtdatos){
                $registroAntiguo = DB::select("SELECT MAX(IdTratamiento)AS IdTratamiento,RutaFormato,IdTercero FROM TratamientoDatos WHERE idTercero=? GROUP BY RutaFormato,IdTercero", [$idTercero]);
                /**Actualizar proceso insertando el id del documento en la tabla de Procesos de la BD */
                $actualizarDocumentos = DB::table('Procesos')->where('IdProceso',$idProceso)->update([
                    'DocumentosCargados' => '2,'
                ]);
                if(empty($registroAntiguo)){
                    /**Insertar datos de proteccion de datos en base de datos del aplicativo */
                    return DB::table('TratamientoDatos')->insert([
                        'IdTercero' => $idTercero,
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
    }
    /**Insertar las opciones que aceptó el cliente en el formulario que llegó a su correo */
    public static function insertarTratamientoDatosAceptados($idTercero,$datosAceptacion){
        $datos = json_decode($datosAceptacion);
        return DB::table('TratamientoDatos')->insert([
            'IdTercero' => $idTercero,
            'Aceptado' => $datos->checkData,
            'ContactoTelefonico' => $datos->checkTelefonoContact,
            'ContactoCorreo' => $datos->checkEmailContact
        ]);
    }
    /**Insertar tratamiento de datos desde documentacion de soporte */
    public static function insertarTratamientoDatos($idTercero,$ruta,$datosAceptacion){
        $datos = json_decode($datosAceptacion);
        return DB::table('TratamientoDatos')->insert([
            'IdTercero' => $idTercero,
            'RutaFormato' => $ruta,
            'Aceptado' => $datos->checkData,
            'ContactoTelefonico' => $datos->checkTelefonoContact,
            'ContactoCorreo' => $datos->checkEmailContact
        ]);
    }
    /**Creación de clientes en SIESA */
    public static function validarUsuariosSiesa($fecha=''){
        $sql = "SELECT c.IdCliente,ISNULL(a.f200_id,0) as IdSiesa,c.NomCliente,c.ApeCliente,isnull(b.f201_rowid_tercero,0) AS cliente,ISNULL(d.f202_rowid_tercero,0) as Idproveedortercero
                FROM FactoringManagerDatos..Clientes c
                LEFT JOIN UNOEEARAR..t200_mm_terceros a 
                ON a.f200_id = CASE WHEN CHARINDEX('-',IdCliente) > 0 THEN LEFT(c.IdCliente,CHARINDEX('-',c.IdCliente)-1) ELSE c.IdCliente END 
                COLLATE Modern_Spanish_CI_AS AND a.f200_id_cia = 7
                LEFT JOIN UNOEEARAR..t201_mm_clientes b ON a.f200_rowid = b.f201_rowid_tercero
                LEFT JOIN UNOEEARAR..t202_mm_proveedores d ON a.f200_rowid = d.f202_rowid_tercero
                WHERE FecModifica >= '20220101'
                GROUP BY c.IdCliente,a.f200_id,a.f200_id_cia,FecModifica,c.NomCliente,c.ApeCliente,b.f201_rowid_tercero,d.f202_rowid_tercero
                ORDER BY FecModifica ASC";
        return DB::select($sql);
    }
    /**Cargar clientes de Factoring */
    public static function LoadClientes($idcliente){
        if($idcliente != ''){
            $sql = "SELECT IdCliente,replace(NomCliente,'ñ','n') as NomCliente,replace(ApeCliente,'ñ','N') as ApeCliente ,DirOficinaCliente AS Direccion,
            IdPais = '169',substring(CodDaneCiudad,1,2) As IdDepartamento,substring(CodDaneCiudad,3,5) As IdCiudad,
            TelOficinaCliente AS Telefono,TelMovilCliente AS Celular,EmailCliente As Email,FecNacimiento AS FechaNacimiento
            FROM FactoringManagerDatos..Clientes a
            JOIN FactoringManagerDatos..CiudadesAct b ON a.IdCiudadOficinaCliente = b.IdCiudad
            WHERE IdCliente = ?";
            return DB::select($sql, [$idcliente]);
        }
        else{
            $sql = "SELECT * FROM FactoringManagerDatos..Clientes";
            return DB::select($sql);
        }
    }

    public static function ValidarTerceros($idcliente){
        $sql = "SELECT * FROM UNOEEARAR.dbo.t200_mm_terceros WHERE f200_id_cia = '7' AND f200_nit = ? ";
        return DB::select($sql, [$idcliente]);
    }

    public static function ValidarCliente($idcliente){
        $sql = "SELECT f200_id_cia AS Compañia ,f200_nit AS Nit,f200_razon_social AS Nombre,f200_apellido1,f200_apellido2,f200_nombres,b.f200_id_tipo_ident
                ,f015_email AS mail,f015_celular AS Celular,f015_telefono AS Telefono,f015_direccion1 AS Direccion,f015_id_pais AS Pais,
                f015_id_depto AS Dpto,f015_id_ciudad AS Ciudad
                ,f201_id_sucursal,f201_id_vendedor,f201_id_cond_pago,f201_id_moneda,f015_rowid,a.f201_id_tipo_cli
                FROM UNOEEARAR.dbo.t200_mm_terceros b
                INNER JOIN UNOEEARAR.dbo.t201_mm_clientes a ON b.f200_rowid=a.f201_rowid_tercero
                AND f201_id_sucursal='001'
                INNER JOIN UNOEEARAR.dbo.t015_mm_contactos d ON  f015_rowid=f201_rowid_contacto
                WHERE f200_nit = ? AND f200_id_cia = '7' ";
        return DB::select($sql, [$idcliente]);
    }

    public static function ValidarProveedor($idcliente){
        $sql = "SELECT * FROM UNOEEARAR.dbo.t200_mm_terceros a
                JOIN UNOEEARAR.dbo.t202_mm_proveedores b ON a.f200_rowid = b.f202_rowid_tercero AND f202_id_cia = 7
                WHERE f200_nit = ?";
        return DB::select($sql, [$idcliente]);
    }
}
