<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Atribución del saldo de SIESA por las notas del documento.
 *
 * Hasta hoy el saldo de SIESA se enlazaba a la operación únicamente por el
 * consecutivo de un documento de cruce `OPE`. Eso deja fuera a toda la cartera
 * antigua: el consecutivo `OPE` más bajo que existe en la compañía 7 es 4024,
 * de modo que **ninguna operación con número menor puede tener uno**. Su
 * cartera vive en documentos `CC` y `FAT`, cuyo consecutivo es el del documento
 * y no el de la operación; el número de operación viaja en las notas
 * ('OPE 3266 CUOTA 1', 'FACTURA AUTOMATICA CORTE : 31/01/2022 OPE: 3266').
 *
 * Medido en el corte del 31 de agosto de 2026: 170 operaciones de 2.109 se
 * quedaban sin `saldo_siesa`, y **167 de ellas tienen número menor a 4024**. Sin
 * saldo, el tope R de RN-09 cae a la base de factoring y deja de topar, que es
 * lo que llevaba a la operación 3266 —ya deducida al 100 % en 2025— a recibir
 * otra deducción de 2.308.978 y a proyectar reversión en el año en curso.
 *
 * La atribución se guarda en el snapshot y no se resuelve al vuelo por la misma
 * razón por la que el snapshot existe: la nota vive en SIESA, SIESA se mueve, y
 * un corte recalculado tiene que devolver lo mismo. Además el parseo recorre las
 * 503.794 filas de los cuatro prefijos, y así se paga una vez por corte y no una
 * vez por consulta.
 */
class AddAtribucionNotaADetSiesa extends Migration
{
    public function up()
    {
        Schema::table('det_corte_saldo_siesa', function (Blueprint $table) {
            // Operación deducida de la nota. Nula en dos casos distintos y los
            // dos legítimos: en los `OPE`, que cruzan por consecutivo y no se
            // parsean a propósito —para que una operación no reciba el mismo
            // peso por dos caminos—, y en la cartera de SIESA que no es de una
            // operación de factoring (nóminas, deterioros, ajustes).
            $table->integer('id_operacion_nota')->nullable();
            // CAPITAL | INTERES | PRORROGA. Decide qué entra en saldo_siesa,
            // que sigue significando capital, y qué sólo suma al valor nominal.
            // Se dimensiona con holgura y no al ancho del valor más largo: hoy
            // 'PRORROGA' mide exactamente 8 y un cuarto valor no cabría.
            $table->string('componente', 12)->nullable();
            $table->index(['id_corte', 'id_operacion_nota'], 'ix_det_saldo_siesa_nota');
        });

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            // Los cuatro prefijos que Contabilidad definió como saldo de
            // cartera: capital más interés facturado. Es el 100 % del valor
            // nominal contra el que topa el acumulado fiscal de RN-09.
            $table->decimal('valor_nominal_siesa', 19, 4)->nullable();
            // OPE cuando el saldo vino del consecutivo, NOTA cuando vino de la
            // atribución por texto. Nulo si la operación no está en SIESA.
            $table->string('origen_saldo_siesa', 10)->nullable();
            // PROYECTADA | AGOTADA | SIN_PROYECCION. Sin esta columna, el nulo
            // de ano_reversion_fiscal significaría dos cosas incompatibles —que
            // el acumulado ya llegó al 100 % y que no alcanza a deducir nunca— y
            // C-REVERSION no podría vigilar la segunda sin delatar la primera.
            $table->string('estado_reversion', 14)->nullable();
        });
    }

    public function down()
    {
        DB::table('det_corte_cuadre')
            ->whereIn('codigo', ['C-SIESA-DOBLE', 'C-SIESA-NOTA', 'C-FISCAL-NOMINAL'])->delete();

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->dropColumn(['valor_nominal_siesa', 'origen_saldo_siesa', 'estado_reversion']);
        });

        Schema::table('det_corte_saldo_siesa', function (Blueprint $table) {
            $table->dropIndex('ix_det_saldo_siesa_nota');
            $table->dropColumn(['id_operacion_nota', 'componente']);
        });
    }
}
