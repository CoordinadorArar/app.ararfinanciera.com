<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Procesos;
use App\Models\Terceros;
use App\Models\Petitions;
use App\Models\Admin;
use App\Models\User;
use App\Services\EvaluadorFormula;
use App\Services\CalculadoraCredito;
use App\Services\ValidadorTercero;
use App\Services\FlujoProceso;
use App\Services\Centrales\CentralesRiesgo;
use DateTime;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
// use Symfony\Component\Process\Process;
// use Symfony\Component\HttpFoundation\BinaryFileResponse;
// use Illuminate\Http\Response;
// use Illuminate\Support\Facades\Mail;

use App\Http\Controllers\PHPMailerController;
use Barryvdh\DomPDF\Facade\Pdf;
use SoapClient;
use SoapFault;
// use SoapVar;
use stdClass;
// use SoapHeader;

class ProcesosController extends Controller
{
    /**Comprobar si el tercero ya ha iniciado un proceso, si es asi, mostrar los datos */
    public function validarDocumento(Request $request){
        $documento = ValidadorTercero::baseDocumento($request->input('documento'));
        $idTercero = ValidadorTercero::documentoAlmacenable($documento) ? Terceros::where('DocumentoTercero',$documento)->value('IdTercero') : null;
        if(!$idTercero){
            return response()->json(['existe'=>false,'paso'=>1,'mensaje'=>null,'tercero'=>null,'proceso'=>null,'tratamiento'=>null]);
        }
        return response()->json(array_merge(['existe'=>true],$this->estadoTercero($idTercero)));
    }
    public function estadoRegistro(Request $request){
        $idProceso = ctype_digit((string) $request->input('idProceso')) ? $request->input('idProceso') : null;
        $idTercero = $idProceso ? Procesos::where('IdProceso',$idProceso)->value('IdTercero') : (ctype_digit((string) $request->input('idTercero')) ? Terceros::where('IdTercero',$request->input('idTercero'))->value('IdTercero') : null);
        if(!$idTercero){
            $campo = $request->filled('idProceso') ? 'idProceso' : 'idTercero';
            $mensaje = $request->filled($campo) ? 'El proceso o tercero indicado no existe.' : 'Debes enviar idProceso o idTercero.';
            return response()->json(['message'=>$mensaje,'errors'=>[$campo=>[$mensaje]]],422);
        }
        if($idProceso && ($denegado = $this->accesoProceso($idProceso))){
            return $denegado;
        }
        return response()->json(array_merge(['existe'=>true],$this->estadoTercero($idTercero,$idProceso)));
    }
    private function accesoProceso($idProceso){
        if(!ctype_digit((string) $idProceso) || !Procesos::where('IdProceso',$idProceso)->exists()){
            return response()->json(['message'=>'El proceso no existe.','errors'=>['idProceso'=>['El proceso no existe.']]],422);
        }
        return Procesos::puedeVer($idProceso) ? null : response()->json(['message'=>'No tienes acceso a este proceso.'],403);
    }
    private function accionPermitida($idProceso,$accion){
        $flujo = FlujoProceso::instancia();
        $estado = (int) Procesos::where('IdProceso',$idProceso)->value('EstadoProceso');
        if($flujo->puedeAccion($accion,$estado,FlujoProceso::rolesUsuario())){
            return null;
        }
        if(!in_array($estado,config('procesos.acciones.'.$accion.'.estados'),true)){
            return response()->json(['message'=>'La acción no está disponible: el proceso está en "'.$flujo->nombreEstado($estado).'".'],422);
        }
        return response()->json(['message'=>'Tu rol no permite realizar esta acción en el proceso.'],403);
    }
    private function estadoTercero($idTercero,$idProceso=null){
        $tercero = DB::table('Terceros')->where('IdTercero',$idTercero)->selectRaw("IdTercero,NombresTercero,ApellidosTercero,IdTipoDocumento,DocumentoTercero,CONVERT(varchar(10),FechaExpedicionDocumento,23) AS FechaExpedicionDocumento,LugarExpedicionDocumento,CONVERT(varchar(10),FechaNacimientoTercero,23) AS FechaNacimientoTercero,TelefonoTercero,EmailTercero,DireccionDomicilioTercero,DepartamentoDomicilioTercero,CiudadDomicilioTercero,IdPagaduria,IngresosTercero")->first();
        $proceso = DB::table('Procesos as p')->leftJoin('CuposResultadosCalculo as cu','cu.IdProceso','=','p.IdProceso')->where('p.IdTercero',$idTercero)
            ->when($idProceso,function($consulta) use ($idProceso){ $consulta->where('p.IdProceso',$idProceso); })
            ->orderByDesc('p.IdProceso')->orderByDesc('cu.IdResultado')
            ->selectRaw("p.IdProceso,p.EstadoProceso,p.ValorCreditoSolicitado,p.NumeroCuotas,p.ValorCuota,p.TasaInteres,p.EmailTratamientoEnviadoA,CONVERT(varchar(19),p.FechaEmailTratamiento,120) AS FechaEmailTratamiento,CONVERT(varchar(10),ISNULL(p.updated_at,p.FechaCreacion),103) AS FechaEstado,cu.Configuracion,cu.ValoresOperacion,cu.CupoDisponible")->first();
        if($proceso && !Procesos::puedeVer($proceso->IdProceso)){
            if(in_array((int) $proceso->EstadoProceso,[1,2,3,4],true)){
                return ['paso'=>'finalizado','mensaje'=>'Este cliente tiene un proceso activo con otro asesor.','tercero'=>null,'proceso'=>null,'tratamiento'=>null];
            }
            $proceso = null;
        }
        $tratamiento = $this->tratamientoProceso($proceso);
        $estado = $proceso ? (int) $proceso->EstadoProceso : null;
        $reintento = $estado === 0 && !$idProceso;
        $paso = ($estado === null || $estado === 1 || $reintento) ? 2 : (($estado === 2 && !$tratamiento['completo']) ? 3 : 'finalizado');
        $mensajes = [
            0 => 'El último proceso de esta persona fue rechazado o cancelado; no puede editarse desde el registro.',
            2 => 'El registro de esta persona ya está completo; el proceso está en consulta de centrales de riesgo.',
            3 => 'El proceso de esta persona está en carga y aprobación de documentos de soporte.',
            4 => 'El proceso de esta persona está pendiente de aprobación del crédito.',
            5 => 'El crédito de esta persona ya fue aprobado.'
        ];
        $numero = function($valor){ return is_numeric($valor) ? $valor + 0 : null; };
        return [
            'paso' => $paso,
            'mensaje' => $reintento ? 'El último proceso fue rechazado el '.$proceso->FechaEstado.'; puedes iniciar uno nuevo.' : ($paso === 'finalizado' ? ($mensajes[$estado] ?? 'El proceso está en estado '.$estado.' y no puede editarse desde el registro.') : null),
            'tercero' => $tercero,
            'proceso' => $proceso ? [
                'IdProceso' => (int) $proceso->IdProceso,
                'EstadoProceso' => $estado,
                'IdPagaduria' => $numero($tercero->IdPagaduria),
                'ingresos' => $numero($tercero->IngresosTercero),
                'valorSolicitado' => $numero($proceso->ValorCreditoSolicitado),
                'numeroCuotas' => $numero($proceso->NumeroCuotas),
                'cupo' => $numero($proceso->CupoDisponible),
                'cuota' => $numero($proceso->ValorCuota),
                'tasa' => $numero($proceso->TasaInteres),
                'rubros' => array_diff_key(EvaluadorFormula::valoresOperacion($proceso->Configuracion,$proceso->ValoresOperacion),['ingresos'=>0,'salarioMinimoMensual'=>0])
            ] : null,
            'tratamiento' => $tratamiento
        ];
    }
    private function tratamientoProceso($proceso){
        return array_merge([
            'enviado' => $proceso && $proceso->FechaEmailTratamiento !== null,
            'enviadoA' => $proceso->EmailTratamientoEnviadoA ?? null,
            'fechaEnvio' => $proceso->FechaEmailTratamiento ?? null
        ],$proceso ? Procesos::estadoTratamiento($proceso->IdProceso) : ['documentoCargado'=>false,'aceptado'=>false,'completo'=>false]);
    }
    /**------------------------------------------------------------- */
    /**Reglas para validación de creación de tercero */
    public function reglas($tipo=null,$departamento=null,$fechaNacimiento=null){
        return [
            'idTercero' => 'nullable|bail|integer|exists:Terceros,IdTercero',
            'nombres' => 'required|string|max:40',
            'apellidos' => 'required|string|max:40',
            'tipoDocumento' => 'required|bail|integer|exists:TiposDocumentos,IdTipoDocumento',
            'documentoTercero' => ['required',function($atributo,$valor,$fallar) use ($tipo){
                if($error = ValidadorTercero::documento($tipo,$valor)){
                    $fallar($error);
                }elseif(!ValidadorTercero::documentoAlmacenable($valor)){
                    $fallar('El documento debe ser numérico y no superar 2147483647 (límite actual de la base de datos).');
                }
            }],
            'fechaExpedicion' => ['required',function($atributo,$valor,$fallar) use ($fechaNacimiento){
                if($error = ValidadorTercero::fechaExpedicion($valor,$fechaNacimiento)){
                    $fallar($error);
                }
            }],
            'lugarExpedicion' => 'required|string|max:70',
            'fechaNacimiento' => ['required',function($atributo,$valor,$fallar){
                if($error = ValidadorTercero::fechaNacimiento($valor)){
                    $fallar($error);
                }
            }],
            'telefonoTercero' => ['required',function($atributo,$valor,$fallar){
                if($error = ValidadorTercero::telefono($valor)){
                    $fallar($error);
                }
            }],
            'emailTercero' => 'required|email:rfc,filter|max:100',
            'direccionTercero' => 'required|string|max:70',
            'departamentoResidencia' => 'required|bail|integer|exists:Departamentos,IdDepartamento',
            'ciudadResidencia' => ['required','bail','integer',Rule::exists('Municipios','IdMunicipio')->where('IdDepartamento',(int) $departamento)]
        ];
    }
    /**------------------------------------------------------------- */
    /**Guardar datos personales del usuario */
    public function guardarDatosPersonales(Request $request){
        $idTipo = $request->input('tipoDocumento');
        $tipo = ValidadorTercero::tipoDocumento(ctype_digit((string) $idTipo) ? DB::table('TiposDocumentos')->where('IdTipoDocumento',$idTipo)->value('NombreTipoDocumento') : null);
        $validate = Validator::make($request->all(),$this->reglas($tipo,$request->input('departamentoResidencia'),$request->input('fechaNacimiento')),[
            'ciudadResidencia.exists' => 'La ciudad no pertenece al departamento seleccionado.'
        ],[
            'idTercero' => 'tercero',
            'nombres' => 'nombres',
            'apellidos' => 'apellidos',
            'tipoDocumento' => 'tipo de documento',
            'documentoTercero' => 'número de documento',
            'fechaExpedicion' => 'fecha de expedición',
            'lugarExpedicion' => 'lugar de expedición',
            'fechaNacimiento' => 'fecha de nacimiento',
            'telefonoTercero' => 'teléfono',
            'emailTercero' => 'correo electrónico',
            'direccionTercero' => 'dirección',
            'departamentoResidencia' => 'departamento',
            'ciudadResidencia' => 'ciudad'
        ]);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        $documento = ValidadorTercero::baseDocumento($request->input('documentoTercero'));
        $existente = Terceros::where('DocumentoTercero',$documento)->value('IdTercero');
        $idTercero = $request->input('idTercero') ?: $existente;
        $duplicado = 'Ya existe otro tercero registrado con este documento.';
        if($existente && $existente != $idTercero){
            return response()->json(['message'=>$duplicado,'errors'=>['documentoTercero'=>[$duplicado]]],422);
        }
        if($idTercero && ($estadoActual = $this->estadoTercero($idTercero))['paso'] === 'finalizado'){
            return response()->json(['message'=>$estadoActual['mensaje']],422);
        }
        $datos = [
            'NombresTercero' => trim($request->input('nombres')),
            'ApellidosTercero' => trim($request->input('apellidos')),
            'IdTipoDocumento' => $idTipo,
            'DocumentoTercero' => $documento,
            'FechaExpedicionDocumento' => str_replace('/','-',$request->input('fechaExpedicion')),
            'LugarExpedicionDocumento' => trim($request->input('lugarExpedicion')),
            'FechaNacimientoTercero' => str_replace('/','-',$request->input('fechaNacimiento')),
            'TelefonoTercero' => ValidadorTercero::limpiarTelefono($request->input('telefonoTercero')),
            'EmailTercero' => trim($request->input('emailTercero')),
            'DireccionDomicilioTercero' => trim($request->input('direccionTercero')),
            'DepartamentoDomicilioTercero' => $request->input('departamentoResidencia'),
            'CiudadDomicilioTercero' => $request->input('ciudadResidencia')
        ];
        try{
            if($idTercero){
                Terceros::where('IdTercero',$idTercero)->update($datos);
            }else{
                $idTercero = Terceros::insertGetId($datos);
            }
        }catch(QueryException $e){
            if(in_array($e->errorInfo[1] ?? null,[2601,2627])){
                return response()->json(['message'=>$duplicado,'errors'=>['documentoTercero'=>[$duplicado]]],422);
            }
            throw $e;
        }
        return response()->json(['success'=>'Datos de tercero agregados correctamente','data'=>['id'=>(int) $idTercero]]);
    }
    /**------------------------------------------------------------- */
    /**Mostrar configuracion de la pagaduria para hallar el cupo disponible */
    public function mostrarConfigInputs(Request $request){
        try{
            $dataPagaduria = CalculadoraCredito::formulaPagaduria($request->input('pagaduriaTercero'),$request->input('ingresosMensuales'));
        }catch(InvalidArgumentException $e){
            return response()->json(['message'=>$e->getMessage()],422);
        }
        return response()->json([$dataPagaduria]);
    }
    /**------------------------------------------------------------- */
    /**Guardar datos financieros del usuario (ingresos,egresos) */
    private function calculoFinanciero(Request $request){
        $validate = Validator::make($request->only('ingresos','valorSolicitado'),[
            'ingresos' => 'required|bail|numeric|gt:0|max:2147483647',
            'valorSolicitado' => 'required|bail|numeric|gt:0|max:2147483647'
        ],[],[
            'ingresos' => 'ingresos mensuales',
            'valorSolicitado' => 'valor solicitado'
        ]);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        $nombres = (array) $request->nombres; $valores = (array) $request->valores;
        $valoresFormula = [];
        foreach($nombres as $indice => $nombre){
            if(array_key_exists($indice,$valores)){
                $valoresFormula[trim($nombre)] = $valores[$indice];
            }
        }
        $valoresFormula['salarioMinimoMensual'] = CalculadoraCredito::valorVariable('SalarioMinimoMensual');
        try{
            $pagaduria = CalculadoraCredito::formulaPagaduria($request->input('idPagaduria'),$request->input('ingresos'));
        }catch(InvalidArgumentException $e){
            return response()->json(['message'=>$e->getMessage()],422);
        }
        try{
            $evaluacion = EvaluadorFormula::evaluar($pagaduria->Configuracion,$valoresFormula);
        }catch(InvalidArgumentException $e){
            return response()->json(['message'=>'No fue posible calcular el cupo: '.$e->getMessage()],422);
        }
        $infoTercero = ctype_digit((string) $request->input('idTercero')) ? Terceros::mostrarInfoTercero($request->input('idTercero')) : [];
        if(count($infoTercero) == 0){
            return response()->json(['message'=>'El tercero no existe.'],422);
        }
        if($bloqueo = $this->bloqueoRegistro($infoTercero[0]->IdTercero)){
            return $bloqueo;
        }
        try{
            $calculo = CalculadoraCredito::paraPagaduria($request->input('idPagaduria'))->calcular($request->input('valorSolicitado'),$request->input('numeroCuotas'),$infoTercero[0]->FechaTerceroNacimiento);
        }catch(InvalidArgumentException $e){
            return response()->json(['message'=>$e->getMessage()],422);
        }
        return ['configuracion'=>$pagaduria->Configuracion,'operacion'=>$evaluacion['operacion'],'cupo'=>$evaluacion['resultado'],'calculo'=>$calculo];
    }
    private function bloqueoRegistro($idTercero){
        $estado = $this->estadoTercero($idTercero);
        return $estado['paso'] === 2 ? null : response()->json(['message'=>$estado['mensaje'] ?? 'Los datos financieros de esta persona ya fueron confirmados; continúa con el tratamiento de datos.'],422);
    }
    private function resumenCalculo(array $resultado){
        $calculo = $resultado['calculo'];
        return [
            'sinCupo' => false,
            'cupo' => $resultado['cupo'],
            'cuota' => $calculo['cuota'],
            'seguro' => $calculo['seguro'],
            'cuotaTotal' => $calculo['cuotaTotal'],
            'tasa' => $calculo['tasa'],
            'plazo' => $calculo['plazo'],
            'plazoMaximo' => $calculo['plazoMaximo']
        ];
    }
    public function calcularDatosFinancieros(Request $request){
        $resultado = $this->calculoFinanciero($request);
        if($resultado instanceof JsonResponse){
            return $resultado;
        }
        if($resultado['cupo'] <= 0){
            return response()->json(['sinCupo'=>true,'cupo'=>$resultado['cupo']]);
        }
        if($bloqueo = CalculadoraCredito::bloqueoCupo($resultado['calculo'],$resultado['cupo'])){
            return response()->json($bloqueo,422);
        }
        return response()->json($this->resumenCalculo($resultado));
    }
    public function guardarDatosFinancieros(Request $request){
        $resultado = $this->calculoFinanciero($request);
        if($resultado instanceof JsonResponse){
            return $resultado;
        }
        $sinCupo = $resultado['cupo'] <= 0;
        if(!$sinCupo && ($bloqueo = CalculadoraCredito::bloqueoCupo($resultado['calculo'],$resultado['cupo']))){
            return response()->json($bloqueo,422);
        }
        $calculo = $resultado['calculo'];
        $idTercero = $request->input('idTercero');
        $idProceso = DB::transaction(function() use ($request,$resultado,$calculo,$idTercero,$sinCupo){
            $idAbierto = Procesos::procesoAbierto($idTercero);
            if($bloqueo = $this->bloqueoRegistro($idTercero)){
                return $bloqueo;
            }
            $repetido = $sinCupo ? DB::table('Procesos')->where('IdTercero',$idTercero)->where('EstadoProceso',0)->where('IdUsuario',auth()->id())
                ->where('ValorCreditoSolicitado',$calculo['monto'])->where('NumeroCuotas',$calculo['plazo'])
                ->where('FechaCreacion','>=',date('Y-m-d\TH:i:s',time()-120))->orderByDesc('IdProceso')->value('IdProceso') : null;
            if($repetido){
                return (int) $repetido;
            }
            Terceros::where('IdTercero',$idTercero)->update([
                'IngresosTercero' => $request->input('ingresos'),
                'IdPagaduria' => $request->input('idPagaduria')
            ]);
            $ahora = date('Y-m-d\TH:i:s');
            $datos = [
                'EstadoProceso' => $sinCupo ? 0 : 1,
                'ValorCreditoSolicitado' => $calculo['monto'],
                'NumeroCuotas' => $calculo['plazo'],
                'ValorCuota' => $calculo['cuotaTotal'],
                'TasaInteres' => $calculo['tasa']
            ];
            $idProceso = $idAbierto;
            $motivo = $sinCupo ? config('procesos.motivoSinCupo') : null;
            if($idProceso){
                DB::table('Procesos')->where('IdProceso',$idProceso)->update($datos + ['updated_at'=>$ahora]);
                if($sinCupo){
                    FlujoProceso::registrarHistorial($idProceso,1,0,auth()->id(),$motivo,'Sin cupo disponible');
                }
            }else{
                $idProceso = DB::table('Procesos')->insertGetId($datos + [
                    'IdTercero' => $idTercero,
                    'FechaCreacion' => $ahora,
                    'IdUsuario' => auth()->id(),
                    'updated_at' => $sinCupo ? $ahora : null
                ]);
                FlujoProceso::registrarHistorial($idProceso,null,$datos['EstadoProceso'],auth()->id(),$motivo,$sinCupo ? 'Sin cupo disponible' : null);
            }
            $accion = DB::table('CuposResultadosCalculo')->where('IdProceso',$idProceso)->exists() ? 'actualizar' : 'guardar';
            Procesos::guardarCupoDisponible($idProceso,$resultado['configuracion'],$resultado['operacion'],$resultado['cupo'],$accion);
            return (int) $idProceso;
        });
        if($idProceso instanceof JsonResponse){
            return $idProceso;
        }
        if($sinCupo){
            return response()->json(['res'=>'sinCupo','idProceso'=>$idProceso,'cupo'=>$resultado['cupo']]);
        }
        return response()->json(array_merge(['res'=>'Datos ingresados con exito!','idProceso'=>$idProceso],$this->resumenCalculo($resultado)));
    }
    /**------------------------------------------------------------- */
    /**Verificar edad del tercero, para no permitir periodos tan extensos para adultos muy mayores */
    public function verificarEdadTercero(Request $request){
        $idTercero = $request->get('idTercero');
        $tercero = Terceros::mostrarInfoTercero($idTercero);
        if(count($tercero) == 0){
            return response()->json(['message'=>'El tercero no existe.'],422);
        }
        $fechaNacimiento = $tercero[0]->FechaTerceroNacimiento;
        $date = date('Y-m-d');
        $hoy = new DateTime($date);
        $fechaEdad = new DateTime($fechaNacimiento);
        $diferencia = $hoy->diff($fechaEdad);
        $idPagaduria = $request->input('idPagaduria') ?: $tercero[0]->IdPagaduria;
        $plazos = ['edad'=>$diferencia->y,'plazoMaximo'=>null,'porcentajeSeguro'=>null,'plazos'=>[],'message'=>null];
        if($idPagaduria){
            try{
                $plazos = array_merge($plazos,CalculadoraCredito::paraPagaduria($idPagaduria)->plazos($fechaNacimiento));
            }catch(InvalidArgumentException $e){
                $plazos['message'] = $e->getMessage();
            }
        }else{
            $plazos['message'] = 'Selecciona una pagaduría para conocer los plazos permitidos.';
        }
        return response()->json(array_merge(get_object_vars($diferencia),$plazos));
    }
    /**------------------------------------------------------------- */
    /**cambiar estado del proceso */
    public function editarEstadoProceso(Request $request){
        if($request->input('estado') !== 'pendiente'){
            $request->merge(['estadoNuevo'=>$request->input('estado')]);
            return $this->cambiarEstadoProceso($request);
        }
        $idProceso = $request->input('idProceso');
        if($denegado = $this->accesoProceso($idProceso)){
            return $denegado;
        }
        $cupo = Procesos::cupoProceso($idProceso);
        $sinCupo = $cupo === null || $cupo <= 0;
        if($sinCupo && (int) Procesos::where('IdProceso',$idProceso)->value('EstadoProceso') === 0){
            return response()->json(['res'=>'canceled']);
        }
        $resultado = FlujoProceso::instancia()->cambiar($idProceso,$sinCupo ? 0 : 2,FlujoProceso::rolesUsuario(),$sinCupo ? config('procesos.motivoSinCupo') : null,$sinCupo ? 'Sin cupo disponible' : null);
        if($resultado[0] !== 200){
            return response()->json(['message'=>$resultado[1]],$resultado[0]);
        }
        return response()->json(['res'=>$sinCupo ? 'canceled' : 'edited']);
    }
    public function cambiarEstadoProceso(Request $request){
        $validate = Validator::make($request->all(),[
            'idProceso' => 'required|integer',
            'estadoNuevo' => 'required|integer|between:0,5',
            'idMotivo' => 'nullable|integer',
            'observacion' => 'nullable|string|max:500',
            'texto' => 'nullable|string|max:2000'
        ],[],[
            'texto' => 'texto del correo',
            'idProceso' => 'proceso',
            'estadoNuevo' => 'estado nuevo',
            'idMotivo' => 'motivo',
            'observacion' => 'observación'
        ]);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        $idProceso = $request->input('idProceso');
        if($denegado = $this->accesoProceso($idProceso)){
            return $denegado;
        }
        $flujo = FlujoProceso::instancia();
        $roles = FlujoProceso::rolesUsuario();
        $resultado = $flujo->cambiar($idProceso,(int) $request->input('estadoNuevo'),$roles,$request->input('idMotivo'),$request->input('observacion'));
        if($resultado[0] !== 200){
            return response()->json(['message'=>$resultado[1]],$resultado[0]);
        }
        $respuesta = ['res'=>'ok','proceso'=>$this->resumenProceso($idProceso,$flujo,$roles)];
        if($resultado[2] === 4){
            try{
                $correo = $this->correoDecision($idProceso,$request->input('texto'));
            }catch(\Throwable $e){
                report($e);
                $correo = 'ocurrió un error al preparar o enviar el correo.';
            }
            $respuesta['correo'] = $correo === true;
            if($correo !== true){
                $respuesta['message'] = 'El proceso quedó '.mb_strtolower($flujo->nombreEstado($request->input('estadoNuevo'))).', pero no se pudo notificar al asesor: '.$correo.' Puedes reenviar el correo.';
            }
        }
        return response()->json($respuesta);
    }
    private function resumenProceso($idProceso,$flujo,$roles){
        $proceso = DB::table('Procesos')->where('IdProceso',$idProceso)->selectRaw('IdProceso,EstadoProceso,CONVERT(varchar(16),ISNULL(updated_at,FechaCreacion),120) AS FechaActualizacion')->first();
        return [
            'IdProceso' => (int) $proceso->IdProceso,
            'estado' => (int) $proceso->EstadoProceso,
            'estadoNombre' => $flujo->nombreEstado($proceso->EstadoProceso),
            'fechaActualizacion' => $proceso->FechaActualizacion,
            'acciones' => $flujo->accionesDisponibles($proceso->EstadoProceso,$roles),
            'accionPrincipal' => $flujo->accionPrincipal($proceso->EstadoProceso,$roles)
        ];
    }
    private function correoDecision($idProceso,$texto=null){
        $proceso = DB::table('Procesos as p')->join('Terceros as t','t.IdTercero','=','p.IdTercero')->leftJoin('Pagadurias as pa','pa.IdPagaduria','=','t.IdPagaduria')
            ->where('p.IdProceso',$idProceso)->select('p.IdProceso','p.IdUsuario','p.EstadoProceso','p.ValorCreditoSolicitado','p.NumeroCuotas','p.ValorCuota','p.TasaInteres','t.NombresTercero','t.ApellidosTercero','t.DocumentoTercero','pa.NombrePagaduria')->first();
        $destinatario = trim((string) (Admin::perfilUsuario($proceso->IdUsuario)[0]->email ?? ''));
        if($destinatario === ''){
            return 'el asesor del proceso no tiene correo registrado.';
        }
        $decision = DB::table('ProcesosHistorial as h')->leftJoin('MotivosRechazo as m','m.IdMotivo','=','h.IdMotivo')->where('h.IdProceso',$idProceso)->where('h.EstadoNuevo',$proceso->EstadoProceso)
            ->orderByDesc('h.IdHistorial')->select('h.Observacion','m.NombreMotivo')->first();
        $aprobado = (int) $proceso->EstadoProceso === 5;
        $nombre = trim($proceso->NombresTercero.' '.$proceso->ApellidosTercero);
        $cuerpo = view('procesos.email-cuerpo',[
            'aprobado' => $aprobado,
            'proceso' => $proceso,
            'nombre' => $nombre,
            'motivo' => $decision->NombreMotivo ?? null,
            'texto' => $texto !== null && trim($texto) !== '' ? $texto : ($decision->Observacion ?? null)
        ])->render();
        $asunto = ($aprobado ? 'Crédito aprobado' : 'Crédito rechazado').' - '.$nombre.' ('.$proceso->DocumentoTercero.')';
        return PHPMailerController::crearEmail(['asunto'=>$asunto,'destinatario'=>$destinatario,'cuerpo'=>$cuerpo,'copiaA'=>'','emailBcc'=>'']) ? true : 'falló el envío a '.$destinatario.'.';
    }
    public function historialProceso(Request $request){
        if($denegado = $this->accesoProceso($request->input('idProceso'))){
            return $denegado;
        }
        return response()->json(['historial'=>Procesos::historial($request->input('idProceso'))]);
    }
    public function motivosRechazo(){
        return response()->json(['motivos'=>DB::table('MotivosRechazo')->where('EstadoMotivo',1)->orderBy('IdMotivo')->get(['IdMotivo','NombreMotivo'])->map(function($motivo){
            return ['IdMotivo'=>(int) $motivo->IdMotivo,'NombreMotivo'=>$motivo->NombreMotivo];
        })]);
    }
    public function detalleProceso(Request $request){
        $idProceso = $request->input('idProceso');
        if($denegado = $this->accesoProceso($idProceso)){
            return $denegado;
        }
        $flujo = FlujoProceso::instancia();
        $roles = FlujoProceso::rolesUsuario();
        $fila = DB::table('Procesos as p')->join('Terceros as t','t.IdTercero','=','p.IdTercero')->leftJoin('Pagadurias as pa','pa.IdPagaduria','=','t.IdPagaduria')
            ->leftJoin('TiposDocumentos as td','td.IdTipoDocumento','=','t.IdTipoDocumento')->leftJoin('Municipios as m','m.IdMunicipio','=','t.CiudadDomicilioTercero')
            ->leftJoin('Departamentos as d','d.IdDepartamento','=','t.DepartamentoDomicilioTercero')->where('p.IdProceso',$idProceso)
            ->selectRaw("p.IdProceso,p.EstadoProceso,p.IdUsuario,p.ValorCreditoSolicitado,p.NumeroCuotas,p.ValorCuota,p.TasaInteres,p.EmailTratamientoEnviadoA,CONVERT(varchar(19),p.FechaEmailTratamiento,120) AS FechaEmailTratamiento,
                CONVERT(varchar(10),p.FechaCreacion,23) AS FechaCreacion,CONVERT(varchar(16),ISNULL(p.updated_at,p.FechaCreacion),120) AS FechaActualizacion,
                t.IdTercero,t.NombresTercero,t.ApellidosTercero,td.NombreTipoDocumento,t.DocumentoTercero,CONVERT(varchar(10),t.FechaExpedicionDocumento,23) AS FechaExpedicion,t.LugarExpedicionDocumento,
                CONVERT(varchar(10),t.FechaNacimientoTercero,23) AS FechaNacimiento,t.TelefonoTercero,t.EmailTercero,t.DireccionDomicilioTercero,d.NombreDepartamento,m.NombreMunicipio,
                t.IdPagaduria,pa.NombrePagaduria,t.IngresosTercero")->first();
        $numero = function($valor){ return is_numeric($valor) ? $valor + 0 : null; };
        $cupo = $numero(Procesos::cupoProceso($idProceso));
        try{
            $reglas = DB::table('PagaduriasReglasEdad')->where('IdPagaduria',$fila->IdPagaduria)->orderBy('EdadMin')->get()->all();
            $calculo = (new CalculadoraCredito($reglas,is_numeric($fila->TasaInteres) ? $fila->TasaInteres : CalculadoraCredito::valorVariable('TasaInteres')))->calcular($fila->ValorCreditoSolicitado,$fila->NumeroCuotas,$fila->FechaNacimiento);
        }catch(InvalidArgumentException $e){
            $calculo = null;
        }
        $montoMaximo = $calculo && $cupo !== null ? CalculadoraCredito::montoMaximo($cupo,$calculo['factor'],$calculo['porcentajeSeguro']) : null;
        $asesor = Procesos::nombresUsuarios([$fila->IdUsuario]);
        return response()->json([
            'proceso' => [
                'IdProceso' => (int) $fila->IdProceso,
                'estado' => (int) $fila->EstadoProceso,
                'estadoNombre' => $flujo->nombreEstado($fila->EstadoProceso),
                'fechaCreacion' => $fila->FechaCreacion,
                'fechaActualizacion' => $fila->FechaActualizacion,
                'idAsesor' => $fila->IdUsuario ? (int) $fila->IdUsuario : null,
                'asesor' => $asesor[$fila->IdUsuario] ?? null
            ],
            'tercero' => [
                'IdTercero' => (int) $fila->IdTercero,
                'nombres' => $fila->NombresTercero,
                'apellidos' => $fila->ApellidosTercero,
                'tipoDocumento' => $fila->NombreTipoDocumento,
                'documento' => (string) $fila->DocumentoTercero,
                'fechaExpedicion' => $fila->FechaExpedicion,
                'lugarExpedicion' => $fila->LugarExpedicionDocumento,
                'fechaNacimiento' => $fila->FechaNacimiento,
                'telefono' => $fila->TelefonoTercero,
                'email' => $fila->EmailTercero,
                'direccion' => $fila->DireccionDomicilioTercero,
                'departamento' => $fila->NombreDepartamento,
                'ciudad' => $fila->NombreMunicipio,
                'IdPagaduria' => $numero($fila->IdPagaduria),
                'pagaduria' => $fila->NombrePagaduria !== null ? trim($fila->NombrePagaduria) : null,
                'ingresos' => $numero($fila->IngresosTercero)
            ],
            'financiero' => [
                'cupo' => $cupo,
                'monto' => $numero($fila->ValorCreditoSolicitado),
                'cuota' => $calculo['cuota'] ?? null,
                'seguro' => $calculo['seguro'] ?? null,
                'porcentajeSeguro' => $calculo['porcentajeSeguro'] ?? null,
                'cuotaTotal' => $numero($fila->ValorCuota),
                'plazo' => $numero($fila->NumeroCuotas),
                'tasa' => $numero($fila->TasaInteres),
                'montoMaximo' => $montoMaximo
            ],
            'documentos' => Procesos::documentosProceso($idProceso),
            'tratamiento' => $this->tratamientoProceso($fila),
            'acciones' => $flujo->accionesDisponibles($fila->EstadoProceso,$roles),
            'accionPrincipal' => $flujo->accionPrincipal($fila->EstadoProceso,$roles),
            'historial' => Procesos::historial($idProceso)
        ]);
    }
    /**------------------------------------------------------------- */
    private function accesoCentrales($idProceso,$consultar=false){
        if($denegado = $this->accesoProceso($idProceso)){
            return $denegado;
        }
        $denegado = $this->accionPermitida($idProceso,'centrales');
        return ($denegado && ($consultar || $this->accionPermitida($idProceso,'aprobarCredito'))) ? $denegado : null;
    }
    private function terceroCentrales($idProceso){
        $fila = DB::table('Procesos as p')->join('Terceros as t','t.IdTercero','=','p.IdTercero')->where('p.IdProceso',$idProceso)
            ->selectRaw('t.IdTercero,t.DocumentoTercero,t.IdTipoDocumento,t.NombresTercero,t.ApellidosTercero,CONVERT(varchar(10),t.FechaExpedicionDocumento,23) AS FechaExpedicion,p.EstadoProceso')->first();
        return [
            'idTercero' => (int) $fila->IdTercero,
            'estado' => (int) $fila->EstadoProceso,
            'documento' => trim((string) $fila->DocumentoTercero),
            'tipoDocumento' => (int) $fila->IdTipoDocumento,
            'primerApellido' => mb_strtoupper(explode(' ',trim((string) $fila->ApellidosTercero))[0]),
            'nombre' => trim($fila->NombresTercero.' '.$fila->ApellidosTercero),
            'fechaExpedicion' => $fila->FechaExpedicion
        ];
    }
    private function consultaCentral(array $consulta,array $usuarios){
        return array_merge($consulta['resumen'] ?? [],[
            'idConsulta' => $consulta['idConsulta'],
            'fechaConsulta' => $consulta['fechaConsulta'],
            'vigenteHasta' => $consulta['vigenteHasta'],
            'vigente' => CentralesRiesgo::reutilizable($consulta,date('Y-m-d H:i:s')),
            'usuario' => $usuarios[$consulta['idUsuario']] ?? null,
            'ambiente' => $consulta['ambiente'],
            'simulado' => $consulta['simulado']
        ]);
    }
    public function centralesEstado(Request $request){
        $idProceso = $request->input('idProceso');
        if($denegado = $this->accesoCentrales($idProceso)){
            return $denegado;
        }
        $centrales = CentralesRiesgo::instancia();
        $tercero = $this->terceroCentrales($idProceso);
        $disponibles = $centrales->disponibles();
        $ultimas = $centrales->ultimas($tercero['idTercero'],$disponibles);
        $ahora = date('Y-m-d H:i:s');
        $flujo = FlujoProceso::instancia();
        $roles = FlujoProceso::rolesUsuario();
        $predeterminado = $centrales->predeterminadoDisponible() ? $centrales->predeterminado() : null;
        return response()->json([
            'tratamientoCompleto' => Procesos::estadoTratamiento($idProceso)['completo'],
            'puedeConsultar' => $flujo->puedeAccion('centrales',$tercero['estado'],$roles),
            'puedeForzar' => $flujo->puedeAccion('forzarConsultaCentrales',$tercero['estado'],$roles),
            'predeterminado' => $predeterminado,
            'vigenciaDias' => $centrales->vigenciaDias(),
            'proveedores' => array_map(function($clave) use ($centrales,$ultimas,$ahora,$predeterminado){
                return [
                    'clave' => $clave,
                    'nombre' => $centrales->nombre($clave),
                    'predeterminado' => $clave === $predeterminado,
                    'ambiente' => $centrales->ambiente($clave),
                    'simulado' => $clave === 'simulado',
                    'pendienteValidar' => $centrales->pendienteValidar($clave),
                    'vigente' => CentralesRiesgo::reutilizable($ultimas[$clave] ?? null,$ahora) ? ['fechaConsulta'=>$ultimas[$clave]['fechaConsulta'],'vigenteHasta'=>$ultimas[$clave]['vigenteHasta']] : null
                ];
            },$disponibles)
        ]);
    }
    public function centralesConsultar(Request $request){
        $centrales = CentralesRiesgo::instancia();
        $validate = Validator::make($request->all(),[
            'idProceso' => 'required|integer',
            'proveedores' => 'required|array|min:1|max:2',
            'proveedores.*' => ['required','distinct',Rule::in($centrales->claves())],
            'forzar' => 'nullable|boolean'
        ],[],['idProceso'=>'proceso','proveedores'=>'proveedores','proveedores.*'=>'proveedor','forzar'=>'forzar']);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        $idProceso = $request->input('idProceso');
        if($denegado = $this->accesoCentrales($idProceso,true)){
            return $denegado;
        }
        if(!Procesos::estadoTratamiento($idProceso)['completo']){
            $mensaje = 'El tratamiento de datos del proceso no está completo; no se puede consultar centrales de riesgo.';
            return response()->json(['message'=>$mensaje,'errors'=>['tratamiento'=>[$mensaje]]],422);
        }
        $tercero = $this->terceroCentrales($idProceso);
        $forzar = $request->boolean('forzar');
        if($forzar && !FlujoProceso::instancia()->puedeAccion('forzarConsultaCentrales',$tercero['estado'],FlujoProceso::rolesUsuario())){
            return response()->json(['message'=>'Tu rol no permite forzar una nueva consulta a centrales de riesgo.'],403);
        }
        $proveedores = $request->input('proveedores');
        $ahora = date('Y-m-d H:i:s');
        $ultimas = $centrales->ultimas($tercero['idTercero'],array_values(array_intersect($proveedores,$centrales->disponibles())));
        $resultados = [];
        $eventos = [];
        foreach(CentralesRiesgo::plan($proveedores,$ultimas,$ahora,$forzar) as $clave => $plan){
            if($plan === 'reutilizar'){
                $resultados[$clave] = ['ok'=>true,'reutilizada'=>true,'simulado'=>$ultimas[$clave]['simulado'],'consulta'=>$ultimas[$clave]];
                $eventos[] = $centrales->nombre($clave).' (reutilizada)';
                continue;
            }
            $ejecucion = $centrales->ejecutar($clave,$tercero);
            $consulta = $centrales->guardar($idProceso,$tercero['idTercero'],$clave,auth()->id(),$ahora,$ejecucion);
            $resultados[$clave] = $consulta ? ['ok'=>true,'reutilizada'=>false,'simulado'=>$consulta['simulado'],'consulta'=>$consulta] : ['ok'=>false,'error'=>$ejecucion['error'],'codigo'=>$ejecucion['codigo']];
            $eventos[] = $centrales->nombre($clave).($consulta ? ($forzar ? ' (nueva, forzada)' : ' (nueva)') : ' (error: '.$ejecucion['codigo'].')');
        }
        FlujoProceso::registrarHistorial($idProceso,$tercero['estado'],$tercero['estado'],auth()->id(),null,mb_substr('Consulta de centrales: '.implode('; ',$eventos),0,500));
        $usuarios = Procesos::nombresUsuarios(array_filter(array_map(function($resultado){ return $resultado['consulta']['idUsuario'] ?? null; },$resultados)));
        foreach($resultados as $clave => $resultado){
            if($resultado['ok']){
                $resultados[$clave]['consulta'] = $this->consultaCentral($resultado['consulta'],$usuarios);
            }
        }
        return response()->json(['resultados'=>$resultados]);
    }
    public function centralesResultados(Request $request){
        $idProceso = $request->input('idProceso');
        if($denegado = $this->accesoCentrales($idProceso)){
            return $denegado;
        }
        $centrales = CentralesRiesgo::instancia();
        $ultimas = $centrales->ultimas($this->terceroCentrales($idProceso)['idTercero'],array_keys(config('centrales.proveedores')),false);
        $usuarios = Procesos::nombresUsuarios(array_column($ultimas,'idUsuario'));
        $disponibles = $centrales->disponibles();
        return response()->json(['resultados'=>$ultimas ? array_map(function($consulta) use ($usuarios,$disponibles,$centrales){ $disponible = in_array($consulta['proveedor'],$disponibles,true); return array_merge($this->consultaCentral($consulta,$usuarios),['vigente'=>$disponible && $consulta['ambiente'] === $centrales->ambiente($consulta['proveedor']) && CentralesRiesgo::reutilizable($consulta,date('Y-m-d H:i:s')),'disponible'=>$disponible]); },$ultimas) : new stdClass]);
    }
    /**------------------------------------------------------------- */
    //subir documento pdf de tratamiento de datos
    public function subirArchivoTratamientoDatos(Request $request){
        $rule = [
            'fileTratamientoDatos' => [
                'required',
                'file',
                'mimes:pdf',                    // Solo archivos PDF
                'max:2048',                     // Máximo 2MB (2048 KB)
                'mimetypes:application/pdf'     // Validación adicional de MIME type
            ]
        ];

        // Mensajes personalizados para las validaciones
        $messages = [
            'fileTratamientoDatos.required' => 'Debe seleccionar un archivo.',
            'fileTratamientoDatos.file' => 'El archivo seleccionado no es válido.',
            'fileTratamientoDatos.mimes' => 'Solo se permiten archivos en formato PDF.',
            'fileTratamientoDatos.max' => 'El archivo no debe superar los 2MB.',
            'fileTratamientoDatos.mimetypes' => 'El archivo debe ser un PDF válido.'
        ];

        $validate = Validator::make($request->all(), $rule, $messages);

        if($validate->fails()){
            return response()->json(['errors'=>$validate->errors()]);
        }

        if($denegado = $this->accesoProceso($request->input('idProceso'))){
            return $denegado;
        }
        if($request->hasFile('fileTratamientoDatos')){
            $tercero = Terceros::mostrarInfoTercero($request->input('idTercero'));
            $documentoTercero = $tercero[0]->DocumentoTercero;
            $fecha = date('Y-m-d');
            $nombreArchivo = $documentoTercero.'-'.$fecha.'-autorizacion.pdf';
            $tamañoArchivo = $request->file('fileTratamientoDatos')->getSize();
            $carpetaArchivo = 'app/public/tratamiento_datos/';

            if(Storage::disk('sftp')->putFileAs($carpetaArchivo,$request->file('fileTratamientoDatos'),$nombreArchivo)){
                $rutaArchivo = rtrim(config('filesystems.disks.sftp.root'),'/').'/'.$carpetaArchivo.$nombreArchivo;
                $guardarTratamiento = Terceros::guardarFormatoTratamientoDatos($request->input('idTercero'),$request->input('idProceso'),$nombreArchivo,$tamañoArchivo,1,$rutaArchivo);
                Procesos::guardarDocumento($request->input('idProceso'),2,$carpetaArchivo.$nombreArchivo,$nombreArchivo);
                return response()->json(['success'=>'Archivo cargado con exito!']);
            }
            
            return response()->json(['errors'=>'Surgieron errores al intentar subir el archivo']);
        }
        return response()->json(['errors'=>'No se ha seleccionado un archivo válido']);
    }
    /**------------------------------------------------------------- */
    /**Envio de correo electronico al tercero para su aceptacion de tratamiento de datos */
    public function enviarEmailAprobacionDatos(Request $request){
        $idProceso = $request->input('idProceso');
        if($denegado = $this->accesoProceso($idProceso)){
            return $denegado;
        }
        $proceso = ctype_digit((string) $idProceso) && Procesos::where('IdProceso',$idProceso)->exists() ? Procesos::mostrarInfoProcesos($idProceso,'approveCredit') : [];
        if(empty($proceso['proceso'])){
            return response()->json(['message'=>'El proceso no existe o no tiene los datos completos para enviar el correo.','errors'=>['idProceso'=>['El proceso no existe o no tiene los datos completos para enviar el correo.']]],422);
        }
        $documentoTercero = $proceso['proceso'][0]->DocumentoTercero;
        $asunto = 'Tratamiento de Datos Arar Financiera';
        $to = trim((string) $proceso['proceso'][0]->EmailTercero);
        if($to === ''){
            return response()->json(['message'=>'El tercero no tiene un correo electrónico registrado.'],422);
        }
        $cuerpo = '<style>a{border-radius:5px;background-color:rgb(65,110,195);border-color:rgb(65,110,195);color:rgb(255,255,255);padding:10px;font-size:14px;}</style>';
        $cuerpo .= '<p>Buen d&iacutea,</p><br>';
        $cuerpo .= '<p>Con el siguiente bot&oacuten ser&aacutes redirigido al formulario de aceptaci&oacuten de tratamiento de tus datos personales</p><br>';
        $enlace = preg_replace('#^http://#i','https://',rtrim(config('app.url'),'/')).URL::temporarySignedRoute('aceptar-tratamiento-datos',now()->addDays(7),['idProceso'=>$idProceso,'documento'=>$documentoTercero],false);
        $cuerpo .= '<a href="'.e($enlace).'">
                        Formulario de tratamiento de datos
                    </a>';
        $data = ['asunto'=>$asunto,'destinatario'=>$to,'cuerpo'=>$cuerpo,'copiaA'=>'','emailBcc'=>''];
        if(!PHPMailerController::crearEmail($data)){
            return response()->json(['message'=>'No fue posible enviar el correo a '.$to.'. Intenta de nuevo o carga el formato firmado.'],422);
        }
        $fechaEnvio = date('Y-m-d H:i:s');
        DB::table('Procesos')->where('IdProceso',$idProceso)->update([
            'EmailTratamientoEnviadoA' => $to,
            'FechaEmailTratamiento' => str_replace(' ','T',$fechaEnvio)
        ]);
        return response()->json(['res'=>'ok','enviadoA'=>$to,'fechaEnvio'=>$fechaEnvio]);
    }
    public function formatoTratamientoDatos($idProceso=null){
        if($idProceso && ($denegado = $this->accesoProceso($idProceso))){
            return $denegado;
        }
        $proceso = $idProceso ? $this->procesoTratamiento($idProceso) : ['proceso'=>[(object) array_fill_keys(['NombresTercero','ApellidosTercero','DocumentoTercero','DireccionDomicilioTercero','EmailTercero','TelefonoTercero'],'')]];
        return Pdf::loadView('procesos.generar-formato-autorizacion',$proceso)->stream('formato_tratamiento_datos.pdf');
    }
    /**-------------------------------------------------------------- */
    /**Vista de formulario de aceptación de tratamiento de datos*/
    public function aceptarTratamientoDatos($idProceso,$documento){
        $proceso = $this->procesoTratamiento($idProceso);
        if((string)$proceso['proceso'][0]->DocumentoTercero !== (string)$documento){
            abort(404);
        }
        $urlsFormato = [];
        foreach(['111','110','101','100'] as $permisos){
            $urlsFormato[$permisos] = url(URL::temporarySignedRoute('generar-formato-autorizacion',now()->addDays(7),['idProceso'=>$idProceso,'permisos'=>$permisos],false));
        }
        return view('perfilamiento.registro-datos.form-aceptar-tratamiento',compact('proceso','urlsFormato'));
    }
    /**-------------------------------------------------------------- */
    /**Generar pdf aceptación de tratamiento de datos digital */
    public function generarFormatoAutorizacion(/*Request $request*/$idProceso,$permisos){
        $proceso = $this->procesoTratamiento($idProceso);
        $permisos = json_encode([
            'checkData'=>$permisos[0],
            'checkTelefonoContact'=>$permisos[1],
            'checkEmailContact'=>$permisos[2]
        ]);
        $insertar = Terceros::insertarTratamientoDatosAceptados($proceso['proceso'][0]->IdTercero,$permisos,$idProceso);
        if($insertar){
            view()->share('proceso',$proceso);
            $pdf = Pdf::loadView('procesos.generar-formato-autorizacion',$proceso);
            return $pdf->download('archivo.pdf');
        }else{
            return response()->json($insertar);
        }
    }
    private function procesoTratamiento($idProceso){
        if(!Procesos::where('IdProceso',$idProceso)->exists()){
            abort(404);
        }
        $proceso = Procesos::mostrarInfoProcesos($idProceso,'approveCredit');
        if(empty($proceso['proceso'])){
            abort(404);
        }
        return $proceso;
    }
    /**------------------------------------------------------------- */
    /**Mostrar procesos segun estado seleccionado por el filtro */
    public function listaProcesos(Request $request){
        $estado = $request->input('estado',$request->input('filtro'));
        $datos = ['estado'=>$estado,'busqueda'=>$request->input('busqueda'),'page'=>$request->input('page',1),'perPage'=>$request->input('perPage',20),'orden'=>$request->input('orden','fecha'),'direccion'=>$request->input('direccion','desc')];
        $validate = Validator::make($datos,[
            'estado' => 'nullable|integer|between:0,5',
            'busqueda' => 'nullable|string|max:100',
            'page' => 'integer|min:1',
            'perPage' => 'integer|between:1,100',
            'orden' => 'in:cliente,monto,estado,fecha',
            'direccion' => 'in:asc,desc'
        ],[],[
            'orden' => 'orden',
            'direccion' => 'dirección',
            'estado' => 'estado',
            'busqueda' => 'búsqueda',
            'page' => 'página',
            'perPage' => 'registros por página'
        ]);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        return response()->json(Procesos::listaProcesos(auth()->id(),$datos));
    }
    /**------------------------------------------------------------- */
    /**Validar edad del cliente para saber si es o no mayor y mostrar periodo de tiempo de credito segun respuesta */
    public function validarMeses(Request $request){
        $fecha = $request->input('edadFecha') ?: $request->input('fechaEdad');
        $validate = Validator::make(['edadFecha'=>$fecha,'idPagaduria'=>$request->input('idPagaduria')],[
            'edadFecha' => 'required|date',
            'idPagaduria' => 'required|integer|exists:Pagadurias,IdPagaduria'
        ]);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        $html = '<option value="">- Selecciona periodo de crédito -</option>';
        try{
            $edad = CalculadoraCredito::edad($fecha);
            if($edad < 18){
                return response()->json(['html'=>$html,'estado'=>'menor','edad'=>$edad,'plazoMaximo'=>null,'porcentajeSeguro'=>null,'plazos'=>[]]);
            }
            $plazos = CalculadoraCredito::paraPagaduria($request->input('idPagaduria'))->plazos($fecha);
        }catch(InvalidArgumentException $e){
            return response()->json(['message'=>$e->getMessage()],422);
        }
        foreach($plazos['plazos'] as $plazo){
            $html .= '<option value="'.$plazo.'">'.$plazo.'</option>';
        }
        return response()->json(array_merge(['html'=>$html,'estado'=>'mayor'],$plazos));
    }
    /**------------------------------------------------------------- */
    /**Calcular credito y mostrar información en tabla de simulacion */
    public function simulacionCredito(Request $request){
        $validate = Validator::make($request->all(),[
            'idPagaduria' => 'required|integer|exists:Pagadurias,IdPagaduria',
            'fechaEdad' => 'required|date',
            'periodoCredito' => 'required',
            'valorCredito' => 'required'
        ]);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        try{
            $calculo = CalculadoraCredito::paraPagaduria($request->input('idPagaduria'))->calcular($request->input('valorCredito'),$request->input('periodoCredito'),$request->input('fechaEdad'));
        }catch(InvalidArgumentException $e){
            return response()->json(['message'=>$e->getMessage()],422);
        }
        $tabla = CalculadoraCredito::amortizacion($calculo);
        $html = $request->input('info') ? $this->htmlInfoCredito($calculo) : $this->htmlAmortizacion($calculo,$tabla);
        return response()->json(['html'=>$html,'datos'=>$calculo,'tabla'=>$tabla]);
    }
    /**------------------------------------------------------------- */
    private function htmlInfoCredito($calculo){
        return '<tr>
                    <td><strong>Valor Credito : $ '.number_format($calculo['monto'],0,',','.').'</strong></td>
                </tr>
                <tr>
                    <td><strong>Valor Cuota Mensual : $ '.number_format($calculo['cuotaTotal'],0,',','.').'</strong></td>
                </tr>
                <tr>
                    <td><strong>Numero Cuota : '.$calculo['plazo'].'</strong></td>
                </tr>
                <tr>
                    <td><strong>Tasa Mensual : '.$calculo['tasa'].' % </strong></td>
                </tr>
                <tr style="border-top: 0.5px solid;">
                    <td><h2 style="font-size: 18px;font-weight: 700;">Tabla Simulación de crédito</h2></td>
                </tr>';
    }
    private function htmlAmortizacion($calculo,$tabla){
        $html = '<tr><td>0</td><td></td><td></td><td></td><td></td><td></td><td>'.number_format($calculo['monto'],0,',','.').'</td></tr>';
        foreach($tabla as $fila){
            $html .= '<tr><td>'.$fila['numero'].'</td>';
            foreach(['cuota','capital','interes','seguro','cuotaTotal','saldo'] as $campo){
                $html .= '<td>'.number_format($fila[$campo],0,',','.').'</td>';
            }
            $html .= '</tr>';
        }
        return $html;
    }
    /**------------------------------------------------------------- */
    /**Mostrar datos del proceso y otras tablas segun peticion de la vista */
    public function mostrarInfoProcesos(Request $request){
        $idProceso = $request->input('idProceso'); $accion = $request->input('accion');
        if($denegado = $this->accesoProceso($idProceso)){
            return $denegado;
        }
        if(!in_array($accion,['uploadDocs','checkDocs','approveCredit'],true)){
            return response()->json(['message'=>'La acción solicitada no es válida.'],422);
        }
        $proceso = Procesos::mostrarInfoProcesos($idProceso,$accion);
        return response()->json($proceso);
    }
    /**------------------------------------------------------------- */
    /**Verificar todos los procesos que ya han cargado todos los documentos, para pasarlos al siguiente estado, espera de aprobación */
    // public function verifyDocumentsAllProccess(){
    //     $consultarProcesos = Procesos::verifyDocumentsAllProccess();

    // }
    /**------------------------------------------------------------- */
    /**Verificar los documentos subidos y comprobar si ya han sido subidos todos para cambiar el estado del proceso a estado 4=En espera de aprobacion de documentos */
    public function verificarDocumentos(Request $request){
        if($denegado = $this->accesoProceso($request->input('idProceso'))){
            return $denegado;
        }
        foreach(Procesos::documentosProceso($request->input('idProceso')) as $documento){
            if($documento['requerido'] && !in_array($documento['estado'],['cargado','aprobado'],true)){
                return response()->json('faltan');
            }
        }
        return response()->json('subidos');
    }
    /**------------------------------------------------------------- */
    /**Subir documentos adjuntos de soporte de credito, creando una carpeta por usuario */
    public function subirDocumentosSoporte(Request $request){
        $idProceso = $request->input('idProceso');
        $idDocumento = $request->input('idDocumento');
        $archivo = $request->file('archivo') ?: $request->file((string) $request->input('nombre'));
        $validate = Validator::make(['archivo'=>$archivo,'idProceso'=>$idProceso,'idDocumento'=>$idDocumento],[
            'archivo' => 'required|file|mimes:pdf|mimetypes:application/pdf|max:10240',
            'idProceso' => 'required|integer',
            'idDocumento' => 'required|integer'
        ],[
            'archivo.required' => 'Debe seleccionar un archivo.',
            'archivo.file' => 'El archivo seleccionado no es válido.',
            'archivo.mimes' => 'Solo se permiten archivos en formato PDF.',
            'archivo.mimetypes' => 'El archivo debe ser un PDF válido.',
            'archivo.max' => 'El archivo no debe superar los 10MB.'
        ]);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        if(($denegado = $this->accesoProceso($idProceso)) || ($denegado = $this->accionPermitida($idProceso,'cargarDocumentos'))){
            return $denegado;
        }
        $documento = collect(Procesos::documentosProceso($idProceso))->firstWhere('IdDocumento',(int) $idDocumento);
        if(!$documento){
            return response()->json(['message'=>'El documento no corresponde a los solicitados para el proceso.'],422);
        }
        if($documento['estado'] === 'aprobado'){
            return response()->json(['message'=>'El documento ya fue aprobado; no puede reemplazarse.'],422);
        }
        $tercero = DB::table('Procesos as p')->join('Terceros as t','t.IdTercero','=','p.IdTercero')->where('p.IdProceso',$idProceso)->select('t.IdTercero','t.DocumentoTercero')->first();
        $carpeta = $idDocumento == 2 ? 'app/public/tratamiento_datos' : 'app/public/documentos-soporte-'.$tercero->DocumentoTercero.'-'.$idProceso;
        $nombreArchivo = $tercero->DocumentoTercero.'-'.$idProceso.'-'.(int) $idDocumento.'-'.date('YmdHis').'-'.Str::lower(Str::random(6)).'.pdf';
        try{
            $subido = Storage::disk('sftp')->putFileAs($carpeta,$archivo,$nombreArchivo);
        }catch(\Throwable $e){
            report($e);
            $subido = false;
        }
        if(!$subido){
            return response()->json(['message'=>'No fue posible subir el archivo al servidor. Intenta de nuevo.'],422);
        }
        $ruta = $carpeta.'/'.$nombreArchivo;
        Procesos::guardarDocumento($idProceso,$idDocumento,$ruta,$nombreArchivo);
        if($idDocumento == 2){
            Terceros::insertarTratamientoDatos($tercero->IdTercero,$ruta,json_encode(['checkData'=>1,'checkTelefonoContact'=>1,'checkEmailContact'=>1]),$idProceso);
        }
        return response()->json(['res'=>'ok','documento'=>collect(Procesos::documentosProceso($idProceso))->firstWhere('IdDocumento',(int) $idDocumento)]);
    }
    /**---------------------------------------------------------------------------------- */
    /**Aprobar o rechazar documentos de soporte cargados */
    public function gestionDocumentosSoporte(Request $request){
        $idProceso = $request->input('idProceso'); $idDocumento = $request->input('idDocumento'); $accion = $request->input('action');
        if($denegado = $this->accesoProceso($idProceso)){
            return $denegado;
        }
        if($accion == 'verify'){
            $pendientes = Procesos::documentosPendientes($idProceso);
            return response()->json(['documentos'=>Procesos::documentosProceso($idProceso),'pendientes'=>$pendientes,'respuesta'=>empty($pendientes) ? 'aprobados' : 'faltan']);
        }
        $validate = Validator::make($request->all(),[
            'action' => 'required|in:aprobar,aceptar,rechazar',
            'idDocumento' => 'required|integer',
            'idMotivo' => 'nullable|integer|exists:MotivosRechazo,IdMotivo',
            'observacion' => 'nullable|required_if:action,rechazar|string|max:500'
        ],[],[
            'action' => 'acción',
            'idDocumento' => 'documento',
            'idMotivo' => 'motivo',
            'observacion' => 'observación'
        ]);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        if($denegado = $this->accionPermitida($idProceso,'aprobarDocumentos')){
            return $denegado;
        }
        $actual = Procesos::documentoProceso($idProceso,$idDocumento);
        if($accion == 'rechazar'){
            if(!$actual || !in_array($actual->Estado,['cargado','aprobado'],true)){
                return response()->json(['message'=>'Solo se pueden rechazar documentos cargados o aprobados.'],422);
            }
            Procesos::cambiarEstadoDocumento($idProceso,$idDocumento,'rechazado',$request->input('idMotivo'),trim($request->input('observacion')));
            if($idDocumento == 2){
                Procesos::eliminarTratamientoDatos($idProceso);
            }
        }else{
            if(!$actual || $actual->Estado !== 'cargado'){
                return response()->json(['message'=>'Solo se pueden aprobar documentos cargados pendientes de revisión.'],422);
            }
            Procesos::cambiarEstadoDocumento($idProceso,$idDocumento,'aprobado');
        }
        return response()->json(['res'=>'ok','documentos'=>Procesos::documentosProceso($idProceso),'pendientes'=>Procesos::documentosPendientes($idProceso)]);
    }
    /**--------------------------------------------------------------------- */
    /**Ver documento de soporte */
    public function verDocumentoSoporte($idDocumento,$idProceso){
        if($denegado = $this->accesoProceso($idProceso)){
            return $denegado;
        }
        $documento = Procesos::documentoProceso($idProceso,$idDocumento);
        if(!$documento || trim((string) $documento->Ruta) === ''){
            return response()->json(['message'=>'El documento no ha sido cargado.'],404);
        }
        try{
            $contenido = Storage::disk('sftp')->get('/'.ltrim($documento->Ruta,'/'));
        }catch(\Throwable $e){
            report($e);
            $contenido = null;
        }
        if(!$contenido){
            return response()->json(['message'=>'No fue posible leer el archivo del documento.'],404);
        }
        return response($contenido,200,[
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str_replace('"','',$documento->NombreArchivo ?: basename($documento->Ruta)).'"'
        ]);
    }
    /**--------------------------------------------------------------------- */
    /**Editar proceso */
    public function editarProceso(Request $request){
        $idProceso = $request->input('idProceso');
        $condiciones = $this->condicionesProceso($request,$idProceso);
        if($condiciones instanceof JsonResponse){
            return $condiciones;
        }
        ['calculo'=>$calculo,'cupo'=>$cupo,'cupoRegistrado'=>$cupoRegistrado] = $condiciones;
        $datos = [
            'ValorCreditoSolicitado' => $calculo['monto'],
            'NumeroCuotas' => $calculo['plazo'],
            'ValorCuota' => $calculo['cuotaTotal'],
            'TasaInteres' => $calculo['tasa']
        ];
        $actualizarProceso = Procesos::editarProceso($idProceso,$datos);
        if($cupo != $cupoRegistrado){
            Procesos::editarResultadoCalculo($idProceso,['CupoDisponible' => $cupo]);
        }
        if($actualizarProceso){
            return response()->json('ok');
        }else{
            return response()->json('fail');
        }
    }
    public function calcularCondicionesProceso(Request $request){
        $condiciones = $this->condicionesProceso($request,$request->input('idProceso'));
        if($condiciones instanceof JsonResponse){
            return $condiciones;
        }
        $calculo = $condiciones['calculo'];
        return response()->json(['cupo'=>$condiciones['cupo'],'cuota'=>$calculo['cuota'],'seguro'=>$calculo['seguro'],'cuotaTotal'=>$calculo['cuotaTotal'],'plazo'=>$calculo['plazo'],'tasa'=>$calculo['tasa']]);
    }
    private function condicionesProceso(Request $request,$idProceso){
        if(($denegado = $this->accesoProceso($idProceso)) || ($denegado = $this->accionPermitida($idProceso,'editarCredito'))){
            return $denegado;
        }
        if(($cupoRegistrado = Procesos::cupoProceso($idProceso)) === null){
            return response()->json(['message'=>'El proceso no existe o no tiene un cupo calculado.'],422);
        }
        $proceso = Procesos::mostrarInfoProcesos($idProceso,'approveCredit');
        if(empty($proceso['proceso'])){
            return response()->json(['message'=>'No fue posible consultar la información del proceso.'],422);
        }
        $cupo = (int) $cupoRegistrado;
        if($request->filled('nuevoCupo')){
            $nuevoCupo = EvaluadorFormula::limpiarValor($request->input('nuevoCupo'));
            if($nuevoCupo === null || $nuevoCupo <= 0 || $nuevoCupo > $cupo){
                $mensaje = 'El cupo solo puede reducirse respecto al registrado ($ '.number_format($cupo,0,',','.').').';
                return response()->json(['message'=>$mensaje,'errors'=>['nuevoCupo'=>[$mensaje]]],422);
            }
            $cupo = (int) round($nuevoCupo);
        }
        try{
            $calculo = CalculadoraCredito::paraPagaduria($proceso['proceso'][0]->IdPagaduria)->calcular($request->input('valorCredito'),$request->input('periodoCredito'),$proceso['proceso'][0]->FechaTerceroNacimiento);
        }catch(InvalidArgumentException $e){
            return response()->json(['message'=>$e->getMessage()],422);
        }
        if($bloqueo = CalculadoraCredito::bloqueoCupo($calculo,$cupo)){
            return response()->json($bloqueo,422);
        }
        return ['calculo'=>$calculo,'cupo'=>$cupo,'cupoRegistrado'=>$cupoRegistrado];
    }
    /**Envio de email al asesor al aprobar o rechazar un credito */
    public function enviarEmailCredito(Request $request){
        $idProceso = $request->input('idProceso');
        if(($denegado = $this->accesoProceso($idProceso)) || ($denegado = $this->accionPermitida($idProceso,'reenviarCorreo'))){
            return $denegado;
        }
        $validate = Validator::make($request->only('texto'),['texto'=>'nullable|string|max:2000'],[],['texto'=>'texto']);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        $ultimo = DB::table('ProcesosHistorial')->where('IdProceso',$idProceso)->orderByDesc('IdHistorial')->first();
        if((int) Procesos::where('IdProceso',$idProceso)->value('EstadoProceso') === 0 && !($ultimo && (int) $ultimo->EstadoAnterior === 4 && (int) $ultimo->EstadoNuevo === 0)){
            return response()->json(['message'=>'Solo se puede reenviar el correo de rechazo cuando el comité rechazó el crédito en aprobación.'],422);
        }
        $correo = $this->correoDecision($idProceso,$request->input('texto'));
        if($correo !== true){
            return response()->json(['message'=>'No se pudo notificar al asesor: '.$correo,'correo'=>false],422);
        }
        return response()->json(['res'=>'ok','correo'=>true]);
    }

    /**Crear documento con estudio del credito solicitado */
    public function descargarInfoCredito($idProceso){
        if($denegado = $this->accesoProceso($idProceso)){
            return $denegado;
        }
        $proceso = Procesos::mostrarInfoProcesos($idProceso,'approveCredit');
        $configuracion = $proceso['proceso'][0]->Configuracion;
        $operacion = $proceso['proceso'][0]->ValoresOperacion;
        //$nuevoStringConfiguracion = str_replace();
        $separarConfiguracion = explode('|',$configuracion);
        $nuevoArrayConfiguracion = '';
        foreach($separarConfiguracion as $data){
            $nuevoArrayConfiguracion .= $data;
        }
        $separadores = ['+','-','(',')','/','1','2','3','4','5','6','7','8','9','0'];
        $nuevoArrayConfiguracion = str_replace($separadores,'|',$nuevoArrayConfiguracion);
        $separarOperacion = str_replace(['+','-','(',')','/'],'|',$operacion);
        $separarOperacion = explode('|',$separarOperacion);
        $nuevoArrayConfiguracion = explode('|',$nuevoArrayConfiguracion);
        $array[] = new stdClass;
        $contador = 0;
        //return view('procesos.documento-estudio-credito',compact('separarOperacion','nuevoArrayConfiguracion'));
        // foreach($separarConfiguracion as $key => $data){
        //     if(!in_array($data,['-','+','/','(',')','*'])){
        //         foreach($separarOperacion as $key2 => $data2){
        //             if($key)
        //             $array[$contador]->$data = $data2;
        //         }
        //         $contador++;
        //     }
        // }
        
        $rubros = Admin::mostrarRubrosPagaduria($proceso['proceso'][0]->IdPagaduria);
        view()->share(['proceso'=>$proceso,'rubros'=>$rubros]);
        $pdf = Pdf::loadView('procesos.documento-estudio-credito',$proceso,$rubros);
        return $pdf->download('archivo.pdf');
        return view('procesos.documento-estudio-credito',$proceso);
    }
}

// class WSSoapClient extends SoapClient{
// 	private $OASIS = 'http://docs.oasis-open.org/wss/2004/01';
// 	/**
// 	 * WS-Security Username
// 	 * @var string
// 	 */
// 	private $username;
// 	/**
// 	 * WS-Security Password
// 	 * @var string
// 	 */
// 	private $password;
// 	/**
// 	 * WS-Security PasswordType
// 	 * @var string
// 	 */
// 	private $passwordType;
// 	/**
// 	 * Set WS-Security credentials
// 	 * 
// 	 * @param string $username
// 	 * @param string $password
// 	 * @param string $passwordType
// 	 */
// 	public function __setUsernameToken($username, $password, $passwordType){
// 		$this->username = $username;
// 		$this->password = $password;
// 		$this->passwordType = $passwordType;
// 	}
	   
// 	/**
// 	 * Overwrites the original method adding the security header.
// 	 * As you can see, if you want to add more headers, the method needs to be modified.
// 	 */
// 	public function __call($function_name, $arguments){
// 		$this->__setSoapHeaders($this->generateWSSecurityHeader());
// 		return parent::__call($function_name, $arguments);
// 	}

//     public function callBackPassword($password,$hashed_password){
//         return password_verify($password,$hashed_password);
//     }
	    
// 	/**
// 	 * Generate password digest.
// 	 * 
// 	 * Using the password directly may work also, but it's not secure to transmit it without encryption.
// 	 * And anyway, at least with axis+wss4j, the nonce and timestamp are mandatory anyway.
// 	 * 
// 	 * @return string   base64 encoded password digest
// 	 */
// 	private function generatePasswordDigest(){
// 		$this->nonce = mt_rand();
// 		$this->timestamp = gmdate('Y-m-d\TH:i:s\Z');
		
// 		$packedNonce = pack('H*', $this->nonce);
// 		$packedTimestamp = pack('a*', $this->timestamp);
// 		$packedPassword = pack('a*', $this->password);
		
// 		$hash = sha1($packedNonce . $packedTimestamp . $packedPassword);
// 		$packedHash = pack('H*', $hash);
		
// 		return base64_encode($packedHash);
// 	}
	
// 	/**
// 	 * Generates WS-Security headers
// 	 * 
// 	 * @return SoapHeader
// 	 */
// 	private function generateWSSecurityHeader(){
// 		if ($this->passwordType === 'PasswordDigest'){
// 			$password = $this->generatePasswordDigest();
// 			$nonce = sha1($this->nonce);
// 		}elseif ($this->passwordType === 'PasswordText'){
// 			$password = $this->password;
// 			$nonce = sha1(mt_rand());
// 		}else{
// 			return '';
// 		}

// 		$xml = '<wsse:Security mustUnderstand="NONE" xmlns:wsse="' . $this->OASIS . '/oasis-200401-wss-wssecurity-secext-1.0.xsd">
// 	            <wsse:UsernameToken>
// 	            <wsse:Username>' . $this->username . '</wsse:Username>
// 	            <wsse:Password Type="' . $this->OASIS . '/oasis-200401-wss-username-token-profile-1.0#' . $this->passwordType . '">' . $password . '</wsse:Password>
// 	            <wsse:Nonce EncodingType="' . $this->OASIS . '/oasis-200401-wss-soap-message-security-1.0#Base64Binary">' . $nonce . '</wsse:Nonce>';
		
// 		if ($this->passwordType === 'PasswordDigest'){
// 			$xml .= "\n\t" . '<wsu:Created xmlns:wsu="' . $this->OASIS . '/oasis-200401-wss-wssecurity-utility-1.0.xsd">' . $this->timestamp . '</wsu:Created>';
// 		}
		
// 		$xml .= '</wsse:UsernameToken></wsse:Security>';
		
// 		return new SoapHeader($this->OASIS.'/oasis-200401-wss-wssecurity-secext-1.0.xsd','Security', new SoapVar($xml, XSD_ANYXML),true);
// 	}
// }