<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semilla de las causales de suspensión de causación de intereses (D-05).
 *
 * Las causales previstas por la norma son tres —fallecimiento, insolvencia y
 * cobro jurídico—: no hay umbral automático por días de mora, la suspensión
 * siempre se marca por evento. A ellas se suma FIN_CUOTAS, que no es un evento
 * del deudor sino de la operación: al agotar las cuotas disponibles, factoring
 * deja de generar la facturación automática de intereses. SIN_DETERMINAR
 * recibe las marcas del cargue inicial (D-14) pendientes de clasificar y se
 * siembra inactiva: solo resuelve su descripción en las consultas, no debe
 * ofrecerse como opción de marcación manual.
 * La vigencia arranca antes de los cortes existentes, igual que las demás
 * paramétricas del módulo: la condición pudo ocurrir en cualquier momento.
 *
 * Es idempotente por codigo: si la causal ya existe (vigente o no), no la
 * vuelve a insertar.
 */
class DeterioroCausalSuspensionSeeder extends Seeder
{
    const VIGENTE_DESDE = '2016-01-01';

    public static function causales()
    {
        return [
            ['codigo' => 'FALLECIMIENTO', 'descripcion' => 'Fallecimiento del deudor sin que la aseguradora pague', 'activa' => 1],
            ['codigo' => 'INSOLVENCIA', 'descripcion' => 'Admisión del deudor a un proceso de insolvencia', 'activa' => 1],
            ['codigo' => 'COBRO_JURIDICO', 'descripcion' => 'Paso de la operación a cobro jurídico', 'activa' => 1],
            ['codigo' => 'FIN_CUOTAS', 'descripcion' => 'Finalización de las cuotas disponibles: deja de generar facturación automática', 'activa' => 1],
            ['codigo' => 'SIN_DETERMINAR', 'descripcion' => 'Proviene del cargue inicial (D-14) y está pendiente de clasificar', 'activa' => 0],
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
            if (!DB::table('det_param_causal_suspension')->where('codigo', $fila['codigo'])->exists()) {
                DB::table('det_param_causal_suspension')->insert($fila + $comunes);
            }
        }
    }
}
