<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuotas repetidas del origen · marca sobre el detalle.
 *
 * `ResumenVigentesClientes` entrega cuotas que son duplicados exactos de otras
 * de la misma operación: mismas fechas y mismos saldos, y sólo difieren en
 * id_cuota e id_detalle_operacion. La extracción sigue copiándolas fila por
 * fila —el detalle es la prueba de qué entregó factoring y no se depura—, y
 * esta columna es la que las separa del cálculo: nula significa que la cuota
 * cuenta, y con valor guarda el id_cuota de la que sí cuenta de su grupo.
 *
 * Guarda el id del superviviente y no una bandera porque la pantalla tiene que
 * poder decir de cuál es copia cada fila; un 1 obligaría a rehacer el
 * emparejamiento para explicarlo.
 *
 * No lleva índice único sobre la tupla de negocio: un duplicado exacto puede
 * ser legítimo en FACTORING y una restricción abortaría el corte entero.
 *
 * Lleva guarda hasTable/hasColumn, como la de los soportes de suspensión: la
 * divergencia entre bases ya nos costó una vez.
 */
class AddDuplicadaDeToDetCorteDetalleCuota extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('det_corte_detalle_cuota')
            || Schema::hasColumn('det_corte_detalle_cuota', 'duplicada_de')) {
            return;
        }

        Schema::table('det_corte_detalle_cuota', function (Blueprint $table) {
            $table->integer('duplicada_de')->nullable();
        });
    }

    public function down()
    {
        if (!Schema::hasTable('det_corte_detalle_cuota')
            || !Schema::hasColumn('det_corte_detalle_cuota', 'duplicada_de')) {
            return;
        }

        // Los cortes ya calculados quedan sin la marca y sus cuadres
        // C-DUPLICADAS y C-DUPLICADAS-BASE sin respaldo: hay que recalcularlos.
        Schema::table('det_corte_detalle_cuota', function (Blueprint $table) {
            $table->dropColumn('duplicada_de');
        });
    }
}
