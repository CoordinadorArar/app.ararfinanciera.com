<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Ambiente de datos de la sesión: producción o demo.
 *
 * Sólo se conmuta la base de datos de la aplicación y la de cartera (FAICO).
 * La identidad (usuarios, roles, menús, permisos) se lee siempre de producción
 * a través de la conexión 'identidad'.
 */
class Ambiente
{
    const CLAVE = 'ambiente';
    const PRODUCCION = 'produccion';
    const DEMO = 'demo';

    public static function normalizar($ambiente)
    {
        return $ambiente === self::DEMO ? self::DEMO : self::PRODUCCION;
    }

    /** Apunta la conexión por defecto a las bases del ambiente indicado. */
    public static function aplicar($ambiente)
    {
        $ambiente = self::normalizar($ambiente);
        $bases = config('database.ambientes.'.$ambiente);

        config([
            'database.ambiente' => $ambiente,
            'database.faico' => $bases['faico'],
        ]);

        if (config('database.connections.sqlsrv.database') !== $bases['app']) {
            config(['database.connections.sqlsrv.database' => $bases['app']]);
            DB::purge('sqlsrv');
        }

        if (config('database.connections.faico.database') !== $bases['faico']) {
            config(['database.connections.faico.database' => $bases['faico']]);
            DB::purge('faico');
        }

        return $ambiente;
    }

    public static function actual()
    {
        return config('database.ambiente', self::PRODUCCION);
    }

    public static function esDemo()
    {
        return self::actual() === self::DEMO;
    }

    /** Sólo los roles autorizados pueden conmutar de ambiente. */
    public static function puedeConmutar()
    {
        $idUsuario = auth()->id();
        if (!$idUsuario) {
            return false;
        }

        $rol = DB::connection('identidad')->table('RolUsuario')
            ->where('IdUsuario', $idUsuario)
            ->first();

        return $rol && in_array($rol->IdRol, config('database.roles_ambiente', []));
    }
}
