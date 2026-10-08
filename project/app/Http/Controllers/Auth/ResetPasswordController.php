<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/login';

    /**
     * Get the password reset validation rules.
     *
     * @return array
     */
    protected function rules()
    {
        return [
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                // Validación personalizada con regex
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).*$/',
            ],
        ];
    }

    /**
     * Get the password reset validation error messages.
     *
     * @return array
     */
    protected function validationErrorMessages()
    {
        return [
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe contener al menos una letra mayúscula, una letra minúscula y un número.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ];
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {
        // Validar la solicitud con las reglas de seguridad
        $customRules = array_merge($this->rules(), [
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[A-Z]/', $value)) {
                        $fail('La contraseña debe contener al menos una letra mayúscula.');
                    }
                    if (!preg_match('/[a-z]/', $value)) {
                        $fail('La contraseña debe contener al menos una letra minúscula.');
                    }
                    if (!preg_match('/[0-9]/', $value)) {
                        $fail('La contraseña debe contener al menos un número.');
                    }
                },
            ],
        ]);

        $request->validate($customRules, $this->validationErrorMessages());

        // Intentar resetear la contraseña
        $response = $this->broker()->reset(
            $this->credentials($request),
            function ($user, $password) {
                $this->resetPassword($user, $password);
            }
        );

        // Retornar la respuesta apropiada
        return $response == Password::PASSWORD_RESET
            ? $this->sendResetResponse($request, $response)
            : $this->sendResetFailedResponse($request, $response);
    }

    /**
     * Reset the given user's password with security validation.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @param  string  $password
     * @return void
     */
    protected function resetPassword($user, $password)
    {
        // Como ya validamos que la contraseña es segura en el método reset,
        // podemos marcar contrasenaSegura como 1 directamente
        $user->forceFill([
            'password' => Hash::make($password),
            'contrasenaSegura' => 1, // Siempre 1 porque ya validamos que es segura
        ])->save();

        // Eliminar tokens de reset de contraseña
        $user->setRememberToken(Str::random(60));

        // Disparar evento de contraseña reseteada
        event(new PasswordReset($user));
    }
}
