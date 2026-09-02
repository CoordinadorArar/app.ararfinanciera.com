<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semilla de las paramétricas del módulo de deterioro.
 *
 * Los valores reproducen las tablas maestras de la hoja `Otros` y los
 * porcentajes de `DETERIORO!V4:AA4` del libro actual. La vigencia arranca en
 * 2016 porque es el corte más antiguo que conserva el archivo.
 *
 * Es idempotente: si la tabla ya tiene filas, no hace nada.
 */
class DeterioroParametrosSeeder extends Seeder
{
    /** Fecha desde la que rigen las políticas heredadas del Excel. */
    const VIGENTE_DESDE = '2016-01-01';

    /**
     * Rangos de mora y porcentaje de deterioro contable.
     *
     * Los límites salen del BUSCARV aproximado sobre `Otros!B` (0, 31, 91, 181,
     * 361, 721), no de la redacción de RN-04, que dice "A: 0 a 31, B: 31 a 90"
     * y se solaparía.
     */
    public static function rangosMora()
    {
        return [
            ['codigo' => 'A', 'dias_desde' => 0,   'dias_hasta' => 30,     'etiqueta' => '0 hasta 30',      'pct_deterioro_contable' => 0.0000, 'orden' => 1],
            ['codigo' => 'B', 'dias_desde' => 31,  'dias_hasta' => 90,     'etiqueta' => '31 hasta 90',     'pct_deterioro_contable' => 0.0800, 'orden' => 2],
            ['codigo' => 'C', 'dias_desde' => 91,  'dias_hasta' => 180,    'etiqueta' => '91 hasta 180',    'pct_deterioro_contable' => 0.2300, 'orden' => 3],
            ['codigo' => 'D', 'dias_desde' => 181, 'dias_hasta' => 360,    'etiqueta' => '181 hasta 360',   'pct_deterioro_contable' => 0.5300, 'orden' => 4],
            ['codigo' => 'E', 'dias_desde' => 361, 'dias_hasta' => 720,    'etiqueta' => '361 hasta 720',   'pct_deterioro_contable' => 0.7800, 'orden' => 5],
            ['codigo' => 'F', 'dias_desde' => 721, 'dias_hasta' => 999999, 'etiqueta' => '721 en adelante', 'pct_deterioro_contable' => 1.0000, 'orden' => 6],
        ];
    }

    /** Mapeo de NomOperacion a producto (Otros!F:G). */
    public static function productos()
    {
        return [
            ['nom_operacion' => 'FINANCIACION',        'producto' => 'FINANCIACION'],
            ['nom_operacion' => 'LETRA DE CAMBIO',     'producto' => 'FACTORING'],
            ['nom_operacion' => 'LIBRANZAS',           'producto' => 'LIBRANZAS'],
            ['nom_operacion' => 'DESCUENTO DE CHEQUES', 'producto' => 'FACTORING'],
            ['nom_operacion' => 'FACTORING',           'producto' => 'FACTORING'],
        ];
    }

    /**
     * Interés de mora por producto (RN-05): 2,33 % mensual sobre capital,
     * prorrateado por día. No aplica a FACTORING.
     *
     * sigue_calculando_suspendido queda en verdadero para FACTORING por D-06;
     * lo usa la fase 5.
     */
    public static function intereses()
    {
        return [
            ['producto' => 'FINANCIACION', 'tasa_mora_mensual' => 0.023300, 'aplica_mora' => 1, 'sigue_calculando_suspendido' => 0],
            ['producto' => 'LIBRANZAS',    'tasa_mora_mensual' => 0.023300, 'aplica_mora' => 1, 'sigue_calculando_suspendido' => 0],
            ['producto' => 'FACTORING',    'tasa_mora_mensual' => 0.023300, 'aplica_mora' => 0, 'sigue_calculando_suspendido' => 1],
        ];
    }

    /**
     * Método fiscal (RN-07, RN-08). Individual del 33 % anual como método
     * adoptado (D-04); el general queda cargado pero desactivado.
     *
     * dias_minimos_mora traduce a días la condición del libro
     * `SI(O(L=E; L=F); ...)`: el rango E arranca en 361 días, de modo que
     * "clasificación E o F" equivale a 361 días o más de mora de la operación.
     */
    public static function fiscal()
    {
        return [
            ['metodo' => 'INDIVIDUAL', 'pct_anual' => 0.3300, 'dias_minimos_mora' => 361, 'activo' => 1],
            ['metodo' => 'GENERAL',    'pct_anual' => null,   'dias_minimos_mora' => 91,  'activo' => 0],
        ];
    }

    /** Porcentajes por rango del método general: 5 % (C), 10 % (D), 15 % (E y F). */
    public static function fiscalRangos()
    {
        return [
            ['metodo' => 'GENERAL', 'rango_codigo' => 'C', 'pct' => 0.0500],
            ['metodo' => 'GENERAL', 'rango_codigo' => 'D', 'pct' => 0.1000],
            ['metodo' => 'GENERAL', 'rango_codigo' => 'E', 'pct' => 0.1500],
            ['metodo' => 'GENERAL', 'rango_codigo' => 'F', 'pct' => 0.1500],
        ];
    }

    /**
     * Convenciones generales.
     *
     * siesa_manda_sobre_base queda desactivado hasta que se resuelva el punto
     * abierto A. tarifa_renta la usa el impuesto diferido de la fase 3.
     */
    public static function convencion()
    {
        return [
            'base_dias' => 360,
            'origen_mora' => 'FEC_INICIAL_MORA',
            'base_incluye_interes' => 1,
            'siesa_manda_sobre_base' => 0,
            'tarifa_renta' => 0.3500,
        ];
    }

    public function run()
    {
        // Formato ISO 8601 con T: el login del servidor usa idioma español, en
        // el que un datetime escrito 'aaaa-mm-dd hh:mm:ss' se interpreta como
        // dd/mm/aaaa y falla. Con la T la lectura es inequívoca.
        $comunes = [
            'vigente_desde' => self::VIGENTE_DESDE,
            'vigente_hasta' => null,
            'id_usuario' => 0,
            'fecha_registro' => date('Y-m-d\TH:i:s'),
        ];

        if (DB::table('det_param_rango_mora')->count() === 0) {
            foreach (self::rangosMora() as $fila) {
                DB::table('det_param_rango_mora')->insert($fila + $comunes);
            }
        }
        if (DB::table('det_param_producto')->count() === 0) {
            foreach (self::productos() as $fila) {
                DB::table('det_param_producto')->insert($fila + $comunes);
            }
        }
        if (DB::table('det_param_interes')->count() === 0) {
            foreach (self::intereses() as $fila) {
                DB::table('det_param_interes')->insert($fila + $comunes);
            }
        }
        if (DB::table('det_param_fiscal')->count() === 0) {
            foreach (self::fiscal() as $fila) {
                DB::table('det_param_fiscal')->insert($fila + $comunes);
            }
        }
        if (DB::table('det_param_fiscal_rango')->count() === 0) {
            foreach (self::fiscalRangos() as $fila) {
                DB::table('det_param_fiscal_rango')->insert($fila + $comunes);
            }
        }
        if (DB::table('det_param_convencion')->count() === 0) {
            DB::table('det_param_convencion')->insert(self::convencion() + $comunes);
        }
    }
}
