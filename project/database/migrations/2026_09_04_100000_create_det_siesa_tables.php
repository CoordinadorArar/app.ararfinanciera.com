<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6a · Conexión a SIESA, tope fiscal y conciliación C-3 — esquema.
 *
 * det_corte_saldo_siesa es un snapshot congelado: se guarda con el corte para
 * que la conciliación y el tope fiscal sean reproducibles aunque SIESA siga
 * moviéndose. Guarda también las filas que no cruzan contra ninguna operación
 * del corte, porque la conciliación necesita los dos sentidos: lo que está en
 * SIESA y no en factoring, y lo que está en factoring y no en SIESA.
 *
 * det_conciliacion_partida se regenera en cada ejecución del corte, igual que
 * det_deterioro_operacion. Por eso la explicación del usuario vive en una tabla
 * aparte: limpiarCorte() borra las tablas del corte al reejecutar, y si la
 * explicación viviera junto a la partida se perdería el trabajo del usuario en
 * cada recálculo. La llave de la explicación es (id_corte, numero_operacion) y
 * no un identity de la partida, justamente porque el identity cambia con cada
 * recálculo y la explicación tiene que sobrevivirlo.
 *
 * origen_base registra por operación de dónde salió su base de cálculo. El
 * parámetro siesa_manda_sobre_base de la fase 1 es global por corte y no
 * alcanza: D-15 exige alcance por operación, porque en el mismo corte conviven
 * operaciones con base de factoring y operaciones con base de SIESA. El default
 * FACTORING deja los cortes ya calculados con el valor que les corresponde.
 */
class CreateDetSiesaTables extends Migration
{
    public function up()
    {
        // Snapshot de la cartera abierta de SIESA a la fecha del corte, por
        // documento de cruce y tercero. nit y razon_social se copian con el
        // tamaño que tienen en t200_mm_terceros (varchar 25 y 100).
        Schema::create('det_corte_saldo_siesa', function (Blueprint $table) {
            $table->increments('id_saldo');
            $table->integer('id_corte');
            // Admite nulo por los cortes extraídos antes del 17 de septiembre
            // de 2026: hasta esa fecha el snapshot traía las 11.070 filas de la
            // compañía 7 sin documento de cruce —el lado crédito de la cartera—
            // porque con ellas reproducía el total de SIESA al peso. Desde que
            // Contabilidad definió los cuatro prefijos del saldo
            // (Deterioro::PREFIJOS_SALDO_SIESA) ya no entra ninguna fila con
            // nulo, pero la columna lo sigue admitiendo: los cortes cerrados
            // conservan su snapshot de entonces y no se recalculan.
            $table->char('tipo_docto_cruce', 3)->nullable();
            $table->integer('consec_docto_cruce');
            $table->string('nit', 25)->nullable();
            $table->string('razon_social', 100)->nullable();
            $table->decimal('saldo', 19, 4);
            $table->dateTime('fecha_extraccion');
            $table->index('id_corte', 'ix_det_saldo_siesa_corte');
            $table->index(['id_corte', 'tipo_docto_cruce', 'consec_docto_cruce'], 'ix_det_saldo_siesa_cruce');
        });

        // Partidas de C-3. Se regeneran en cada ejecución del corte.
        // id_operacion queda nulo en las partidas SOLO_SIESA, que por
        // definición no tienen operación en el corte.
        Schema::create('det_conciliacion_partida', function (Blueprint $table) {
            $table->increments('id_partida');
            $table->integer('id_corte');
            $table->integer('id_operacion')->nullable();
            $table->integer('numero_operacion');
            $table->string('nit', 25)->nullable();
            $table->string('cliente', 255)->nullable();
            $table->decimal('saldo_siesa', 19, 4)->nullable();
            $table->decimal('saldo_factoring', 19, 4)->nullable();
            $table->decimal('diferencia', 19, 4);
            // SOLO_SIESA | SOLO_FACTORING | DIFERENCIA
            $table->string('tipo', 20);
            $table->index('id_corte', 'ix_det_concilia_corte');
            $table->index(['id_corte', 'numero_operacion'], 'ix_det_concilia_operacion');
        });

        // La explicación del usuario. Fuera de la tabla de partidas a
        // propósito: sobrevive al recálculo del corte.
        Schema::create('det_conciliacion_explicacion', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->integer('numero_operacion');
            $table->string('estado', 20);
            $table->string('explicacion', 500);
            $table->integer('id_usuario');
            $table->dateTime('fecha');
            $table->primary(['id_corte', 'numero_operacion'], 'pk_det_concilia_explicacion');
        });

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->string('origen_base', 10)->default('FACTORING');
        });
    }

    public function down()
    {
        // Los cuatro controles de esta fase se borran para que no queden
        // huérfanos listándose en pantalla, igual que hicieron las fases 2 y 4
        // con los suyos. C-MARCAS no depende de ninguna columna nueva, pero lo
        // emite el motor de esta fase: sin ella, un corte ya calculado seguiría
        // mostrando un control que el código revertido no vuelve a producir.
        DB::table('det_corte_cuadre')
            ->whereIn('codigo', ['C-SIESA-BASE', 'C-SIESA-EXTRAC', 'C-CONCILIA', 'C-MARCAS'])->delete();

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->dropColumn('origen_base');
        });

        Schema::dropIfExists('det_conciliacion_explicacion');
        Schema::dropIfExists('det_conciliacion_partida');
        Schema::dropIfExists('det_corte_saldo_siesa');
    }
}
