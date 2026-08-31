<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El corte y su snapshot inmutable.
 *
 * Cerrado un mes, sus datos y resultados no cambian aunque después se corrija
 * la base transaccional. Por eso el detalle de cuotas se copia entero y las
 * paramétricas vigentes se congelan en tablas propias del corte: el motor
 * siempre lee de la copia congelada, nunca de las det_param_*.
 *
 * Los importes van en decimal(19,4) porque el origen los trae en money, que
 * tiene cuatro decimales. Nunca float: el criterio de aceptación es un peso.
 */
class CreateDetCorteTables extends Migration
{
    public function up()
    {
        Schema::create('det_corte', function (Blueprint $table) {
            $table->increments('id_corte');
            $table->date('fecha_corte');
            $table->date('fecha_comparacion')->nullable();
            $table->string('estado', 12);
            $table->integer('id_corte_anterior')->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha_creacion');
            $table->dateTime('fecha_ejecucion')->nullable();
            $table->integer('duracion_ms')->nullable();
            $table->integer('filas_origen')->nullable();
            $table->decimal('suma_capital_origen', 19, 4)->nullable();
            $table->decimal('suma_interes_origen', 19, 4)->nullable();
            $table->char('hash_datos', 64)->nullable();
            $table->char('hash_parametros', 64)->nullable();
            // Permite recorrer el mismo corte replicando los defectos D-A, D-B y
            // el hallazgo 7 del libro, para aislar en dos corridas qué diferencia
            // viene de una corrección deliberada y cuál de un error propio.
            $table->boolean('modo_compatibilidad_excel')->default(false);
            $table->string('observacion', 500)->nullable();
            $table->unique('fecha_corte', 'ux_det_corte_fecha');
        });

        // --- Copia congelada de las paramétricas vigentes a la fecha de corte ---

        Schema::create('det_corte_param_rango_mora', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->char('codigo', 1);
            $table->integer('dias_desde');
            $table->integer('dias_hasta');
            $table->string('etiqueta', 40);
            $table->decimal('pct_deterioro_contable', 7, 4);
            $table->unsignedTinyInteger('orden');
            $table->primary(['id_corte', 'codigo'], 'pk_det_corte_param_rango');
        });

        Schema::create('det_corte_param_producto', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->string('nom_operacion', 100);
            $table->string('producto', 20);
            $table->primary(['id_corte', 'nom_operacion'], 'pk_det_corte_param_producto');
        });

        Schema::create('det_corte_param_interes', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->string('producto', 20);
            $table->decimal('tasa_mora_mensual', 9, 6);
            $table->boolean('aplica_mora');
            $table->boolean('sigue_calculando_suspendido');
            $table->primary(['id_corte', 'producto'], 'pk_det_corte_param_interes');
        });

        Schema::create('det_corte_param_convencion', function (Blueprint $table) {
            $table->integer('id_corte')->primary();
            $table->smallInteger('base_dias');
            $table->string('origen_mora', 30);
            $table->boolean('base_incluye_interes');
            $table->boolean('siesa_manda_sobre_base');
            $table->decimal('tarifa_renta', 7, 4);
        });

        // --- Snapshot del origen, grano operación-cuota ---
        // El grano único verificado sobre el corte de julio de 2026 es
        // (IdOperacion, IdCuota); IdDetalleOperacion no aporta unicidad y se
        // conserva solo como dato de origen.
        Schema::create('det_corte_detalle_cuota', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->integer('id_operacion');
            $table->integer('id_cuota');

            // Campos tal como llegan del origen
            $table->integer('id_detalle_operacion')->nullable();
            $table->string('id_ano', 4)->nullable();
            $table->string('id_periodo', 2)->nullable();
            $table->string('id_cliente', 20)->nullable();
            $table->string('cliente', 255)->nullable();
            $table->string('id_pagador', 20)->nullable();
            $table->string('pagador', 255)->nullable();
            $table->string('id_comisionista', 20)->nullable();
            $table->string('comisionista', 255)->nullable();
            $table->date('fec_operacion')->nullable();
            $table->decimal('tasa_interes_cliente', 12, 6)->nullable();
            $table->decimal('saldo_capital', 19, 4)->nullable();
            $table->decimal('saldo_intereses', 19, 4)->nullable();
            $table->decimal('saldo_intereses_causado', 19, 4)->nullable();
            $table->decimal('saldo_mora', 19, 4)->nullable();
            $table->decimal('saldo_mora_causado', 19, 4)->nullable();
            $table->decimal('saldo_admon', 19, 4)->nullable();
            $table->decimal('saldo_neto_recibir', 19, 4)->nullable();
            $table->date('fec_inicial_corriente')->nullable();
            $table->date('fec_final_corriente')->nullable();
            $table->date('fec_inicial_mora')->nullable();
            $table->integer('dias_vencidos')->nullable();
            $table->integer('dias_corriente')->nullable();
            $table->decimal('reliquida_mora', 19, 4)->nullable();
            $table->integer('tipo_operacion')->nullable();
            $table->string('nom_operacion', 255)->nullable();
            $table->string('nom_mod_operacion', 255)->nullable();
            $table->decimal('valor_credito', 19, 4)->nullable();

            // Derivadas que calcula el motor
            $table->string('producto', 20)->nullable();
            $table->integer('dias_mora_cuota')->nullable();
            $table->integer('dias_mora_operacion')->nullable();
            $table->string('calificacion_abc', 12)->nullable();
            $table->char('rango_codigo', 1)->nullable();
            $table->decimal('capital_corriente', 19, 4)->nullable();
            $table->decimal('interes_corriente', 19, 4)->nullable();
            $table->decimal('capital_vencido', 19, 4)->nullable();
            $table->decimal('interes_vencido', 19, 4)->nullable();
            $table->decimal('interes_mora', 19, 4)->nullable();
            $table->string('estado_cuota', 10)->nullable();
            $table->decimal('capital_mes_anterior', 19, 4)->nullable();

            $table->primary(['id_corte', 'id_operacion', 'id_cuota'], 'pk_det_corte_detalle_cuota');
            $table->index(['id_corte', 'id_cliente'], 'ix_det_detalle_cliente');
        });
    }

    public function down()
    {
        Schema::dropIfExists('det_corte_detalle_cuota');
        Schema::dropIfExists('det_corte_param_convencion');
        Schema::dropIfExists('det_corte_param_interes');
        Schema::dropIfExists('det_corte_param_producto');
        Schema::dropIfExists('det_corte_param_rango_mora');
        Schema::dropIfExists('det_corte');
    }
}
