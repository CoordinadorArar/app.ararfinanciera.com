<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Petitions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PetitionsController extends Controller
{
    public function mostrarPagadurias(){
        $pagadurias = Petitions::mostrarPagadurias();
        return response()->json(compact('pagadurias'));
    }

    public function mostrarDepartamentos(){
        $departamentos = Petitions::mostrarDepartamentos();
        return response()->json(compact('departamentos'));
    }

    public function mostrarCiudades(Request $request){
        $municipios = Petitions::mostrarCiudades($request->input('idDepartamento'));
        return response()->json(compact('municipios'));
    }

    public function mostrarTiposDocumentos(){
        $tipos = Petitions::mostrarTiposDocumentos();
        return response()->json(compact('tipos'));
    }

    public function mostrarRoles(){
        $roles = Petitions::mostrarRoles();
        return response()->json(compact('roles'));
    }
}
