<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\Petitions;
use App\Models\Procesos;
use App\Models\User;
use App\Services\EvaluadorFormula;
use App\Services\CalculadoraCredito;
use App\Services\Centrales\CentralesRiesgo;
use Illuminate\Support\Facades\DB;
use App\Support\Ambiente;
use ArrayObject;
use InvalidArgumentException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{
    /**Cargar menu */
    public function obtenerMenus(Request $request){
        $menus = Admin::obtenerMenus();
        $rol = User::obtenerRol(auth()->id());
        $submenus = Admin::obtenerSubMenus($rol[0]->IdRol);
        //La base se toma de la peticion en curso; el rtrim evita la barra doble porque RutaSubmenu ya trae la barra inicial
        $arrayMenu = []; $html = ''; $rutaBase = rtrim(url('/'),'/');
        foreach($menus as $dataMenu){//Crear array con todos los menus
            $items = [
                'IdMenu' => $dataMenu->IdMenu,
                'NombreMenu' => $dataMenu->NombreMenu,
                'RutaMenu' => $dataMenu->RutaMenu,
                'CodigoMenu' => $dataMenu->CodigoMenu,
                'submenus' => []
            ];
            array_push($arrayMenu,$items);
        }
        foreach($arrayMenu as $key => $dataMenu){//Crear array copn los submenus según coincidan las relaciones en la base de datos
            foreach($submenus as $dataSubmenu){
                if($dataMenu['IdMenu'] == $dataSubmenu->IdMenu){
                    $itemsMenu = [
                        'IdSubmenu' => $dataSubmenu->IdSubmenu,
                        'NombreSubmenu' => $dataSubmenu->NombreSubmenu,
                        'RutaSubmenu' => $dataSubmenu->RutaSubmenu,
                        'CodigoSubmenu' => $dataSubmenu->CodigoSubmenu
                    ];
                    array_push($arrayMenu[$key]['submenus'],$itemsMenu);
                }
            }
        }
        //Ruta de la pagina abierta, para marcar el submenu activo. Si no llega, ninguno queda activo
        $rutaActual = $request->input('rutaActual','');
        foreach($arrayMenu as $key => $data){//Recorrer array de menus para crear el HTML necesario para mostrar en el DOM
            //Los submenus se despliegan debajo del menu padre (collapse), no sobrepuestos (dropdown)
            $idSubmenu = 'menu-collapse-'.$data['IdMenu'];
            $itemsHtml = ''; $hayActivo = false;
            foreach($data['submenus'] as $items){
                $activo = ($rutaActual !== '' && $items['RutaSubmenu'] === $rutaActual) ? ' active" aria-current="page' : '';
                $hayActivo = $hayActivo || ($activo !== '');
                $hrefSubmenu = ($items['RutaSubmenu'] == 'logout') ? '#' : $rutaBase.$items['RutaSubmenu'];
                $onclick = ($items['RutaSubmenu'] == 'logout') ? ' data-logout' : '';
                $itemsHtml .= '<li class="nav-item">
                                <a class="nav-link submenu-item'.$activo.'" href="'.$hrefSubmenu.'"'.$onclick.'>
                                    <span class="submenu-icon">'.$items['CodigoSubmenu'].'</span>
                                    <span class="submenu-text">'.e($items['NombreSubmenu']).'</span>
                                </a>
                            </li>';
            }
            if(count($data['submenus']) > 0){
                //El modulo de la pagina abierta arranca desplegado
                $submenusHtml = '<div class="collapse submenu-collapse'.($hayActivo ? ' show' : '').'" id="'.$idSubmenu.'">
                                    <ul class="nav nav-pills flex-column submenu-nav">
                                        '.$itemsHtml.'
                                    </ul>
                                </div>';
                $liClass = 'nav-item has-submenu';
                $etiqueta = 'button';
                $aExtra = ' type="button" class="nav-link submenu-toggle'.($hayActivo ? '' : ' collapsed').'" data-bs-toggle="collapse" data-bs-target="#'.$idSubmenu.'" aria-expanded="'.($hayActivo ? 'true' : 'false').'" aria-controls="'.$idSubmenu.'"';
                $caret = '<i class="fas fa-chevron-down submenu-caret" aria-hidden="true"></i>';
            }else{
                $submenusHtml = '';
                $liClass = 'nav-item';
                $etiqueta = 'a';
                $aExtra = ' href="'.$data['RutaMenu'].'" class="nav-link'.(($rutaActual !== '' && '/'.ltrim($data['RutaMenu'],'/') === $rutaActual) ? ' active" aria-current="page' : '').'"';
                $caret = '';
            }
            $html .= '<li class="'.$liClass.'">
                        <'.$etiqueta.$aExtra.'>
                            '.$data['CodigoMenu'].' <span class="text-truncate">'.e($data['NombreMenu']).'</span>
                            '.$caret.'
                        </'.$etiqueta.'>
                        '.$submenusHtml.'
                    </li>';
        }
        $ul = '<ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-start" id="menu">
                '.$html.'
                </ul>';
        return response()->json(compact('ul'));
    }
    /**----------------------------------------------- */
    /**Cargar items de menus segun el rol del usuario */
    public function obtenerSubMenus(Request $request){
        $submenus = Admin::obtenerSubMenus($request->input('IdRol'));
        return response()->json(compact('submenus'));
    }
    /**----------------------------------------------- */
    /**Actualizar datos del usuario iniciado */
    public function editarUsuario(Request $request){
        $rules = [
            'nombreUsuario' => 'required|string',
            'email' => 'required|email',
            'documento' => 'required|numeric|min:6'
        ];
        $validate = Validator::make($request->all(), $rules);
        if($validate->fails()){
            return response()->json(['errors'=>$validate->errors()]);
        }else{
            $updateInfo = User::where('IdUsuario',auth()->id())->update([
                'nombreUsuario' => $request->input('nombreUsuario'),
                'email' => $request->input('email'),
                'documentoUsuario' => $request->input('documento'),
            ]);
            if($updateInfo){
                $user = Admin::perfilUsuario();
            }
            return response()->json(['success'=>'Datos de usuario editados correctamente','data'=>$user]);
        }
    }
    /**----------------------------------------------- */
    /**Actualizar la contraseña */
    public function editarContrasena(Request $request){
        $rules = [
            'password' => 'min:6|required_with:confirmPassword|same:confirmPassword',
            'confirmPassword' => 'min:6|required'
        ];
        $validate = Validator::make($request->all(),$rules);
        if($validate->fails()){
            return response()->json(['errors'=>$validate->errors()]);
        }else{
            $editarContrasena = User::where('IdUsuario',auth()->id())->update([
                'password' => Hash::make($request->input('password'))
            ]);
            return response()->json(['success'=>'Cambio de contraseña exitoso!']);
        }
    }
    /**Subir foto de perfil */
    public function subirFotoPerfil(Request $request){
        $rule = ['photo'=>'required'];
        $validate = Validator::make($request->all(),$rule);
        if($validate->fails()){
            return response()->json(['errors'=>$validate->errors()]);
        }
        if($request->hasFile('photo')){
            $usuario = Admin::perfilUsuario();
            $documentoUsuario = $usuario[0]->documentoUsuario;
            $nombreArchivo = $documentoUsuario.'-perfil.jpg';
            $carpetaArchivo = '/images-perfiles';
            if($request->file('photo')->storeAs('images-perfiles',$nombreArchivo,'public')){/**Storage::disk('local')->putFileAs($carpetaArchivo,$request->file('photo'),$nombreArchivo) */
                $actualizarFoto = User::where('IdUsuario',auth()->id())->update([
                    'nombreImagen' => $nombreArchivo
                ]);
                return response()->json(['success'=>'Foto subida con exito!']);
            }
            return response()->json(['errors'=>'Surgieron errores al intentar subir el archivo']);
        }
        return response()->json(['errors'=>'No se ha seleccionado un archivo válido']);
    }
    /**Cambiar el ambiente de datos de la sesión (producción o demo) */
    public function cambiarAmbiente(Request $request){
        $rules = ['ambiente'=>'required|in:produccion,demo'];
        $validate = Validator::make($request->all(),$rules);
        if($validate->fails()){
            return response()->json(['errors'=>$validate->errors()]);
        }
        //Volver a producción es la acción segura y nunca se bloquea: si no fuera así,
        //un cambio de rol con la sesión viva dejaría al usuario encerrado en demo.
        if($request->input('ambiente') == Ambiente::DEMO && !Ambiente::puedeConmutar()){
            return response()->json(['errors'=>['ambiente'=>['No tienes permiso para cambiar de ambiente.']]]);
        }
        $ambiente = Ambiente::aplicar($request->input('ambiente'));
        $request->session()->put(Ambiente::CLAVE,$ambiente);
        $mensaje = ($ambiente == Ambiente::DEMO)? 'Ahora estás en el ambiente Demo' : 'Ahora estás en el ambiente de Producción';
        return response()->json(['success'=>$mensaje,'data'=>['ambiente'=>$ambiente]]);
    }
    /**Mostrar foto de perfil */
    public function mostrarFotoPerfil(){
        $url = storage_path().'/images-perfiles/';
        return response()->json($url);
        if(!Storage::exists($url)){
            return 'foto-perfil';
        }
        $foto = Storage::disk('sftp')->get('/'.$url.'/'.$imagen);
        $tipo = Storage::mimeType('/'.$url.'/'.$imagen);

        $response = response()->make($foto,200);
        $response->headers->set('Content-Type',$tipo);

        return $response;
    }
    /**----------------------------------------------- */
    /**Mostrar información de un usuario seleccionado*/
    public function mostrarInfoUsuario(Request $request){
        $id = $request->input('idUsuario');
        $info = Petitions::totalUsuarios($id);
        return response()->json($info);
    }
    /**----------------------------------------------- */
    /**Actualizar información de usuario seleccionado */
    public function editarUsuarios(Request $request){
        if($request->input('estado') != null){
            $cambiarEstado = Admin::changeStateUser($request->input('idUsuario'),$request->input('estado'));
            return response()->json(['success'=>'El estado del usuario ha cambiado']);
        }else{
            $rules = [
                'nombreUsuario' => 'required',
                'documentoUsuario' => 'required',
                'email' => 'required|email',
                'rol-user' => 'required'
            ];
            $validate = Validator::make($request->all(), $rules);
            if($validate->fails()){
                return response()->json(['errors'=>$validate->errors()]);
            }else{
                //guardar datos del usuario
                $save = Admin::editarUsuarios($request->input('id-user-update'),$request->input('nombreUsuario'),$request->input('documentoUsuario'),$request->input('email'));
                //consultar si el usuario no tiene rol asignado aun
                $consulta = Admin::checkRol($request->input('id-user-update'));
                if(empty($consulta)){
                    $insertRol = Admin::insertRol($request->input('id-user-update'),$request->input('rol-user'));
                }else{
                    $edit_rol = Admin::editRol($request->input('id-user-update'),$request->input('rol-user'));
                }
                return response()->json(['success'=>'Datos de usuario editados correctamente']);
            }
        }
    }
    /**----------------------------------------------- */
    /**Mostrar procesos relacionados con el usuario seleccionado */
    public function mostrarProcesosAsesor(Request $request){
        $procesos = Procesos::showProcessByState('any');
    }
    /**----------------------------------------------- */
    public function mostrarInfoPagaduria(Request $request){
        $idPagaduria = $request->input('IdPagaduria',$request->input('idPagaduria'));
        $parametros = Admin::pagaduria($idPagaduria);
        if(!$parametros){
            return response()->json(['message'=>'La pagaduría no existe.'],422);
        }
        $pagaduria = Admin::mostrarInfoPagaduria($idPagaduria,'',$request->input('idConfiguracion',''));
        $formulas = Admin::formulasPagaduria($idPagaduria);
        $tokens = [];
        foreach($formulas as $formula){
            $tokens = array_merge($tokens,array_map('trim',explode('|',mb_strtolower((string) $formula->Configuracion))));
        }
        $rubros = array_map(function($rubro) use ($tokens){
            $rubro->enUso = in_array(mb_strtolower($this->tokenRubro($rubro->NombreRubro)),$tokens,true);
            return $rubro;
        },Admin::mostrarRubrosPagaduria($idPagaduria));
        $reglasEdad = Admin::reglasEdadPagaduria($idPagaduria);
        $ultimaAuditoria = Admin::ultimaAuditoriaPagaduria($idPagaduria);
        return response()->json(compact('pagaduria','parametros','formulas','reglasEdad','rubros','ultimaAuditoria'));
    }
    /**----------------------------------------------- */
    public function listarPagadurias(){
        $pagadurias = Petitions::mostrarPagadurias(true);
        return response()->json(compact('pagadurias'));
    }
    /**----------------------------------------------- */
    public function guardarPagaduria(Request $request){
        $validate = Validator::make($request->all(),[
            'idPagaduria' => 'nullable|integer|exists:Pagadurias,IdPagaduria',
            'nombrePagaduria' => 'required|string|max:50',
            'usaReglaSMMLV' => 'nullable|boolean',
            'umbralSMMLV' => 'nullable|numeric|min:1|max:999.99',
            'estadoPagaduria' => 'nullable|boolean'
        ]);
        if($validate->fails()){
            return $this->errorValidacion($validate);
        }
        $idPagaduria = $request->input('idPagaduria');
        $nombre = trim($request->input('nombrePagaduria'));
        $duplicada = DB::table('Pagadurias')->whereRaw("UPPER(LTRIM(RTRIM(REPLACE(REPLACE(NombrePagaduria,CHAR(13),''),CHAR(10),'')))) = UPPER(?)",[$nombre])
            ->when($idPagaduria,function($consulta) use ($idPagaduria){ return $consulta->where('IdPagaduria','<>',$idPagaduria); })->exists();
        if($duplicada){
            return response()->json(['message'=>'Ya existe una pagaduría con ese nombre.','errors'=>['nombrePagaduria'=>['Ya existe una pagaduría con ese nombre.']]],422);
        }
        $actual = $idPagaduria ? Admin::pagaduria($idPagaduria) : null;
        $datos = [
            'NombrePagaduria' => $nombre,
            'UsaReglaSMMLV' => $request->has('usaReglaSMMLV') ? (int) $request->boolean('usaReglaSMMLV') : ($actual ? (int) $actual->UsaReglaSMMLV : 0),
            'UmbralSMMLV' => $request->filled('umbralSMMLV') ? (float) $request->input('umbralSMMLV') : ($actual ? (float) $actual->UmbralSMMLV : 2),
            'EstadoPagaduria' => $request->has('estadoPagaduria') ? (int) $request->boolean('estadoPagaduria') : ($actual ? (int) $actual->EstadoPagaduria : 1)
        ];
        $idPagaduria = Admin::guardarPagaduria($idPagaduria,$datos);
        return response()->json(['res'=>$actual ? 'Pagaduria editada correctamente' : 'Pagaduria creada correctamente','idPagaduria'=>(int) $idPagaduria]);
    }
    /**----------------------------------------------- */
    public function cambiarEstadoPagaduria(Request $request){
        $validate = Validator::make($request->all(),[
            'idPagaduria' => 'required|integer|exists:Pagadurias,IdPagaduria',
            'estado' => 'required|boolean'
        ]);
        if($validate->fails()){
            return $this->errorValidacion($validate);
        }
        Admin::guardarPagaduria($request->input('idPagaduria'),['EstadoPagaduria'=>(int) $request->boolean('estado')]);
        return response()->json(['res'=>$request->boolean('estado') ? 'Pagaduria activada' : 'Pagaduria inactivada']);
    }
    /**----------------------------------------------- */
    public function guardarReglaEdad(Request $request){
        $validate = Validator::make($request->all(),[
            'idReglaEdad' => 'nullable|integer',
            'idPagaduria' => 'required|integer|exists:Pagadurias,IdPagaduria',
            'edadMin' => 'required|integer|min:18|max:120',
            'edadMax' => 'required|integer|min:18|max:120|gte:edadMin',
            'plazoMaximo' => 'required|integer|min:1|max:360',
            'porcentajeSeguro' => 'required|numeric|min:0|max:0.05'
        ]);
        if($validate->fails()){
            return $this->errorValidacion($validate);
        }
        $idPagaduria = $request->input('idPagaduria'); $idReglaEdad = $request->input('idReglaEdad');
        if($idReglaEdad && !DB::table('PagaduriasReglasEdad')->where('IdReglaEdad',$idReglaEdad)->where('IdPagaduria',$idPagaduria)->exists()){
            return response()->json(['message'=>'La regla de edad no pertenece a la pagaduría.'],422);
        }
        $solape = DB::table('PagaduriasReglasEdad')->where('IdPagaduria',$idPagaduria)
            ->where('EdadMin','<=',$request->input('edadMax'))->where('EdadMax','>=',$request->input('edadMin'))
            ->when($idReglaEdad,function($consulta) use ($idReglaEdad){ return $consulta->where('IdReglaEdad','<>',$idReglaEdad); })->first();
        if($solape){
            $mensaje = 'El rango se cruza con la regla de '.$solape->EdadMin.' a '.$solape->EdadMax.' años.';
            return response()->json(['message'=>$mensaje,'errors'=>['edadMin'=>[$mensaje]]],422);
        }
        $idReglaEdad = Admin::guardarReglaEdad($idReglaEdad ?: '',$idPagaduria,[
            'EdadMin' => (int) $request->input('edadMin'),
            'EdadMax' => (int) $request->input('edadMax'),
            'PlazoMaximo' => (int) $request->input('plazoMaximo'),
            'PorcentajeSeguro' => (float) $request->input('porcentajeSeguro')
        ]);
        return response()->json(['res'=>'Regla de edad guardada correctamente','idReglaEdad'=>(int) $idReglaEdad]);
    }
    /**----------------------------------------------- */
    public function eliminarReglaEdad(Request $request){
        if(!Admin::eliminarReglaEdad($request->input('idReglaEdad'),$request->input('idPagaduria'))){
            return response()->json(['message'=>'La regla de edad no existe o no pertenece a la pagaduría.'],422);
        }
        return response()->json(['res'=>'Regla de edad eliminada correctamente']);
    }
    /**----------------------------------------------- */
    public function eliminarRubroConfiguracion(Request $request){
        $idPagaduria = $request->input('IdPagaduria',$request->input('idPagaduria'));
        $rubro = DB::table('PagaduriasRubros')->where('IdRubro',$request->input('idRubro'))->where('IdPagaduria',$idPagaduria)->first();
        if(!$rubro){
            return response()->json(['message'=>'El rubro no existe o no pertenece a la pagaduría.'],422);
        }
        $token = mb_strtolower($this->tokenRubro($rubro->NombreRubro));
        foreach(Admin::formulasPagaduria($idPagaduria) as $formula){
            if(in_array($token,array_map('trim',explode('|',mb_strtolower((string) $formula->Configuracion))),true)){
                return response()->json(['message'=>'El rubro "'.$rubro->NombreRubro.'" está en uso en una fórmula de la pagaduría y no puede eliminarse.'],422);
            }
        }
        Admin::eliminarRubro($rubro->IdRubro,$idPagaduria);
        return response()->json(['res'=>'Rubro eliminado correctamente']);
    }
    /**----------------------------------------------- */
    public function probarFormula(Request $request){
        $valores = $request->input('valores',[]);
        if(is_string($valores)){
            $valores = json_decode($valores,true);
        }
        if(!is_array($valores) || trim((string) $request->input('configuracion')) === ''){
            return response()->json(['message'=>'Se requiere la configuración y los valores a probar.'],422);
        }
        if(!array_key_exists('salarioMinimoMensual',$valores)){
            $valores['salarioMinimoMensual'] = CalculadoraCredito::valorVariable('SalarioMinimoMensual');
        }
        try{
            $evaluacion = EvaluadorFormula::evaluar($request->input('configuracion'),$valores);
        }catch(InvalidArgumentException $e){
            return response()->json(['message'=>'La fórmula no es válida: '.$e->getMessage()],422);
        }
        return response()->json($evaluacion);
    }
    /**----------------------------------------------- */
    private function tokenRubro($nombreRubro){
        $palabras = explode(' ',trim($nombreRubro));
        $nombre = array_shift($palabras);
        foreach($palabras as $palabra){
            $nombre .= mb_strtoupper(mb_substr($palabra,0,1)).mb_substr($palabra,1);
        }
        return $nombre;
    }
    private function errorValidacion($validate){
        return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
    }
    /**----------------------------------------------- */
    /**Guardar datos de pagaduria */
    public function guardarPagaduriaInfo(Request $request){
        if($request->input('action') == 'config'){// si se cambia la configuración
            if(!DB::table('CuposConfigCalculos')->where('IdConfigCalculo',$request->input('idConfiguracion'))->where('IdPagaduria',$request->input('idPagaduria'))->exists()){
                return response()->json(['message'=>'La configuración no pertenece a la pagaduría seleccionada.'],422);
            }
            $nombres = [];
            foreach(Admin::mostrarRubrosPagaduria($request->input('idPagaduria')) as $rubro){
                $nombres[] = $this->tokenRubro($rubro->NombreRubro);
            }
            array_push($nombres,'salarioMinimoMensual','ingresos');
            try{
                EvaluadorFormula::validar($request->input('configuracion'),$nombres,true);
            }catch(InvalidArgumentException $e){
                return response()->json(['message'=>'La configuración no es válida: '.$e->getMessage()],422);
            }
            $guardarPagaduria = Admin::guardarPagaduriaInfo('',$request->input('action'),$request->input('idConfiguracion'),$request->input('configuracion'),$request->input('idPagaduria'));
            if($guardarPagaduria){
                return response()->json(['res'=>'Configuracion de cupo editada correctamente']);
            }
            return response()->json(['message'=>'No fue posible guardar la configuración.'],422);
        }elseif($request->input('action') == 'infoBasica'){ //si solo se guarda el nombre de la pagaduria
            return $this->guardarPagaduria($request);
        }
    }
    /**Guardar rubro nuevo */
    public function guardarRubroConfiguracion(Request $request){
        $rules = ['nombreRubro'=>'required|string|max:50','IdPagaduria'=>'required|integer|exists:Pagadurias,IdPagaduria'];
        $validate = Validator::make($request->all(), $rules);
        if($validate->fails()){
            return $this->errorValidacion($validate);
        }else{
            $token = $this->tokenRubro($request->input('nombreRubro'));
            foreach(Admin::mostrarRubrosPagaduria($request->input('IdPagaduria')) as $rubro){
                if(mb_strtolower($this->tokenRubro($rubro->NombreRubro)) === mb_strtolower($token)){
                    return response()->json(['message'=>'El rubro ya existe en la pagaduría.','errors'=>['nombreRubro'=>['El rubro ya existe en la pagaduría.']]],422);
                }
            }
            $guardar = Admin::guardarRubroConfiguracion($request->input('nombreRubro'),$request->input('IdPagaduria'));
            if($guardar){
                return response()->json('ok');
            }else{
                return response()->json('error');
            }
        }
    }
    /**Peticiones de tablas de administrador */
    public function mostrarInfoAdmin(Request $request){
        switch($request->input('peticion')){
            case 'roles':
                $rol = Petitions::mostrarRoles($request->input('idRol'));
                $submenusTodos = Admin::obtenerSubMenus();
                $submenusRol = Admin::obtenerSubMenus($request->input('idRol'));
                return response()->json(['rol'=>$rol,'submenusTodos'=>$submenusTodos,'submenusRol'=>$submenusRol]);
            break;
            case 'variables':
                $variable = Petitions::mostrarValoresVariables($request->input('idVariable'));
                return response()->json($variable);
            break;
        }
    }
    /**---------------------------------------------------- */
    /**Guardar datos de variables, roles y mas elementos del sitio... */
    public function guardarDatosSitio(Request $request){
        switch($request->input('option')){
            case 'variables':
                $rule = [
                    'nombreVariable' => 'required',
                    'valorVariable' => 'required'
                ];
                $validate = Validator::make($request->all(), $rule);
                if($validate->fails()){
                    return response()->json(['errors'=>$validate->errors()]);
                }
                $sistema = ['salariominimomensual'=>'SalarioMinimoMensual','tasainteres'=>'TasaInteres'];
                $nombre = trim($request->input('nombreVariable')); $valor = trim($request->input('valorVariable'));
                $actual = $request->input('idVariable') != '' ? Petitions::mostrarValoresVariables($request->input('idVariable')) : [];
                if($request->input('idVariable') != '' && count($actual) == 0){
                    return response()->json(['message'=>'La variable no existe.'],422);
                }
                $nombreActual = count($actual) ? trim($actual[0]->NombreValorVariable) : '';
                if(isset($sistema[strtolower($nombreActual)]) && $nombre !== $nombreActual){
                    return response()->json(['message'=>'La variable '.$nombreActual.' es del sistema y no puede renombrarse.','errors'=>['nombreVariable'=>['La variable '.$nombreActual.' es del sistema y no puede renombrarse.']]],422);
                }
                if(!isset($sistema[strtolower($nombreActual)]) && isset($sistema[strtolower($nombre)])){
                    return response()->json(['message'=>'Ya existe la variable de sistema '.$sistema[strtolower($nombre)].'.','errors'=>['nombreVariable'=>['Ya existe la variable de sistema '.$sistema[strtolower($nombre)].'.']]],422);
                }
                if(isset($sistema[strtolower($nombre)])){
                    $error = null;
                    if(!is_numeric($valor)){
                        $error = 'El valor de '.$nombre.' debe ser numérico.';
                    }elseif(strtolower($nombre) == 'salariominimomensual' && $valor <= 0){
                        $error = 'El salario mínimo debe ser mayor a cero.';
                    }elseif(strtolower($nombre) == 'tasainteres' && ($valor <= 0 || $valor > 10)){
                        $error = 'La tasa de interés mensual debe ser mayor a 0 y menor o igual a 10 %.';
                    }
                    if($error){
                        return response()->json(['message'=>$error,'errors'=>['valorVariable'=>[$error]]],422);
                    }
                }
                $guardar = Admin::guardarVariable($request->input('idVariable'),$nombre,$valor);
                if($guardar){
                    return response()->json('ok');
                }else{
                    return response()->json('fail');
                }
            break;
            case 'roles':
                if(!collect(User::obtenerRol(auth()->id()))->contains('IdRol',1)){
                    return response()->json(['message'=>'Esta sección es solo editable para un administrador'],403);
                }
                $submenus = json_decode($request->input('submenus'));
                $eliminados = json_decode($request->input('submenusEliminados'));
                foreach($submenus as $data){
                    $guardar = Admin::guardarPermisoRol($request->input('idRol'),$data);
                }
                $consultarPermisoRol = Admin::mostrarPermisosRoles('',$request->input('idRol'),'14');
                if(count($consultarPermisoRol) == 0){
                    $actualizar = Admin::guardarPermisoRol($request->input('idRol'),'14');
                }
                foreach($eliminados as $data){
                    $eliminar = Admin::eliminarPermisoRol($request->input('idRol'),$data);
                }
                return response()->json(['res'=>'ok']);
            break;
        }
    }
    private function soloAdministrador(){
        return collect(User::obtenerRol(auth()->id()))->contains('IdRol',1) ? null : response()->json(['message'=>'Esta sección es solo editable para un administrador'],403);
    }
    public function centralesConfigListar(){
        if($denegado = $this->soloAdministrador()){
            return $denegado;
        }
        $centrales = CentralesRiesgo::instancia();
        $ultimas = [];
        foreach($centrales->claves() as $clave){
            $ultimas[$clave] = $centrales->ultimaGlobal($clave);
        }
        $usuarios = Procesos::nombresUsuarios(array_column(array_filter($ultimas),'idUsuario'));
        return response()->json([
            'predeterminado' => $centrales->predeterminado(),
            'predeterminadoDisponible' => $centrales->predeterminadoDisponible(),
            'vigenciaDias' => $centrales->vigenciaDias(),
            'simuladoHabilitado' => (bool) config('centrales.simulado_habilitado'),
            'proveedores' => array_map(function($clave) use ($centrales,$ultimas,$usuarios){
                return [
                    'clave' => $clave,
                    'nombre' => $centrales->nombre($clave),
                    'habilitado' => $centrales->habilitado($clave),
                    'editable' => $clave !== 'simulado',
                    'configurado' => $centrales->configurado($clave),
                    'ambiente' => $centrales->ambiente($clave),
                    'predeterminado' => $clave === $centrales->predeterminado(),
                    'pendienteValidar' => $centrales->pendienteValidar($clave),
                    'ultimaConsulta' => $ultimas[$clave] ? ['fecha'=>$ultimas[$clave]['fechaConsulta'],'usuario'=>$usuarios[$ultimas[$clave]['idUsuario']] ?? null] : null
                ];
            },$centrales->claves())
        ]);
    }
    public function centralesConfigGuardar(Request $request){
        if($denegado = $this->soloAdministrador()){
            return $denegado;
        }
        $centrales = CentralesRiesgo::instancia();
        $editables = array_values(array_diff($centrales->claves(),['simulado']));
        $validate = Validator::make($request->all(),[
            'predeterminado' => ['present','nullable',Rule::in($centrales->claves())],
            'habilitados' => 'required|array',
            'habilitados.*' => 'boolean',
            'vigenciaDias' => 'required|integer|between:1,365'
        ],[],['predeterminado'=>'proveedor predeterminado','habilitados'=>'proveedores habilitados','vigenciaDias'=>'vigencia en días']);
        $validate->after(function($validator) use ($request,$editables){
            foreach(array_keys((array) $request->input('habilitados')) as $clave){
                if(!in_array($clave,$editables,true)){
                    $validator->errors()->add('habilitados','El proveedor '.$clave.' no se puede habilitar o deshabilitar desde aquí.');
                }
            }
        });
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        $valores = [];
        foreach($request->input('habilitados') as $clave => $habilitado){
            $valores['habilitado.'.$clave] = filter_var($habilitado,FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }
        $predeterminado = $request->input('predeterminado') === '' ? null : $request->input('predeterminado');
        $habilitado = $predeterminado === null || (isset($valores['habilitado.'.$predeterminado]) ? $valores['habilitado.'.$predeterminado] === '1' : $centrales->habilitado($predeterminado));
        if($predeterminado !== $centrales->predeterminado() && (!$habilitado || ($predeterminado !== null && !$centrales->configurado($predeterminado)))){
            $mensaje = 'El proveedor predeterminado debe estar habilitado y tener credenciales configuradas.';
            return response()->json(['message'=>$mensaje,'errors'=>['predeterminado'=>[$mensaje]]],422);
        }
        $centrales->guardarAjustes($valores + ['predeterminado'=>(string) $predeterminado,'vigenciaDias'=>(int) $request->input('vigenciaDias')],auth()->id());
        return $this->centralesConfigListar();
    }
    public function centralesProbarConexion(Request $request){
        if($denegado = $this->soloAdministrador()){
            return $denegado;
        }
        $centrales = CentralesRiesgo::instancia();
        $validate = Validator::make($request->all(),['proveedor'=>['required',Rule::in($centrales->claves())]],[],['proveedor'=>'proveedor']);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        return response()->json(array_merge(['proveedor'=>$request->input('proveedor')],$centrales->proveedor($request->input('proveedor'))->probarConexion()));
    }
}
