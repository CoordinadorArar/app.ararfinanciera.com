<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultados del corte, controles de cuadre, staging de validación y bitácora.
 *
 * Las columnas fiscales de det_deterioro_operacion entran en la fase 2; aquí
 * solo está lo contable.
 */
class CreateDetResultadoTables extends Migration
{
    public function up()
    {
        // Una fila por corte y operación (~2.106 en el corte de julio de 2026).
        Schema::create('det_deterioro_operacion', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->integer('id_operacion');
            $table->string('id_cliente', 20)->nullable();
            $table->string('cliente', 255)->nullable();
            $table->string('producto', 20)->nullable();
            $table->string('nom_operacion', 255)->nullable();
            $table->date('fec_operacion')->nullable();
            $table->date('fec_inicial_mora')->nullable();
            $table->integer('cuotas');

            $table->decimal('capital_corriente', 19, 4);
            $table->decimal('interes_corriente', 19, 4);
            $table->decimal('capital_vencido', 19, 4);
            $table->decimal('interes_vencido', 19, 4);
            $table->decimal('interes_mora', 19, 4);
            $table->decimal('saldo_admon', 19, 4);

            $table->integer('dias_mora_operacion')->nullable();
            $table->string('calificacion_abc', 12)->nullable();
            $table->char('rango_codigo', 1)->nullable();

            // RN-03 / D-03: capital vencido + interés vencido. Excluye
            // administración e interés de mora.
            $table->decimal('base_deterioro', 19, 4);
            $table->decimal('pct_contable', 7, 4)->nullable();
            $table->decimal('deterioro_contable', 19, 4)->nullable();

            $table->decimal('capital_mes_anterior', 19, 4)->nullable();
            $table->decimal('variacion_capital', 19, 4)->nullable();

            $table->primary(['id_corte', 'id_operacion'], 'pk_det_deterioro_operacion');
            $table->index(['id_corte', 'producto', 'rango_codigo'], 'ix_det_deterioro_prod_rango');
        });

        // Controles de cuadre de RN-12. En fase 1 solo se pueblan los que no
        // dependen de SIESA ni del cálculo fiscal.
        Schema::create('det_corte_cuadre', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->string('codigo', 10);
            $table->string('descripcion', 200);
            $table->decimal('valor_detalle', 19, 4)->nullable();
            $table->decimal('valor_resumen', 19, 4)->nullable();
            $table->decimal('diferencia', 19, 4)->nullable();
            $table->decimal('tolerancia', 19, 4)->default(1);
            $table->string('estado', 10);
            $table->primary(['id_corte', 'codigo'], 'pk_det_corte_cuadre');
        });

        // Staging de las hojas del libro para comparar la réplica de forma
        // automatizada, en vez de a ojo contra un archivo de 68 MB.
        Schema::create('det_validacion_excel', function (Blueprint $table) {
            $table->increments('id_validacion');
            $table->integer('id_corte');
            $table->string('hoja', 20);
            $table->integer('id_operacion')->nullable();
            $table->string('columna', 40);
            $table->decimal('valor_excel', 19, 4)->nullable();
            $table->index(['id_corte', 'hoja', 'id_operacion'], 'ix_det_validacion');
        });

        Schema::create('det_bitacora', function (Blueprint $table) {
            $table->bigIncrements('id_evento');
            $table->string('accion', 40);
            $table->integer('id_corte')->nullable();
            $table->integer('id_operacion')->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->integer('id_usuario');
            $table->string('doc_usuario', 20)->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('fecha');
            $table->index(['id_corte', 'fecha'], 'ix_det_bitacora_corte');
        });
    }

    public function down()
    {
        Schema::dropIfExists('det_bitacora');
        Schema::dropIfExists('det_validacion_excel');
        Schema::dropIfExists('det_corte_cuadre');
        Schema::dropIfExists('det_deterioro_operacion');
    }
}
