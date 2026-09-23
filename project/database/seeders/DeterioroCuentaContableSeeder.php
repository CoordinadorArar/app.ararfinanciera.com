<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Semilla de las cuentas contables del asiento (§17).
 *
 * ESTÁ VACÍA A PROPÓSITO. §17 dice que el gasto va «contra la cuenta que defina
 * Contabilidad», y esa definición todavía no existe. Inventar un PUC sería peor
 * que no tener ninguno: un asiento con cuentas equivocadas que alguien suba a
 * contabilidad es más dañino que un error visible. Mientras `cuentas()` devuelva
 * un arreglo vacío, el exportable del asiento se rechaza enumerando los
 * conceptos sin cuenta, y los otros tres exportables no dependen de esto.
 *
 * El día que las cuentas lleguen, se agregan aquí —o se insertan por la
 * paramétrica— y el módulo empieza a generar el asiento sin desplegar código.
 *
 * Cada fila lleva las DOS cuentas del movimiento porque el asiento cambia de
 * forma según el signo: el aumento debita gasto y acredita la 1399; la
 * liberación debita la 1399 y acredita una recuperación, que normalmente es de
 * ingreso y no la misma cuenta de gasto.
 *
 * `producto` es opcional: una fila con producto resuelve sólo ese producto y una
 * fila con producto nulo es la general. El asiento busca primero
 * (concepto, producto) y luego (concepto, null).
 *
 * Los conceptos válidos son las llaves de Deterioro::CONCEPTOS_ASIENTO.
 * La vigencia arranca antes de los cortes existentes, igual que las demás
 * paramétricas del módulo. Es idempotente por (concepto, producto).
 *
 * CUIDADO: usa la conexión por defecto, que apunta a la base de PRODUCCIÓN
 * incluso corriendo desde la máquina de desarrollo. Para probarlo contra
 * ArarFinanciera_PRUEBAS hay que llamar antes a Ambiente::aplicar('demo') y
 * envolverlo en una transacción que se revierta.
 */
class DeterioroCuentaContableSeeder extends Seeder
{
    const VIGENTE_DESDE = '2016-01-01';

    /**
     * Estructura de una fila, para cuando Contabilidad defina las cuentas:
     *
     * ['concepto' => 'AUMENTO_DETERIORO', 'producto' => null,
     *  'cuenta_debito' => '', 'cuenta_credito' => '', 'descripcion' => '']
     */
    public static function cuentas()
    {
        return [];
    }

    public function run()
    {
        $comunes = [
            'vigente_desde' => self::VIGENTE_DESDE,
            'vigente_hasta' => null,
            'id_usuario' => 0,
            'fecha_registro' => date('Y-m-d\TH:i:s'),
        ];

        foreach (self::cuentas() as $fila) {
            $existe = DB::table('det_param_cuenta_contable')
                ->where('concepto', $fila['concepto']);
            $existe = $fila['producto'] === null
                ? $existe->whereNull('producto')
                : $existe->where('producto', $fila['producto']);

            if (!$existe->exists()) {
                DB::table('det_param_cuenta_contable')->insert($fila + $comunes);
            }
        }
    }
}
