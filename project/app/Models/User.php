<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\PHPMailerController;
use Laravel\Sanctum\HasApiTokens;

//JWT Subject
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombreUsuario',
        'email',
        'password',
        'documentoUsuario',
        'estadoUsuario',
        'created_at',
        'updated_at',
        'remember_token',
        'failed_logins'
    ];
    protected $connection = 'identidad';
    protected $primaryKey = 'idUsuario';
    public $timestamps = false;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    // protected $casts = [
    //     'email_verified_at' => 'datetime',
    // ];
    /**
     * Devuelve una matriz de valores clave que contiene cualquier reclamo personalizado que se agregará al JWT.
     *
     * @return array
     */
    public function getJWTIdentifier(){
        return $this->getKey();
    }
    /**
     * Devuelva una matriz de valor clave que contenga cualquier notificación personalizada que se agregará al JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims(){
        return [];
    }
    public function sendPasswordResetNotification($token){
        $cuerpo = view('emails.restablecer-contrasena', [
            'nombre' => $this->nombreUsuario,
            'url' => url(route('password.reset', ['token' => $token, 'email' => $this->email], false)),
            'minutos' => config('auth.passwords.users.expire')
        ])->render();
        if(!PHPMailerController::crearEmail(['asunto'=>'Restablecer contraseña - Arar Financiera','destinatario'=>$this->email,'cuerpo'=>$cuerpo,'copiaA'=>'','emailBcc'=>''])){
            throw ValidationException::withMessages(['email' => 'No fue posible enviar el correo en este momento. Intenta de nuevo más tarde.']);
        }
    }
    public static function obtenerRol($idUser){
        $sql = "SELECT * FROM rolusuario WHERE idUsuario=?";
        $rol = DB::connection('identidad')->select($sql, [$idUser]);
        return $rol;
    }
}
