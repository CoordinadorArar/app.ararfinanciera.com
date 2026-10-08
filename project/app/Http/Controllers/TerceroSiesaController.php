<?php

namespace App\Http\Controllers;

use App\Models\Terceros;
use App\Models\User;
use App\Services\Siesa\ClienteSoapSiesa;
use App\Services\Siesa\RegistroClienteSiesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TerceroSiesaController extends Controller
{
    const ID_CLIENTE = ['required', 'regex:/^[A-Za-z0-9-]{1,20}$/'];

    public function siesaClientes(Request $request){
        $validate = Validator::make($request->all(),[
            'busqueda' => 'nullable|string|max:100',
            'estado' => 'nullable|in:pendiente,parcial,completo',
            'page' => 'nullable|integer|min:1',
            'perPage' => 'nullable|integer|min:1|max:100'
        ],[],['busqueda'=>'búsqueda','estado'=>'estado','page'=>'página','perPage'=>'registros por página']);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        $page = (int) ($request->input('page') ?: 1);
        $perPage = (int) ($request->input('perPage') ?: 20);
        $listado = Terceros::listadoClientesSiesa($request->input('busqueda'),$request->input('estado'),$page,$perPage);
        $registros = array_map(function($fila){
            $nitDv = RegistroClienteSiesa::nitDv($fila->IdCliente,$fila->DigitoVerificaCli);
            return [
                'idCliente' => trim($fila->IdCliente),
                'nit' => $nitDv['nit'],
                'dv' => $nitDv['dv'],
                'nombre' => trim(trim((string) $fila->NomCliente).' '.trim((string) $fila->ApeCliente)),
                'fecha' => $fila->FecModifica ? date('Y-m-d',strtotime($fila->FecModifica)) : null,
                'siesa' => ['tercero'=>(bool) $fila->Tercero,'cliente'=>(bool) $fila->Cliente,'proveedor'=>(bool) $fila->Proveedor],
                'estado' => $fila->Estado
            ];
        },$listado['registros']);
        return response()->json([
            'registros' => $registros,
            'total' => $listado['total'],
            'page' => $page,
            'perPage' => $perPage,
            'contadores' => $listado['contadores'],
            'envioHabilitado' => ClienteSoapSiesa::habilitado()
        ]);
    }

    public function siesaValidar(Request $request){
        $validate = $this->validarCliente($request);
        if($validate !== true){
            return $validate;
        }
        [$datos,$pasos] = $this->evaluar($request->input('idCliente'));
        if(!$datos){
            return $this->clienteInexistente();
        }
        return response()->json(['pasos'=>$pasos,'envioHabilitado'=>ClienteSoapSiesa::habilitado()]);
    }

    public function siesaVistaPrevia(Request $request){
        if(!collect(User::obtenerRol(auth()->id()))->contains('IdRol',1)){
            return response()->json(['message'=>'La vista previa de SIESA es solo para el administrador.'],403);
        }
        $validate = $this->validarCliente($request,false);
        if($validate !== true){
            return $validate;
        }
        [$datos,$pasos] = $this->evaluar($request->input('idCliente'));
        if(!$datos){
            return $this->clienteInexistente();
        }
        $resultado = [];
        foreach($pasos as $paso){
            if($request->filled('paso') && $paso['clave'] !== $request->input('paso')){
                continue;
            }
            $lineas = $paso['estado'] === 'omitido' ? [] : RegistroClienteSiesa::lineas($paso['clave'],$datos);
            $resultado[] = $paso + [
                'registros' => $lineas,
                'xml' => $lineas ? RegistroClienteSiesa::xml($lineas,ClienteSoapSiesa::conexion(),true) : null
            ];
        }
        return response()->json(['pasos'=>$resultado,'envioHabilitado'=>ClienteSoapSiesa::habilitado()]);
    }

    public function siesaEjecutarPaso(Request $request){
        $validate = $this->validarCliente($request,true);
        if($validate !== true){
            return $validate;
        }
        if(!ClienteSoapSiesa::habilitado()){
            return response()->json(['message'=>ClienteSoapSiesa::MENSAJE_DESHABILITADO,'errors'=>['envio'=>[ClienteSoapSiesa::MENSAJE_DESHABILITADO]]],422);
        }
        [$datos,$pasos] = $this->evaluar($request->input('idCliente'));
        if(!$datos){
            return $this->clienteInexistente();
        }
        $paso = collect($pasos)->firstWhere('clave',$request->input('paso'));
        if($paso['estado'] !== 'listo'){
            $motivos = $paso['estado'] === 'hecho' ? ['El paso ya existe en SIESA.'] : $paso['motivos'];
            return response()->json(['message'=>'El paso '.$paso['nombre'].' no está listo para enviarse.','errors'=>['paso'=>$motivos]],422);
        }
        $envio = ClienteSoapSiesa::enviar(RegistroClienteSiesa::xml(RegistroClienteSiesa::lineas($paso['clave'],$datos),ClienteSoapSiesa::conexion()));
        return response()->json(['ok'=>$envio['ok'],'paso'=>$paso['clave'],'detalle'=>$envio['detalle'],'erroresSiesa'=>$envio['errores']]);
    }

    private function validarCliente(Request $request,$conPaso = null){
        $reglas = ['idCliente' => self::ID_CLIENTE];
        if($conPaso !== null){
            $reglas['paso'] = [$conPaso ? 'required' : 'nullable',Rule::in(array_keys(RegistroClienteSiesa::PASOS))];
        }
        $validate = Validator::make($request->all(),$reglas,[],['idCliente'=>'cliente','paso'=>'paso']);
        if($validate->fails()){
            return response()->json(['message'=>$validate->errors()->first(),'errors'=>$validate->errors()],422);
        }
        return true;
    }

    private function evaluar($idCliente){
        $cliente = Terceros::LoadClientes((string) $idCliente);
        if(count($cliente) === 0){
            return [null,[]];
        }
        $fila = (array) $cliente[0];
        $nit = RegistroClienteSiesa::nitDv($fila['IdCliente'],$fila['DigitoVerificaCli'])['nit'];
        $cuenta = Terceros::cuentaBancariaCliente($fila['IdCliente'],$nit);
        $datos = RegistroClienteSiesa::datos($fila,$cuenta ? (array) $cuenta[0] : null);
        return [$datos,RegistroClienteSiesa::pasos($datos,Terceros::estadoSiesaCliente($datos['nit']))];
    }

    private function clienteInexistente(){
        $mensaje = 'El cliente no existe en FactoringManager.';
        return response()->json(['message'=>$mensaje,'errors'=>['idCliente'=>[$mensaje]]],422);
    }
}
