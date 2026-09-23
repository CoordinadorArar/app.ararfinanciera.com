<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5a · Suspensión de causación de intereses (D-05, D-06) — esquema.
 *
 * La marca de suspensión no es una tabla de corte: vive fuera del snapshot,
 * porque se registra y se levanta en cualquier momento, y el motor la aplica
 * a cada corte según la fecha del evento y la fecha de corte (inmutabilidad
 * del snapshot: levantar hoy una marca no puede cambiar un corte de hace tres
 * meses).
 *
 * interes_vencido y base_deterioro de det_deterioro_operacion NO se tocan: son
 * el insumo de C-INTERES y C-BASE contra el detalle de cuotas de origen. El
 * congelamiento vive en columnas nuevas (interes_vencido_congelado,
 * base_congelada) que el motor consume aparte.
 *
 * marca_factoring_aplicada, valor_anterior_factoring y fecha_escritura están
 * previstos para D-12 (escritura de la marca en el sistema de factoring) y no
 * se usan todavía.
 */
class CreateDetSuspensionTables extends Migration
{
    public function up()
    {
        // Causales de D-05, con vigencia igual que las demás paramétricas.
        Schema::create('det_param_causal_suspension', function (Blueprint $table) {
            $table->increments('id_param');
            $table->string('codigo', 20);
            $table->string('descripcion', 120);
            $table->boolean('activa');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');
            $table->index(['vigente_desde', 'vigente_hasta'], 'ix_param_causal_susp_vigencia');
        });

        // Copia congelada dentro del corte, mismo patrón que
        // det_corte_param_rango_mora.
        Schema::create('det_corte_param_causal_suspension', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->string('codigo', 20);
            $table->string('descripcion', 120);
            $table->boolean('activa');
            $table->primary(['id_corte', 'codigo'], 'pk_det_corte_param_causal_susp');
        });

        // Las marcas. No pertenece al corte: el motor la lee y la aplica según
        // fecha_evento y fecha_reactivacion contra la fecha de cada corte.
        Schema::create('det_suspension_interes', function (Blueprint $table) {
            $table->increments('id_suspension');
            $table->integer('id_operacion');
            $table->string('causal_codigo', 20);
            $table->date('fecha_evento');
            $table->string('observacion', 500);
            $table->string('soporte', 255)->nullable();

            // MANUAL (D-05) o CARGUE_INICIAL (D-14, todavía sin implementar).
            $table->string('origen', 20)->default('MANUAL');
            $table->boolean('existe_en_factoring')->default(true);

            // Valor del interés a la fecha del evento y corte de donde se tomó,
            // para auditarlo. Los puebla la pasada de marcación, no esta migración.
            $table->decimal('interes_congelado', 19, 4)->nullable();
            $table->integer('id_corte_congelado')->nullable();

            // Previstos para D-12, sin uso todavía.
            $table->boolean('marca_factoring_aplicada')->default(false);
            $table->decimal('valor_anterior_factoring', 19, 4)->nullable();
            $table->dateTime('fecha_escritura')->nullable();

            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');

            // Levantamiento.
            $table->dateTime('fecha_reactivacion')->nullable();
            $table->integer('id_usuario_reactivacion')->nullable();
            $table->string('observacion_reactivacion', 500)->nullable();

            $table->index('id_operacion', 'ix_det_suspension_operacion');
        });

        // Una sola marca activa por operación. Laravel no expresa un índice
        // único filtrado, se crea a mano.
        DB::statement(
            'CREATE UNIQUE INDEX ux_det_suspension_operacion_activa
             ON det_suspension_interes (id_operacion)
             WHERE fecha_reactivacion IS NULL'
        );

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->boolean('suspendida')->default(false);
            $table->integer('id_suspension')->nullable();
            // Interés congelado que aplica a este corte (D-06).
            $table->decimal('interes_vencido_congelado', 19, 4)->nullable();
            // capital_vencido + interes_vencido_congelado. Nulo cuando la
            // operación no está suspendida: ese nulo hace que el motor use la
            // base normal.
            $table->decimal('base_congelada', 19, 4)->nullable();
            // Lo que FACTORING siguió calculando internamente y no se facturó.
            $table->decimal('interes_no_facturado', 19, 4)->nullable();
        });
    }

    public function down()
    {
        DB::table('det_corte_cuadre')->where('codigo', 'C-SUSPENSION')->delete();

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->dropColumn([
                'suspendida', 'id_suspension', 'interes_vencido_congelado',
                'base_congelada', 'interes_no_facturado',
            ]);
        });

        DB::statement('DROP INDEX ux_det_suspension_operacion_activa ON det_suspension_interes');
        Schema::dropIfExists('det_suspension_interes');
        Schema::dropIfExists('det_corte_param_causal_suspension');
        Schema::dropIfExists('det_param_causal_suspension');
    }
}
