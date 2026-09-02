<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\Petitions;
use App\Models\Procesos;
use App\Models\User;
use App\Support\Ambiente;
use ArrayObject;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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
                $activo = ($rutaActual !== '' && $items['RutaSubmenu'] === $rutaActual) ? ' active' : '';
                $hayActivo = $hayActivo || ($activo !== '');
                $hrefSubmenu = ($items['RutaSubmenu'] == 'logout') ? '#' : $rutaBase.$items['RutaSubmenu'];
                $onclick = ($items['RutaSubmenu'] == 'logout') ? ' onclick="logOut()"' : '';
                $itemsHtml .= '<li class="nav-item">
                                <a class="nav-link submenu-item'.$activo.'" href="'.$hrefSubmenu.'"'.$onclick.'>
                                    <span class="submenu-icon">'.$items['CodigoSubmenu'].'</span>
                                    <span class="submenu-text">'.$items['NombreSubmenu'].'</span>
                                </a>
                            </li>';
            }
            if(count($data['submenus']) > 0){
                //El modulo de la pagina abierta arranca desplegado
                $submenusHtml = '<div class="collapse submenu-collapse'.($hayActivo ? ' show' : '').'" id="'.$idSubmenu.'" data-bs-parent="#menu">
                                    <ul class="nav nav-pills flex-column submenu-nav">
                                        '.$itemsHtml.'
                                    </ul>
                                </div>';
                $liClass = 'nav-item has-submenu';
                $hrefMenu = '#'.$idSubmenu;
                $aClass = ' submenu-toggle'.($hayActivo ? '' : ' collapsed');
                $aExtra = ' data-bs-toggle="collapse" aria-expanded="'.($hayActivo ? 'true' : 'false').'" aria-controls="'.$idSubmenu.'"';
                $caret = '<i class="fas fa-chevron-down submenu-caret"></i>';
            }else{
                $submenusHtml = '';
                $liClass = 'nav-item';
                $hrefMenu = $data['RutaMenu'];
                $aClass = '';
                $aExtra = '';
                $caret = '';
            }
            $html .= '<li class="'.$liClass.'">
                        <a href="'.$hrefMenu.'" class="nav-link text-truncate'.$aClass.'"'.$aExtra.'>
                            '.$data['CodigoMenu'].' <span class="ms-1 d-none d-sm-inline">'.$data['NombreMenu'].'</span>
                            '.$caret.'
                        </a>
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
    /** */
    public function procesoSoloInfo(Request $request){
        $proceso = Procesos::mostrarProceso($request->input('estado'),$request->input('idProceso'));
        return response()->json(compact('proceso'));
    }

    public function mostrarInfoPagaduria(Request $request){
        if($request->input('idConfiguracion')){
            $pagaduria = Admin::mostrarInfoPagaduria($request->input('IdPagaduria'),'',$request->input('idConfiguracion'));
        }else{
            $pagaduria = Admin::mostrarInfoPagaduria($request->input('IdPagaduria'));
        }
        return response()->json(compact('pagaduria'));
    }
    /**----------------------------------------------- */
    public function cantidadConfigPagaduria(Request $request){
        $idPagaduria = $request->input('IdPagaduria');
        $pagaduria = Admin::mostrarInfoPagaduria($idPagaduria);
        return response()->json($pagaduria);
    }
    /**----------------------------------------------- */
    public function mostrarRubrosPagaduria(Request $request){
        $idPagaduria = $request->input('IdPagaduria');
        $rubros = Admin::mostrarRubrosPagaduria($idPagaduria);
        if($rubros){
            $html = '<option value="">- Selecciona un dato para agregar -</option>';
            foreach($rubros as $data){
                $html .= '<option value="'.$data->NombreRubro.'">'.$data->NombreRubro.'</option>';
            }
        }
        return response()->json($html);
    }
    /**----------------------------------------------- */
    /**Guardar datos de pagaduria */
    public function guardarPagaduriaInfo(Request $request){
        if($request->input('action') == 'config'){// si se cambia la configuración
            $guardarPagaduria = Admin::guardarPagaduriaInfo('',$request->input('action'),$request->input('idConfiguracion'),$request->input('configuracion'));
            if($guardarPagaduria){
                return response()->json(['res'=>'Configuracion de cupo editada correctamente']);
            }
        }elseif($request->input('action') == 'infoBasica'){ //si solo se guarda el nombre de la pagaduria
            $rule = [
                'nombrePagaduria' => 'required'
            ];
            $validate = Validator::make($request->all(), $rule);
            if($validate->fails()){
                return response()->json(['errors'=>$validate->errors()]);
            }
            $guardarPagaduria = Admin::guardarPagaduriaInfo($request->input('nombrePagaduria'));
            if($guardarPagaduria){
                return response()->json(['res'=>'Pagaduria creada correctamente']);
            }
        }
    }
    /**Guardar rubro nuevo */
    public function guardarRubroConfiguracion(Request $request){
        $rules = ['nombreRubro'=>'required','IdPagaduria'=>'required'];
        $validate = Validator::make($request->all(), $rules);
        if($validate->fails()){
            return response()->json(['errors'=>$validate->errors()]);
        }else{
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
                $guardar = Admin::guardarVariable($request->input('idVariable'),$request->input('nombreVariable'),$request->input('valorVariable'));
                if($guardar){
                    return response()->json('ok');
                }else{
                    return response()->json('fail');
                }
            break;
            case 'roles':
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
}
