<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\FlujoProceso;
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
    /**---------------------------------------------------------------------- */
    public static function procesoAbierto($idTercero){
        $procesos = DB::select("SELECT IdProceso, EstadoProceso FROM Procesos WITH (UPDLOCK, HOLDLOCK) WHERE IdTercero = ? ORDER BY IdProceso DESC", [$idTercero]);
        foreach($procesos as $proceso){
            if($proceso->EstadoProceso == 1){
                return $proceso->IdProceso;
            }
        }
        return null;
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

    public static function propios($consulta,$idUsuario){
        return $consulta->where(function($q) use ($idUsuario){
            $q->where('p.IdUsuario',$idUsuario)->orWhereExists(function($s) use ($idUsuario){
                $s->selectRaw('1')->from('ProcesosHistorial as h')->whereColumn('h.IdProceso','p.IdProceso')->whereNull('h.EstadoAnterior')->where('h.IdUsuario',$idUsuario);
            });
        });
    }

    public static function puedeVer($idProceso,$idUsuario=null,$roles=null,$flujo=null){
        $idUsuario = $idUsuario ?: auth()->id();
        $flujo = $flujo ?: FlujoProceso::instancia();
        $roles = $roles ?? FlujoProceso::rolesUsuario($idUsuario);
        if(!$flujo->soloPropios($roles)){
            return true;
        }
        return self::propios(DB::table('Procesos as p')->where('p.IdProceso',$idProceso),$idUsuario)->exists();
    }

    public static function nombresUsuarios(array $ids){
        $ids = array_values(array_unique(array_filter($ids)));
        return $ids ? DB::connection('identidad')->table('users')->whereIn('idUsuario',$ids)->pluck('nombreUsuario','idUsuario')->all() : [];
    }

    public static function rolesUsuarios(array $ids){
        $ids = array_values(array_unique(array_filter($ids)));
        $roles = [];
        foreach($ids ? DB::connection('identidad')->table('RolUsuario as ru')->join('Roles as r','r.IdRol','=','ru.IdRol')->whereIn('ru.IdUsuario',$ids)->orderBy('r.IdRol')->get(['ru.IdUsuario','r.NombreRol']) : [] as $fila){
            $roles[$fila->IdUsuario] = isset($roles[$fila->IdUsuario]) ? $roles[$fila->IdUsuario].', '.$fila->NombreRol : $fila->NombreRol;
        }
        return $roles;
    }

    public static function listaProcesos($idUsuario,array $filtros=[]){
        $flujo = FlujoProceso::instancia();
        $roles = FlujoProceso::rolesUsuario($idUsuario);
        $visibles = $flujo->estadosVisibles($roles);
        $base = DB::table('Procesos as p')->join('Terceros as t','t.IdTercero','=','p.IdTercero')->leftJoin('Pagadurias as pa','pa.IdPagaduria','=','t.IdPagaduria')
            ->whereIn('p.EstadoProceso',$visibles ?: [-1]);
        if($flujo->soloPropios($roles)){
            self::propios($base,$idUsuario);
        }
        $contadores = array_fill_keys($visibles,0);
        foreach((clone $base)->groupBy('p.EstadoProceso')->selectRaw('p.EstadoProceso, COUNT(*) AS total')->get() as $fila){
            $contadores[(int) $fila->EstadoProceso] = (int) $fila->total;
        }
        $busqueda = trim((string) ($filtros['busqueda'] ?? ''));
        if($busqueda !== ''){
            $like = '%'.str_replace(['[','%','_'],['[[]','[%]','[_]'],$busqueda).'%';
            $base->where(function($q) use ($like){
                $q->whereRaw('CONVERT(varchar(20),t.DocumentoTercero) LIKE ?',[$like])->orWhereRaw("ISNULL(t.NombresTercero,'') + ' ' + ISNULL(t.ApellidosTercero,'') LIKE ?",[$like]);
            });
        }
        if(isset($filtros['estado']) && $filtros['estado'] !== ''){
            $base->where('p.EstadoProceso',(int) $filtros['estado']);
        }else{
            $base->where('p.EstadoProceso','>',0);
        }
        $total = (clone $base)->count();
        $page = max(1,(int) ($filtros['page'] ?? 1));
        $perPage = (int) ($filtros['perPage'] ?? 20);
        $columnas = ['cliente'=>"ISNULL(t.NombresTercero,'') + ' ' + ISNULL(t.ApellidosTercero,'')",'monto'=>'p.ValorCreditoSolicitado','estado'=>'p.EstadoProceso','fecha'=>'ISNULL(p.updated_at,p.FechaCreacion)'];
        $direccion = strtolower((string) ($filtros['direccion'] ?? '')) === 'asc' ? 'ASC' : 'DESC';
        $filas = $base->orderByRaw(($columnas[$filtros['orden'] ?? 'fecha'] ?? $columnas['fecha']).' '.$direccion)->orderBy('p.IdProceso',$direccion)->offset(($page - 1) * $perPage)->limit($perPage)
            ->selectRaw("p.IdProceso,p.EstadoProceso,p.IdUsuario,p.ValorCreditoSolicitado,t.DocumentoTercero,t.NombresTercero,t.ApellidosTercero,pa.NombrePagaduria,CONVERT(varchar(10),p.FechaCreacion,23) AS FechaCreacion,CONVERT(varchar(16),ISNULL(p.updated_at,p.FechaCreacion),120) AS FechaActualizacion")->get();
        $asesores = self::nombresUsuarios($filas->pluck('IdUsuario')->all());
        $registros = $filas->map(function($fila) use ($flujo,$roles,$asesores){
            return [
                'IdProceso' => (int) $fila->IdProceso,
                'documento' => (string) $fila->DocumentoTercero,
                'nombre' => trim($fila->NombresTercero.' '.$fila->ApellidosTercero),
                'pagaduria' => $fila->NombrePagaduria !== null ? trim($fila->NombrePagaduria) : null,
                'monto' => is_numeric($fila->ValorCreditoSolicitado) ? $fila->ValorCreditoSolicitado + 0 : null,
                'estado' => (int) $fila->EstadoProceso,
                'estadoNombre' => $flujo->nombreEstado($fila->EstadoProceso),
                'fechaCreacion' => $fila->FechaCreacion,
                'fechaActualizacion' => $fila->FechaActualizacion,
                'asesor' => $asesores[$fila->IdUsuario] ?? null,
                'accionPrincipal' => $flujo->accionPrincipal($fila->EstadoProceso,$roles)
            ];
        })->all();
        $resumen = [];
        foreach($contadores as $estado => $cantidad){
            $resumen[] = ['estado'=>$estado,'estadoNombre'=>$flujo->nombreEstado($estado),'total'=>$cantidad];
        }
        return ['registros'=>$registros,'total'=>$total,'page'=>$page,'perPage'=>$perPage,'contadores'=>$resumen];
    }

    public static function resumenInicio($idUsuario,array $roles,$flujo,$limite=10){
        $visibles = $flujo->estadosVisibles($roles);
        $base = DB::table('Procesos as p')->join('Terceros as t','t.IdTercero','=','p.IdTercero')->whereIn('p.EstadoProceso',$visibles ?: [-1]);
        if($flujo->soloPropios($roles)){
            self::propios($base,$idUsuario);
        }
        $contadores = array_fill_keys($visibles,0);
        foreach((clone $base)->groupBy('p.EstadoProceso')->selectRaw('p.EstadoProceso, COUNT(*) AS total')->get() as $fila){
            $contadores[(int) $fila->EstadoProceso] = (int) $fila->total;
        }
        $pendientes = array_values(array_filter($visibles,function($estado) use ($flujo,$roles){
            return $flujo->puedeTransicion($estado,$estado + 1,$roles);
        }));
        $filas = $pendientes ? $base->whereIn('p.EstadoProceso',$pendientes)->orderByRaw('ISNULL(p.updated_at,p.FechaCreacion) ASC')->orderBy('p.IdProceso')->limit($limite)
            ->selectRaw("p.IdProceso,p.EstadoProceso,t.NombresTercero,t.ApellidosTercero,CONVERT(varchar(16),ISNULL(p.updated_at,p.FechaCreacion),120) AS Fecha")->get()->all() : [];
        return ['contadores'=>$contadores,'estadosPendientes'=>$pendientes,'pendientes'=>$filas];
    }

    /**Consultar informacion del proceso segun la peticion requerida en la vista */
    public static function mostrarInfoProcesos($idProceso,$accion){
        switch ($accion){
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
                $tratamiento = DB::select("SELECT * FROM TratamientoDatos WHERE IdProceso=?", [$idProceso]);
                $idPagaduria = $proceso[0]->IdPagaduria;
                $documentos = DB::select("SELECT * FROM DocumentosSolicitados ds
                                            INNER JOIN PagaduriasDocumentos pd ON ds.IdDocumentoSolicitado=pd.IdDocumento
                                            INNER JOIN Pagadurias p ON pd.IdPagaduria=p.IdPagaduria
                                            WHERE p.IdPagaduria = ?", [$idPagaduria]);
                return ['tratamiento' => $tratamiento, 'proceso' => $proceso, 'documentos' => $documentos];
            break;
        }
    }

    public static function eliminarTratamientoDatos($idProceso){
        $idTratamiento = DB::table('TratamientoDatos')->where('IdProceso',$idProceso)->max('IdTratamiento');
        return $idTratamiento ? DB::table('TratamientoDatos')->where('IdTratamiento',$idTratamiento)->delete() : 0;
    }

    public static function estadoTratamiento($idProceso){
        $registros = DB::table('TratamientoDatos')->where('IdProceso',$idProceso)->get();
        $documentoCargado = DB::table('ProcesosDocumentos')->where('IdProceso',$idProceso)->where('IdDocumento',2)->whereIn('Estado',['cargado','aprobado'])->exists()
            || $registros->contains(function($registro){ return trim((string) $registro->RutaFormato) !== ''; });
        $aceptado = $registros->contains(function($registro){ return $registro->Aceptado == 1 && trim((string) $registro->RutaFormato) === ''; });
        return ['documentoCargado'=>$documentoCargado,'aceptado'=>$aceptado,'completo'=>$documentoCargado || $aceptado];
    }

    public static function documentosProceso($idProceso){
        $idPagaduria = DB::table('Procesos as p')->join('Terceros as t','t.IdTercero','=','p.IdTercero')->where('p.IdProceso',$idProceso)->value('t.IdPagaduria');
        $filas = DB::table('PagaduriasDocumentos as pd')->join('DocumentosSolicitados as ds','ds.IdDocumentoSolicitado','=','pd.IdDocumento')
            ->leftJoin('ProcesosDocumentos as d',function($j) use ($idProceso){ $j->on('d.IdDocumento','=','pd.IdDocumento')->where('d.IdProceso','=',$idProceso); })
            ->leftJoin('MotivosRechazo as m','m.IdMotivo','=','d.IdMotivo')
            ->where('pd.IdPagaduria',$idPagaduria)->orderBy('pd.IdDocumento')
            ->selectRaw('pd.IdDocumento,ds.NombreDocumento,pd.Requerido,d.Estado,d.NombreArchivo,d.IdMotivo,m.NombreMotivo,d.Observacion,d.IdUsuario,CONVERT(varchar(19),ISNULL(d.updated_at,d.Fecha),120) AS Fecha')->get();
        $digital = $filas->contains('IdDocumento',2) && self::estadoTratamiento($idProceso)['aceptado'];
        return $filas->map(function($fila) use ($digital){
            $estado = $fila->Estado ?: (($fila->IdDocumento == 2 && $digital) ? 'aprobado' : null);
            return [
                'IdDocumento' => (int) $fila->IdDocumento,
                'nombre' => $fila->NombreDocumento,
                'requerido' => $fila->Requerido === null || (int) $fila->Requerido === 1,
                'estado' => $estado,
                'origen' => $fila->Estado ? 'archivo' : ($estado ? 'digital' : null),
                'nombreArchivo' => $fila->NombreArchivo,
                'idMotivo' => $fila->IdMotivo ? (int) $fila->IdMotivo : null,
                'motivo' => $fila->NombreMotivo,
                'observacion' => $fila->Observacion,
                'fecha' => $fila->Fecha
            ];
        })->all();
    }

    public static function documentosPendientes($idProceso){
        $pendientes = [];
        foreach(self::documentosProceso($idProceso) as $documento){
            if($documento['requerido'] && $documento['estado'] !== 'aprobado'){
                $pendientes[] = $documento['nombre'];
            }
        }
        return $pendientes;
    }

    public static function guardarDocumento($idProceso,$idDocumento,$ruta,$nombreArchivo,$idUsuario=null){
        $ahora = date('Y-m-d\TH:i:s');
        $datos = ['Estado'=>'cargado','Ruta'=>$ruta,'NombreArchivo'=>$nombreArchivo,'IdMotivo'=>null,'Observacion'=>null,'IdUsuario'=>$idUsuario ?: auth()->id(),'Fecha'=>$ahora,'updated_at'=>$ahora];
        return DB::transaction(function() use ($idProceso,$idDocumento,$datos){
            $existe = DB::selectOne('SELECT IdProcesoDocumento FROM ProcesosDocumentos WITH (UPDLOCK, HOLDLOCK) WHERE IdProceso = ? AND IdDocumento = ?',[$idProceso,$idDocumento]);
            if($existe){
                return DB::table('ProcesosDocumentos')->where('IdProcesoDocumento',$existe->IdProcesoDocumento)->update($datos) > 0;
            }
            return DB::table('ProcesosDocumentos')->insert($datos + ['IdProceso'=>$idProceso,'IdDocumento'=>$idDocumento]);
        });
    }

    public static function documentoProceso($idProceso,$idDocumento){
        return DB::table('ProcesosDocumentos')->where('IdProceso',$idProceso)->where('IdDocumento',$idDocumento)->first();
    }

    public static function cambiarEstadoDocumento($idProceso,$idDocumento,$estado,$idMotivo=null,$observacion=null,$idUsuario=null){
        return DB::table('ProcesosDocumentos')->where('IdProceso',$idProceso)->where('IdDocumento',$idDocumento)->update([
            'Estado' => $estado,
            'IdMotivo' => $idMotivo ?: null,
            'Observacion' => $observacion,
            'IdUsuario' => $idUsuario ?: auth()->id(),
            'updated_at' => date('Y-m-d\TH:i:s')
        ]);
    }

    public static function historial($idProceso){
        $flujo = FlujoProceso::instancia();
        $filas = DB::table('ProcesosHistorial as h')->leftJoin('MotivosRechazo as m','m.IdMotivo','=','h.IdMotivo')->where('h.IdProceso',$idProceso)
            ->orderBy('h.Fecha')->orderBy('h.IdHistorial')
            ->selectRaw('h.IdHistorial,h.EstadoAnterior,h.EstadoNuevo,h.IdMotivo,m.NombreMotivo,h.Observacion,h.IdUsuario,CONVERT(varchar(19),h.Fecha,120) AS Fecha')->get();
        $usuarios = self::nombresUsuarios($filas->pluck('IdUsuario')->all());
        $roles = self::rolesUsuarios($filas->pluck('IdUsuario')->all());
        return $filas->map(function($fila) use ($flujo,$usuarios,$roles){
            return [
                'IdHistorial' => (int) $fila->IdHistorial,
                'estadoAnterior' => $fila->EstadoAnterior === null ? null : (int) $fila->EstadoAnterior,
                'estadoAnteriorNombre' => $flujo->nombreEstado($fila->EstadoAnterior),
                'estadoNuevo' => (int) $fila->EstadoNuevo,
                'estadoNuevoNombre' => $flujo->nombreEstado($fila->EstadoNuevo),
                'idMotivo' => $fila->IdMotivo ? (int) $fila->IdMotivo : null,
                'motivo' => $fila->NombreMotivo,
                'observacion' => $fila->Observacion,
                'idUsuario' => $fila->IdUsuario ? (int) $fila->IdUsuario : null,
                'usuario' => $usuarios[$fila->IdUsuario] ?? null,
                'rol' => $roles[$fila->IdUsuario] ?? null,
                'fecha' => $fila->Fecha
            ];
        })->all();
    }
    /**---------------------------------------------------------------------- */
    /**Eeditar proceso recibiendo el id y un array con los datos a editar */
    public static function editarProceso($idProceso,$datos){
        return DB::table('Procesos')->where('IdProceso',$idProceso)->update($datos);
    }
    /**---------------------------------------------------------------------- */
    /**Editar resultado de calculo del cupo, normalmente solo sucede al momento de aprobar el credito, pues es posible que deba disminuirse segun lo informe la pagaduria */
    public static function cupoProceso($idProceso){
        return DB::table('CuposResultadosCalculo')->where('IdProceso',$idProceso)->orderByDesc('IdResultado')->value('CupoDisponible');
    }
    public static function editarResultadoCalculo($idProceso,$datos){
        $consultar = DB::select("SELECT MAX(IdResultado) AS IdResultado FROM CuposResultadosCalculo WHERE idProceso = ?", [$idProceso]);
        $idResultado = $consultar[0]->IdResultado;
        return DB::table('CuposResultadosCalculo')->where('IdResultado',$idResultado)->update($datos);
    }
}
