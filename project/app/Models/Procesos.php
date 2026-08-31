<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Procesos extends Model
{
    use HasFactory;

    protected $fillable = [
        'IdTercero',
        'FechaCreacion',
        'IdUsuario',
        'EstadoProceso',
        'ValorCreditoSolicitado',
        'NumeroCuotas',
        'ValorCuota',
        'TasaInteres',
        'DocumentosCargados',
        'DocumentosAprobados'
    ];
    public $timestamps = false;
    /**Mostrar procesos */
    public static function mostrarProcesos($estado,$idProceso='',$usuario=''){
        $idUsuario = ($usuario != '')? $usuario : auth()->id();
        $sql = "SELECT p.*,t.*,td.NombreTipoDocumento FROM procesos p JOIN terceros t ON p.IdTercero=t.IdTercero
                INNER JOIN tiposdocumentos td ON t.IdTipoDocumento=td.IdTipodocumento
                INNER JOIN pagadurias pa ON t.IdPagaduria=pa.IdPagaduria ";
        $bindings = [];
        if($estado != ''){
            $sql .= " WHERE p.EstadoProceso=? ";
            $bindings[] = $estado;
            if ($idProceso != '') {
                $sql .= " AND p.IdProceso = ? ";
                $bindings[] = $idProceso;
            } else {
                $sql .= " AND p.IdUsuario=?";
                $bindings[] = $idUsuario;
            }
        }else{
            $sql .= " LEFT JOIN cuposresultadoscalculo cu ON cu.IdProceso=p.IdProceso";
            if ($idProceso != '') {
                $sql .= " WHERE p.IdProceso = ? ";
                $bindings[] = $idProceso;
            } else {
                $sql .= " WHERE p.IdUsuario=?";
                $bindings[] = $idUsuario;
            }
        }
        $procesos = DB::select($sql, $bindings);
        return $procesos;
    }
    /**Mostrar proceso con tercero que llega desde formulario */
    public static function mostrarTerceroProceso($idTercero,$estado=''){
        $sql = "SELECT TOP 1 * FROM Procesos WHERE IdTercero = ? ORDER BY IdProceso DESC";
        return DB::select($sql, [$idTercero]);
    }
    /**---------------------------------------------------------------------- */
    /**Mostrar proceso con idTercero, IdUsuario y estado coincidentes */
    public static function mostrarProcesoTercero($idTercero,$idUsuario,$estado){
        $sql = "SELECT MAX(IdProceso) AS IdProceso FROM Procesos WHERE EstadoProceso=? AND idUsuario=? AND IdTercero=?";
        $proceso = DB::select($sql, [$estado, $idUsuario, $idTercero]);
        return $proceso;
    }
    /**---------------------------------------------------------------------- */
    /**Mostrar datos de configuraciones para calcular cupos según pagadurias */
    public static function showConfig($idPagaduria,$tipoDescuento=''){
        $sql = "SELECT * FROM cuposconfigcalculos WHERE IdPagaduria = ?";
        $bindings = [$idPagaduria];
        if ($tipoDescuento != '') {
            $sql .= " AND TipoDescuentoMaximo=?";
            $bindings[] = $tipoDescuento;
        }
        $configuraciones = DB::select($sql, $bindings);
        return $configuraciones;
    }
    /**---------------------------------------------------------------------- */
    /**Mostrar resultados de la configuracion del calculo de cupo segun proceso*/
    public static function mostrarResultadoConfiguracion($idProceso){
        $sql = "SELECT * FROM CuposResultadosCalculo c
                INNER JOIN Procesos p ON c.IdProceso=p.IdProceso WHERE c.IdProceso = ?";
        $resultado = DB::select($sql, [$idProceso]);
        return $resultado;
    }
    /**---------------------------------------------------------------------- */
    /**Guardar datos del resultado del calculo del cupo disponible */
    public static function guardarCupoDisponible($idProceso,$Configuracion,$valores,$cupo,$accion=''){
        if($accion == 'guardar'){
            return DB::table('CuposResultadosCalculo')->insert([
                'IdProceso' => $idProceso,
                'Configuracion' => $Configuracion,
                'ValoresOperacion' => $valores,
                'CupoDisponible' => $cupo
            ]);
        }elseif($accion == 'actualizar'){
            return DB::table('CuposResultadosCalculo')->where('IdProceso',$idProceso)->update([
                'IdProceso' => $idProceso,
                'Configuracion' => $Configuracion,
                'ValoresOperacion' => $valores,
                'CupoDisponible' => $cupo
            ]);
        }
    }
    /**---------------------------------------------------------------------- */
    /**Cambiar el estado del proceso */
    public static function editarEstadoProceso($idProceso,$estado){
        $hoy = date('Y-m-d H:i:s');
        $fecha = explode(' ',$hoy)[0];
        $hora = explode(' ',$hoy)[1];
        return DB::table('Procesos')->where('IdProceso',$idProceso)->update([
            'EstadoProceso' => $estado,
            'updated_at' => $fecha.'T'.$hora
        ]);
    }

    public static function showProcessByState($estado){
        $sql = "SELECT p.IdProceso,p.FechaCreacion,p.EstadoProceso,p.IdUsuario,t.NombresTercero,t.ApellidosTercero,t.DocumentoTercero
                FROM procesos p INNER JOIN terceros t ON p.IdTercero=t.IdTercero ";
        $bindings = [];
        if ($estado != 'any') {
            $sql .= " WHERE p.EstadoProceso=? ";
            $bindings[] = $estado;
        }
        $procesos = DB::select($sql, $bindings);
        return $procesos;
    }

    public static function listaProcesos($idUsuario='',$filtro='',$busqueda=''){
        $rol = DB::select("SELECT * FROM RolUsuario WHERE IdUsuario=?", [$idUsuario]);
        $sql = "SELECT p.IdProceso,FechaCreacion=CONVERT(VARCHAR,p.FechaCreacion),p.EstadoProceso,t.NombresTercero,t.ApellidosTercero,
                    t.DocumentoTercero,pa.IdPagaduria,pa.NombrePagaduria,updated_at=CONVERT(VARCHAR,p.updated_at)
                    FROM Procesos p
                    INNER JOIN Terceros t ON p.IdTercero=t.IdTercero
                    INNER JOIN TiposDocumentos td ON t.IdTipoDocumento=td.IdTipoDocumento
                    INNER JOIN Departamentos dep ON t.DepartamentoDomicilioTercero=dep.IdDepartamento
                    INNER JOIN Municipios m ON t.CiudadDomicilioTercero=m.IdMunicipio
                    INNER JOIN Users u ON p.IdUsuario=u.idUsuario
                    INNER JOIN Pagadurias pa ON t.IdPagaduria=pa.IdPagaduria
                WHERE p.EstadoProceso > 0 ";
        $bindings = [];
        switch($rol[0]->IdRol){
            case 1: $sql .= " AND p.EstadoProceso IN(1,2,3,4,5) "; break;
            case 2: $sql .= " AND p.EstadoProceso IN(1,2,3,4,5) "; break;
            case 3: $sql .= " AND p.EstadoProceso IN(1,2,3) "; break;
            case 4: $sql .= " AND p.EstadoProceso IN(3) "; break;
            case 5: $sql .= " AND p.EstadoProceso IN(2,3) "; break;
        }
        if($filtro != ''){
            $sql .= " AND p.EstadoProceso = ? ";
            $bindings[] = $filtro;
        }
        if($busqueda != ''){
            $sql .= " AND t.DocumentoTercero LIKE ? ";
            $bindings[] = '%'.$busqueda.'%';
        }
        $procesos = DB::select($sql, $bindings);
        return $procesos;
    }

    /**Consultar informacion del proceso single  */
    public static function mostarInfoProceso($idProceso){
        $sql = "SELECT p.IdProceso,FechaCreacion=CONVERT(VARCHAR,p.FechaCreacion),p.EstadoProceso,p.ValorCreditoSolicitado,p.DocumentosCargados,
                    t.IdTercero,t.NombresTercero,t.ApellidosTercero,t.DocumentoTercero
                    FROM Procesos p
                    INNER JOIN Terceros t ON p.IdTercero=t.IdTercero
                    INNER JOIN TiposDocumentos td ON t.IdTipoDocumento=td.IdTipoDocumento
                    WHERE p.IdProceso = ?";
        $procesoInfo = DB::select($sql, [$idProceso]);
        return ['status' => true, 'data' => $procesoInfo[0]];
    }

    /**Consultar informacion del proceso segun la peticion requerida en la vista */
    public static function mostrarInfoProcesos($idProceso,$accion){
        switch ($accion){
            case 'uploadDocs':
                $proceso = DB::select("SELECT * FROM Procesos WHERE IdProceso = ?", [$idProceso]);
                $idTercero = $proceso[0]->IdTercero;
                $tratamiento = DB::select("SELECT * FROM TratamientoDatos WHERE IdTercero=?", [$idTercero]);
                $tratamientoAceptado = ($tratamiento > 0)? true : false;
                $procesoInfo = DB::select("SELECT p.IdProceso,FechaCreacion=CONVERT(VARCHAR,p.FechaCreacion),p.EstadoProceso,p.ValorCreditoSolicitado,p.DocumentosCargados,
                                            t.IdTercero,t.NombresTercero,t.ApellidosTercero,t.DocumentoTercero,pa.IdPagaduria,pa.NombrePagaduria,cu.CupoDisponible
                                            FROM Procesos p
                                            INNER JOIN Terceros t ON p.IdTercero=t.IdTercero
                                            INNER JOIN TiposDocumentos td ON t.IdTipoDocumento=td.IdTipoDocumento
                                            INNER JOIN Users u ON p.IdUsuario=u.idUsuario
                                            INNER JOIN Pagadurias pa ON t.IdPagaduria=pa.IdPagaduria
                                            INNER JOIN CuposResultadosCalculo cu ON cu.IdProceso=p.IdProceso
                                        WHERE p.IdProceso = ?", [$idProceso]);
                $idPagaduria = $procesoInfo[0]->IdPagaduria;
                $documentos = DB::select("SELECT * FROM DocumentosSolicitados ds
                                            INNER JOIN PagaduriasDocumentos pd ON ds.IdDocumentoSolicitado=pd.IdDocumento
                                            INNER JOIN Pagadurias p ON pd.IdPagaduria=p.IdPagaduria
                                            WHERE p.IdPagaduria = ?", [$idPagaduria]);
                return ['tratamiento' => $tratamientoAceptado, 'proceso' => $procesoInfo, 'documentos' => $documentos];
            break;
            case 'checkDocs':
                $proceso = DB::select("SELECT * FROM Procesos p INNER JOIN Terceros t ON p.IdTercero=t.IdTercero WHERE IdProceso = ?", [$idProceso]);
                $idTercero = $proceso[0]->IdTercero;
                $tratamiento = DB::select("SELECT * FROM TratamientoDatos WHERE IdTercero=?", [$idTercero]);
                $idPagaduria = $proceso[0]->IdPagaduria;
                $documentos = DB::select("SELECT * FROM DocumentosSolicitados ds
                                            INNER JOIN PagaduriasDocumentos pd ON ds.IdDocumentoSolicitado=pd.IdDocumento
                                            INNER JOIN Pagadurias p ON pd.IdPagaduria=p.IdPagaduria
                                            WHERE p.IdPagaduria = ?", [$idPagaduria]);
                return ['proceso'=>$proceso, 'documentos'=>$documentos, 'tratamiento'=>$tratamiento];
            break;
            case 'approveCredit':
                $proceso = DB::select("SELECT *,FechaTerceroNacimiento=CONVERT(VARCHAR,FechaNacimientoTercero) FROM Procesos p
                                        INNER JOIN Terceros t ON p.IdTercero=t.IdTercero
                                        INNER JOIN TiposDocumentos td ON td.IdTipoDocumento=t.IdTipoDocumento
                                        INNER JOIN CuposResultadosCalculo cu ON cu.IdProceso=p.IdProceso
                                        INNER JOIN Municipios m ON m.IdMunicipio=t.CiudadDomicilioTercero
                                        INNER JOIN Departamentos d ON d.IdDepartamento=m.IdDepartamento
                                        INNER JOIN Pagadurias pa ON pa.IdPagaduria=t.IdPagaduria
                                        INNER JOIN Users u ON u.IdUsuario=p.IdUsuario
                                        WHERE p.IdProceso = ?", [$idProceso]);
                $idTercero = $proceso[0]->IdTercero;
                $tratamiento = DB::select("SELECT * FROM TratamientoDatos WHERE IdTercero=?", [$idTercero]);
                $idPagaduria = $proceso[0]->IdPagaduria;
                $documentos = DB::select("SELECT * FROM DocumentosSolicitados ds
                                            INNER JOIN PagaduriasDocumentos pd ON ds.IdDocumentoSolicitado=pd.IdDocumento
                                            INNER JOIN Pagadurias p ON pd.IdPagaduria=p.IdPagaduria
                                            WHERE p.IdPagaduria = ?", [$idPagaduria]);
                return ['tratamiento' => $tratamiento, 'proceso' => $proceso, 'documentos' => $documentos];
            break;
        }
    }

    /**Actualizar string con Ids de los documentos de soporte ya cargados */
    public static function actualizarDocumentosSubidos($idProceso,$idDocumento){
        $consultar = DB::select("SELECT * FROM Procesos WHERE idProceso = ?", [$idProceso]);
        $nuevosDocumentos = $consultar[0]->DocumentosCargados.$idDocumento.',';
        return DB::table('Procesos')->where('IdProceso',$idProceso)->update([
            'DocumentosCargados' => $nuevosDocumentos
        ]);
    }

    /**Eliminar de Tratamiento de Datos */
    public static function eliminarTratamientoDatos($idTercero){
        $ultimoTratamiento = DB::select("SELECT MAX(IdTratamiento) AS IdTratamiento FROM TratamientoDatos WHERE IdTercero=?", [$idTercero]);
        $idTratamiento = $ultimoTratamiento[0]->IdTratamiento;
        return DB::table('TratamientoDatos')->where('IdTratamiento',$idTratamiento)->delete();
    }

    /**Gestionar documentos de sopoorte ya subidos */
    public static function gestionDocumentosSoporte($idProceso,$idDocumento,$accion){
        $proceso = DB::select("SELECT * FROM Procesos WHERE idProceso = ?", [$idProceso]);
        if($accion == 'aceptar'){
            $nuevoValor = $proceso[0]->DocumentosAprobados.$idDocumento.',';
            return DB::table('Procesos')->where('IdProceso',$idProceso)->update([
                'DocumentosAprobados' => $nuevoValor,
            ]);
        }elseif($accion == 'rechazar'){
            $arrayAprobados = explode(',',$proceso[0]->DocumentosAprobados);
            $arrayCargados = explode(',',$proceso[0]->DocumentosCargados);
            $key = array_search($idDocumento,$arrayAprobados);
            $key2 = array_search($idDocumento,$arrayCargados);
            unset($arrayAprobados[$key]);
            unset($arrayCargados[$key2]);
            $nuevoValor = ''; $nuevoValorCargados = '';
            foreach($arrayAprobados as $key => $value){
                if($value != ''){
                    $nuevoValor .= $value.',';
                }
            }
            foreach($arrayCargados as $key => $value){
                if($value != ''){
                    $nuevoValorCargados .= $value.',';
                }
            }
            return DB::table('Procesos')->where('IdProceso',$idProceso)->update([
                'DocumentosCargados' =>$nuevoValorCargados,
                'DocumentosAprobados' => $nuevoValor
            ]);
        }
    }
    /**---------------------------------------------------------------------- */
    /**Eeditar proceso recibiendo el id y un array con los datos a editar */
    public static function editarProceso($idProceso,$datos){
        return DB::table('Procesos')->where('IdProceso',$idProceso)->update($datos);
    }
    /**---------------------------------------------------------------------- */
    /**Editar resultado de calculo del cupo, normalmente solo sucede al momento de aprobar el credito, pues es posible que deba disminuirse segun lo informe la pagaduria */
    public static function editarResultadoCalculo($idProceso,$datos){
        $consultar = DB::select("SELECT MAX(IdResultado) AS IdResultado FROM CuposResultadosCalculo WHERE idProceso = ?", [$idProceso]);
        $idResultado = $consultar[0]->IdResultado;
        return DB::table('CuposResultadosCalculo')->where('IdResultado',$idResultado)->update($datos);
    }
}
