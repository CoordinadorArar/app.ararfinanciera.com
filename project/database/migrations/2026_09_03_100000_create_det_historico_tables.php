<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 · Histórico y descomposición del movimiento del mes (RN-10, §10 paso 10).
 *
 * T9 del libro —el deterioro del mes anterior por rango— se digita a mano y T10
 * resta para obtener el gasto contable del período. Aquí T9 se calcula por
 * operación contra el corte anterior y T10 sale de la resta, sin teclear.
 *
 * No crea tablas: el comparativo es una derivación por operación del deterioro
 * contable ya calculado en los dos cortes, así que vive en tres columnas de
 * det_deterioro_operacion.
 *
 * Las bajas —operaciones del corte anterior ausentes en el actual— se detectan
 * por consulta y no se persisten: la tabla salida_operacion, la clasificación de
 * la baja y el control C-2 son de la fase 7.
 *
 * det_corte_cuadre.codigo quedó en nvarchar(20) con la fase 2, de modo que
 * C-MOVIMIENTO y C-VARIACION caben sin tocar la llave primaria.
 */
class CreateDetHistoricoTables extends Migration
{
    public function up()
    {
        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->decimal('deterioro_mes_anterior', 19, 4)->nullable();
            $table->decimal('variacion_deterioro', 19, 4)->nullable();
            // ALTA cuando la operación no estaba en el corte anterior,
            // VARIACION cuando estaba en ambos. Las bajas no tienen fila en el
            // corte actual y por eso no aparecen aquí.
            $table->string('movimiento', 12)->nullable();
        });

        Schema::table('det_corte_cuadre', function (Blueprint $table) {
            $table->string('motivo', 200)->nullable();
            $table->boolean('informativo')->default(false);
        });
    }

    public function down()
    {
        DB::table('det_corte_cuadre')
            ->whereIn('codigo', ['C-MOVIMIENTO', 'C-VARIACION'])->delete();

        Schema::table('det_corte_cuadre', function (Blueprint $table) {
            $table->dropColumn(['motivo', 'informativo']);
        });

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->dropColumn([
                'deterioro_mes_anterior', 'variacion_deterioro', 'movimiento',
            ]);
        });
    }
}
