<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 · Cálculo fiscal: paramétricas, columnas de resultado y acumulado por
 * año gravable.
 *
 * Igual que en la fase 1, ninguna cifra vive en el código: el porcentaje anual,
 * los días mínimos de mora y los porcentajes del método general se resuelven
 * por vigencia y se congelan dentro del corte.
 *
 * det_fiscal_acumulado no se congela por corte: es el histórico de lo ya
 * deducido en años gravables anteriores y sustituye a las hojas `1399 AÑO XXXX`.
 */
class CreateDetFiscalTables extends Migration
{
    public function up()
    {
        // Método fiscal (RN-07, RN-08, D-04). dias_minimos_mora expresa por días
        // la condición "clasificación E o F" del libro: E arranca en 361.
        Schema::create('det_param_fiscal', function (Blueprint $table) {
            $table->increments('id_param');
            $table->string('metodo', 12);
            $table->decimal('pct_anual', 7, 4)->nullable();
            $table->integer('dias_minimos_mora');
            $table->boolean('activo');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');
            $table->index(['vigente_desde', 'vigente_hasta'], 'ix_param_fiscal_vigencia');
        });

        // Porcentajes por rango del método general (RN-08): 5 %, 10 % y 15 %.
        Schema::create('det_param_fiscal_rango', function (Blueprint $table) {
            $table->increments('id_param');
            $table->string('metodo', 12);
            $table->char('rango_codigo', 1);
            $table->decimal('pct', 7, 4);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');
            $table->index(['vigente_desde', 'vigente_hasta'], 'ix_param_fiscal_rango_vigencia');
        });

        // --- Copia congelada dentro del corte ---

        Schema::create('det_corte_param_fiscal', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->string('metodo', 12);
            $table->decimal('pct_anual', 7, 4)->nullable();
            $table->integer('dias_minimos_mora');
            $table->boolean('activo');
            $table->primary(['id_corte', 'metodo'], 'pk_det_corte_param_fiscal');
        });

        Schema::create('det_corte_param_fiscal_rango', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->string('metodo', 12);
            $table->char('rango_codigo', 1);
            $table->decimal('pct', 7, 4);
            $table->primary(['id_corte', 'metodo', 'rango_codigo'], 'pk_det_corte_param_fiscal_rango');
        });

        // --- Resultado fiscal por operación (RN-07, RN-08, RN-09) ---
        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            // Q del libro. La puebla la fase 6 desde SIESA; hoy queda nula y el
            // tope se resuelve contra la base.
            $table->decimal('saldo_siesa', 19, 4)->nullable();
            $table->decimal('saldo_topado', 19, 4)->nullable();
            $table->decimal('fiscal_acumulado_anterior', 19, 4)->nullable();
            $table->decimal('deterioro_fiscal_individual', 19, 4)->nullable();
            $table->decimal('deterioro_fiscal_general', 19, 4)->nullable();
            $table->decimal('deduccion_fiscal_ano', 19, 4)->nullable();
        });

        // Acumulado deducido por operación y año gravable. Reemplaza la hoja
        // `1399 AÑO 2025` y es el insumo de P en RN-09.
        Schema::create('det_fiscal_acumulado', function (Blueprint $table) {
            $table->integer('id_operacion');
            $table->smallInteger('ano_gravable');
            $table->decimal('valor_deducido', 19, 4);
            $table->integer('id_corte_origen')->nullable();
            $table->string('origen', 20);
            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');
            $table->primary(['id_operacion', 'ano_gravable'], 'pk_det_fiscal_acumulado');
        });

        // Los códigos de los cuadres fiscales de RN-12 (C-FISCAL-ACUM,
        // C-FISCAL-TOPE) no caben en los 10 caracteres de la fase 1. La columna
        // es parte de la llave primaria, así que se recrea la restricción.
        DB::statement('ALTER TABLE det_corte_cuadre DROP CONSTRAINT pk_det_corte_cuadre');
        DB::statement('ALTER TABLE det_corte_cuadre ALTER COLUMN codigo nvarchar(20) NOT NULL');
        DB::statement('ALTER TABLE det_corte_cuadre ADD CONSTRAINT pk_det_corte_cuadre PRIMARY KEY (id_corte, codigo)');
    }

    public function down()
    {
        // También C-FISCAL: es un control de esta fase y sin sus columnas
        // quedaría huérfano listándose en pantalla tras el rollback.
        DB::table('det_corte_cuadre')
            ->whereIn('codigo', ['C-FISCAL', 'C-FISCAL-ACUM', 'C-FISCAL-TOPE'])->delete();
        DB::statement('ALTER TABLE det_corte_cuadre DROP CONSTRAINT pk_det_corte_cuadre');
        DB::statement('ALTER TABLE det_corte_cuadre ALTER COLUMN codigo nvarchar(10) NOT NULL');
        DB::statement('ALTER TABLE det_corte_cuadre ADD CONSTRAINT pk_det_corte_cuadre PRIMARY KEY (id_corte, codigo)');

        Schema::dropIfExists('det_fiscal_acumulado');

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->dropColumn([
                'saldo_siesa', 'saldo_topado', 'fiscal_acumulado_anterior',
                'deterioro_fiscal_individual', 'deterioro_fiscal_general', 'deduccion_fiscal_ano',
            ]);
        });

        Schema::dropIfExists('det_corte_param_fiscal_rango');
        Schema::dropIfExists('det_corte_param_fiscal');
        Schema::dropIfExists('det_param_fiscal_rango');
        Schema::dropIfExists('det_param_fiscal');
    }
}
