<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramétricas del módulo de deterioro de cartera.
 *
 * Ninguna regla de negocio vive en el código: rangos, porcentajes, tasas y
 * convenciones se resuelven por la fecha de corte contra estas tablas.
 * Cambiar una política es insertar un registro nuevo y cerrar la vigencia
 * del anterior, no desplegar.
 */
class CreateDetParametrosTables extends Migration
{
    public function up()
    {
        // Rangos de mora y porcentaje de deterioro contable (RN-02, RN-04).
        Schema::create('det_param_rango_mora', function (Blueprint $table) {
            $table->increments('id_param');
            $table->char('codigo', 1);
            $table->integer('dias_desde');
            $table->integer('dias_hasta');
            $table->string('etiqueta', 40);
            $table->decimal('pct_deterioro_contable', 7, 4);
            $table->unsignedTinyInteger('orden');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');
            $table->index(['vigente_desde', 'vigente_hasta'], 'ix_param_rango_vigencia');
        });

        // Mapeo de NomOperacion a producto (Otros!F:G del libro).
        Schema::create('det_param_producto', function (Blueprint $table) {
            $table->increments('id_param');
            $table->string('nom_operacion', 100);
            $table->string('producto', 20);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');
            $table->index(['vigente_desde', 'vigente_hasta'], 'ix_param_producto_vigencia');
        });

        // Interés de mora por producto (RN-05) y comportamiento al suspender (D-06).
        Schema::create('det_param_interes', function (Blueprint $table) {
            $table->increments('id_param');
            $table->string('producto', 20);
            $table->decimal('tasa_mora_mensual', 9, 6);
            $table->boolean('aplica_mora');
            $table->boolean('sigue_calculando_suspendido');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');
            $table->index(['vigente_desde', 'vigente_hasta'], 'ix_param_interes_vigencia');
        });

        // Convenciones generales (D-02, D-03) y parámetros previstos para fases
        // posteriores: siesa_manda_sobre_base es el punto abierto A y
        // tarifa_renta alimenta el impuesto diferido de la fase 3.
        Schema::create('det_param_convencion', function (Blueprint $table) {
            $table->increments('id_param');
            $table->smallInteger('base_dias');
            $table->string('origen_mora', 30);
            $table->boolean('base_incluye_interes');
            $table->boolean('siesa_manda_sobre_base');
            $table->decimal('tarifa_renta', 7, 4);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');
            $table->index(['vigente_desde', 'vigente_hasta'], 'ix_param_convencion_vigencia');
        });
    }

    public function down()
    {
        Schema::dropIfExists('det_param_convencion');
        Schema::dropIfExists('det_param_interes');
        Schema::dropIfExists('det_param_producto');
        Schema::dropIfExists('det_param_rango_mora');
    }
}
