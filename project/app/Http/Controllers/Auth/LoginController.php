<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
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
        $this->middleware('guest')->except('logout');
    }

    public function login(Request $request){
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'Usuario no encontrado']);
        }

        if ($user->estadoUsuario != '1' || $user->estadoUsuario != true) {
            return back()->withErrors(['email' => 'El usuario no está activo']);
        }

        $securePasswordUser = $user->contrasenaSegura;

        $flagSecurePass = true;
        $flagPasswordInDb = Hash::check($request->password, $user->password);

        /**Validacion de contraseña segura, debe tener minimo 8 caracteres, mayusculas, minusculas y numeros */
        if (strlen($request->password) < 8 || !preg_match('/[A-Z]/', $request->password) || !preg_match('/[a-z]/', $request->password) || !preg_match('/[0-9]/', $request->password)) {
            $flagSecurePass = false;
        }

        /**Validando banderas */
        if($flagSecurePass && $flagPasswordInDb && $securePasswordUser == 1){
            //Reinicio de intentos, si ingresa de nuevo, solo se dará cuando se le haya actiado el usuario
            $user->update(['failed_logins' => 0]);

            Auth::login($user);
            $request->session()->regenerate();
            return response()->json(['res' => 'ok','message' => 'Inicio de sesión exitoso']);
        }

        if (!$flagSecurePass && $flagPasswordInDb && $securePasswordUser == 0) {
            //Reinicio de intentos, si ingresa de nuevo, solo se dará cuando se le haya actiado el usuario
            $user->update(['failed_logins' => 0]);

            Auth::login($user);
            $request->session()->regenerate();
            return response()->json([
                'res' => 'error',
                'details' => 'no seguro',
                'message' => 'Por políticas de seguridad es necesario cambiar la contraseña',
                'secure' => false
            ]);
        }

        $currentFailedLogins = $user->failed_logins ?? 0;
        $newFailedLogins = $currentFailedLogins + 1;

        // Verificar si se debe bloquear el usuario
        if ($newFailedLogins >= 3) {
            $user->update([
                'failed_logins' => $newFailedLogins,
                'estadoUsuario' => 0
            ]);
            
            return response()->json([
                'res' => 'error', 
                'details' => 'bloqueado', 
                'message' => 'Usuario bloqueado por múltiples intentos fallidos. Contacte con soporte.'
            ]);
        } else {
            $user->update(['failed_logins' => $newFailedLogins]);
            
            $remainingAttempts = 3 - $newFailedLogins;
            
            if ($flagSecurePass && !$flagPasswordInDb) {
                return response()->json([
                    'res' => 'error', 
                    'details' => 'incorrecta', 
                    'message' => "Contraseña incorrecta. Le quedan {$remainingAttempts} intentos antes del bloqueo."
                ]);
            }

            // Caso genérico: contraseña no segura y no coincide con la base
            return response()->json([
                'res' => 'error', 
                'details' => 'invalido', 
                'message' => "Credenciales inválidas. Le quedan {$remainingAttempts} intentos antes del bloqueo."
            ]);
        }
    }
}
