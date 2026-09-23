<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semilla de las causales de salida de la base entre cortes (C-2, D-09).
 *
 * Son las cuatro que nombra §9 para `salida_operacion`. La lista es paramétrica
 * y no una constante: puede crecer sin desplegar código, igual que las causales
 * de suspensión.
 *
 * cierra_fiscal distingue el castigo del resto: la operación castigada tiene
 * tratamiento fiscal propio y su deterioro acumulado se cierra en el mismo
 * movimiento (§15, C-2). En un recaudo total no hay nada que cerrar.
 *
 * pide_referencia sólo lo lleva el cierre con apertura, y la referencia que se
 * captura es informativa: D-07 dice que la operación nueva no se vincula con la
 * cerrada, así que ningún cruce se construye sobre ella.
 *
 * La vigencia arranca antes de los cortes existentes, igual que las demás
 * paramétricas del módulo. Es idempotente por codigo.
 *
 * CUIDADO: usa la conexión por defecto, que apunta a la base de PRODUCCIÓN
 * incluso corriendo desde la máquina de desarrollo. Para probarlo contra
 * ArarFinanciera_PRUEBAS hay que llamar antes a Ambiente::aplicar('demo') y
 * envolverlo en una transacción que se revierta.
 */
class DeterioroCausalSalidaSeeder extends Seeder
{
    const VIGENTE_DESDE = '2016-01-01';

    public static function causales()
    {
        return [
            ['codigo' => 'RECAUDO_TOTAL', 'descripcion' => 'Recaudo total de la operación',
                'activa' => 1, 'cierra_fiscal' => 0, 'pide_referencia' => 0, 'orden' => 1],
            ['codigo' => 'CASTIGO', 'descripcion' => 'Castigo de cartera',
                'activa' => 1, 'cierra_fiscal' => 1, 'pide_referencia' => 0, 'orden' => 2],
            ['codigo' => 'CIERRE_Y_APERTURA', 'descripcion' => 'Cierre con apertura de una operación nueva',
                'activa' => 1, 'cierra_fiscal' => 0, 'pide_referencia' => 1, 'orden' => 3],
            ['codigo' => 'OTRA', 'descripcion' => 'Otra causa, explicada en la observación',
                'activa' => 1, 'cierra_fiscal' => 0, 'pide_referencia' => 0, 'orden' => 4],
        ];
    }

    public function run()
    {
        $comunes = [
            'vigente_desde' => self::VIGENTE_DESDE,
            'vigente_hasta' => null,
            'id_usuario' => 0,
            'fecha_registro' => date('Y-m-d\TH:i:s'),
        ];

        foreach (self::causales() as $fila) {
            if (!DB::table('det_param_causal_salida')->where('codigo', $fila['codigo'])->exists()) {
                DB::table('det_param_causal_salida')->insert($fila + $comunes);
            }
        }
    }
}
