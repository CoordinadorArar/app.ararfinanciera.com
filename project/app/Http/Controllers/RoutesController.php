<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Contable;
use App\Models\Petitions;
use App\Models\Procesos;
use App\Models\User;
use App\Models\Terceros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class RoutesController extends Controller
{   
    public function vistaRegistroInformacion(){ //vista ingreso de datos de usuario para iniciar proceso
        return view('perfilamiento.registro-datos.main');
    }
    /**Vista calculo de cupo */
    public function calcularVista(){
        $procesos = Procesos::mostrarProcesos(1);
        $fecha_actual = date('Ymd');
        $fecha_actual = date("Y-m-d",strtotime($fecha_actual."- 18 years")); 
        return view('perfilamiento.calcular.simulador',compact('procesos','fecha_actual'));
    }
    /**Vista creación de clientes siesa */
    public function crearClienteSiesa(){
        $validarUsuariosSiesa = Terceros::validarUsuariosSiesa(''); 
        return view('perfilamiento.crear-cliente-siesa',compact('validarUsuariosSiesa'));
    }
    /**Vista de todos los procesos ya iniciados en estado 2=consulta de centrales de riesgo para hacer gestion segun rol iniciado */
    public function listaProcesos(){
        $rol = User::obtenerRol(auth()->id());
        $procesos = Procesos::listaProcesos(auth()->id());
        return view('procesos.main',compact('procesos','rol'));
    }
    /**Vista documento contable */
    public function documentoContable(){
        return view('contable.documento-contable');
    }
    /**Vista documento operaciones */
    public function documentoOperaciones(){
        $fecha = date('Y-m-d');
        $datosOperaciones = Contable::datosOperaciones(explode('-',$fecha)[0].'0101','');
        return view('contable.documento-operaciones',compact('datosOperaciones'));
    }
    /**Vista reclasificar operaciones */
    public function reclasificarOperaciones(){
        return view('contable.reclasificar-operaciones');
    }
    /**Vista cupones */
    public function cupones(){
        $cupones = Contable::mostrarCuponesGenerados();
        $bancos = Contable::mostrarBancos();
        return view('contable.cupones',compact('cupones','bancos'));
    }
    /**Vista creacion de pagadurias */
    public function crearPagaduria(){
        $pagadurias = Petitions::mostrarPagadurias();
        return view('administracion.pagadurias.crear-pagaduria',compact('pagadurias'));
    }
    /**Vista administración de asesores */
    public function GestionAsesores(){
        $asesores = Petitions::mostrarAsesores();
        $procesos = Procesos::showProcessByState('any'); //procesos
        return view('administracion.usuario.asesores.lista-asesores',compact('asesores','procesos'));
    }
    /**Vista administración de usuarios */
    public function GestionUsuarios(){
        $users = Petitions::totalUsuarios();
        return view('administracion.usuario.lista-usuarios',compact('users'));
    }
    /**Gestión de roles, valores variables y otras configuraciones del sitio en general */
    public function gestionSitio(){
        $rol = User::obtenerRol(auth()->id());
        $roles = Petitions::mostrarRoles();
        $valoresVariables = Petitions::mostrarValoresVariables();
        return view('administracion.sitio.main',compact('roles','valoresVariables','rol'));
    }
    /**Vista consulta de folios (Gestión Documental) */
    public function consultaFolios(){
        return view('gestion-documental.consulta-folios');
    }
    /**Vista digitalizar facturas (Gestión Documental) */
    public function digitalizarFacturas(){
        return view('gestion-documental.digitalizar-facturas');
    }
    /**Vista gestión de cajas: cerrar cajas y asignar ubicación física (Gestión Documental) */
    public function gestionCajas(){
        return view('gestion-documental.gestion-cajas');
    }
    /**Vista tablero de facturas pendientes de digitalizar, por asesor y por mes (Gestión Documental) */
    public function tableroDigitalizacion(){
        return view('gestion-documental.tablero-digitalizacion');
    }
    /**Vista de cortes del módulo de deterioro de cartera */
    public function deterioroCortes(){
        return view('deterioro.cortes');
    }
    /**Vista resumen del corte: matriz producto x rango */
    public function deterioroResumen(){
        return view('deterioro.resumen-corte');
    }
    /**Vista detalle por operación con descenso a las cuotas */
    public function deterioroDetalleOperaciones(){
        return view('deterioro.detalle-operaciones');
    }
    /**Vista perfil de usuario */
    public function perfilUsuario(){
        $user = Admin::perfilUsuario();
        if($user[0]->nombreImagen != NULL && $user[0]->nombreImagen != ''){
            $rutaCarpeta = '/app/public/images-perfiles';
            $imagen = Storage::disk('sftp')->get($rutaCarpeta.'/'.$user[0]->nombreImagen);
        }else{
            $imagen = '';
        }
        return view('administracion.usuario.perfil-usuario',compact('user','imagen'));
    }
}
