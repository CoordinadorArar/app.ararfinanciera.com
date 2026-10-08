<?php

namespace App\Providers;

use App\Models\Procesos;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
        View::composer(['layouts.navbar.navbar', 'home'], function ($view) {
            static $datos;
            if ($datos === null) {
                $usuario = auth()->user();
                $nombre = mb_convert_case(mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $usuario->nombreUsuario)), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
                $partes = preg_split('/\s+/u', $nombre, -1, PREG_SPLIT_NO_EMPTY) ?: [''];
                $datos = [
                    'nombre' => $nombre,
                    'primerNombre' => $partes[0],
                    'iniciales' => mb_strtoupper(mb_substr($partes[0], 0, 1).(count($partes) > 1 ? mb_substr(end($partes), 0, 1) : ''), 'UTF-8'),
                    'correo' => $usuario->email,
                    'rol' => Procesos::rolesUsuarios([auth()->id()])[auth()->id()] ?? '',
                ];
            }
            $view->with('shellUsuario', $datos);
        });
    }
}
