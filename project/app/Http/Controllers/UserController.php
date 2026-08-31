<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use App\Models\User;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Session;

class UserController extends Controller
{
    public function obtenerRol(){
        $userData = User::obtenerRol(auth()->id());
        return response()->json(compact('userData'));
    }

    public function validarInfoSesionUsuario(){
        $datosUsuario = Admin::perfilUsuario();
        if($datosUsuario[0]->estadoUsuario == 0){
            return response()->json(['res'=>'inactivo']);
        }else{
            return response()->json(['res'=>'activo']);
        }
    }

    public function logout(){
        Auth::logout();
        Session::flush();
        return redirect('/login');
    }
}
