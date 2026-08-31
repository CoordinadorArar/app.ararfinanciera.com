<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    //use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**Mostrar vista de formulario de registro */
    public function showRegistrationForm(){
        return view('auth.register');
    }
    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function register(Request $request)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => 'min:6|required_with:confirmPassword|same:confirmPassword',
            'confirmPassword' => 'required|min:6',
            'documento' => ['required', 'numeric', 'min:6']
        ];
        $validate = Validator::make($request->all(),$rules);
        if($validate->fails()){
            return response()->json(['errors'=>$validate->errors()]);
        }
        $create = $this->create($request->all());

        return response()->json(['susccess'=>'Usuario registrado con exito!']);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $hoy = date('Y-m-d H:i:s');
        $fecha = explode(' ',$hoy)[0];
        $hora = explode(' ',$hoy)[1];
        return User::create([
            'nombreUsuario' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'documentoUsuario' => $data['documento'],
            'estadoUsuario' => 0,
            'created_at' => $fecha.'T'.$hora
        ]);
    }
}
