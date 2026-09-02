<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 · Comparativo contable contra fiscal e impuesto diferido (§11).
 *
 * No crea tablas: el comparativo es una derivación por operación del contable
 * ya calculado y del acumulado fiscal de la fase 2, así que vive en cuatro
 * columnas de det_deterioro_operacion.
 *
 * La tarifa que multiplica la diferencia temporaria es
 * det_param_convencion.tarifa_renta (art. 240), no el pct_anual del 33 % de
 * det_param_fiscal, que es la provisión deducible del art. 145 ET.
 *
 * det_corte_cuadre.codigo ya quedó en nvarchar(20) con la fase 2, de modo que
 * C-DIF-TEMP, C-DIFERIDO y C-REVERSION caben sin tocar la llave primaria: aquí
 * no hay que repetir el bloque de DROP CONSTRAINT / ALTER COLUMN.
 */
class CreateDetDiferidoTables extends Migration
{
    public function up()
    {
        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->decimal('deterioro_fiscal_acumulado', 19, 4)->nullable();
            $table->decimal('diferencia_temporaria', 19, 4)->nullable();
            $table->decimal('impuesto_diferido_activo', 19, 4)->nullable();
            // Año gravable proyectado en que la operación completa el 100 %
            // fiscal. Nulo cuando no hay base o el método no deduce.
            $table->smallInteger('ano_reversion_fiscal')->nullable();
        });
    }

    public function down()
    {
        DB::table('det_corte_cuadre')
            ->whereIn('codigo', ['C-DIF-TEMP', 'C-DIFERIDO', 'C-REVERSION'])->delete();

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->dropColumn([
                'deterioro_fiscal_acumulado', 'diferencia_temporaria',
                'impuesto_diferido_activo', 'ano_reversion_fiscal',
            ]);
        });
    }
}
