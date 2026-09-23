<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7a · Cierre y reapertura de corte, controles C-1 y C-2 — esquema.
 *
 * Las causales de la salida son paramétrica con vigencias y copia congelada por
 * corte, igual que las causales de suspensión de la fase 5: la lista tiene que
 * poder crecer sin desplegar código. Y no sólo la lista: también el tratamiento.
 * cierra_fiscal es lo que dice que esa causal cierra el deterioro fiscal
 * acumulado en el mismo movimiento —hoy sólo el castigo, mañana lo que
 * Contabilidad decida— y pide_referencia lo que dice que la captura pregunta por
 * la operación nueva. Sin esas dos columnas el código volvería a preguntar si el
 * código es 'CASTIGO', que es exactamente la regla escrita en duro que el módulo
 * evita desde la fase 1.
 *
 * det_salida_operacion es la tabla que §9 llama `salida_operacion` y que la
 * fase 4 dejó explícitamente pendiente: bajasDelPeriodo() ya detectaba las
 * operaciones que estaban en el corte anterior y no están en el actual, pero no
 * había dónde escribir por qué se fueron. La llave es (id_corte, id_operacion)
 * porque la baja pertenece al corte en que se detecta: la misma operación puede
 * salir, volver y salir otra vez.
 *
 * Los importes de la salida se congelan al clasificar y no se recalculan. El
 * corte anterior es inmutable, así que hoy darían lo mismo; se guardan porque el
 * castigo cierra el deterioro acumulado en el mismo movimiento (C-2) y esa cifra
 * es el asiento, no una consulta que se rehace. fiscal_acumulado_cerrado sólo se
 * llena cuando la causal cierra fiscal: en un recaudo total no hay deducción que
 * cerrar y un cero ahí significaría lo contrario.
 *
 * referencia_operacion_nueva es informativa y se guarda como texto a propósito.
 * D-07 dice que no se rastrea la antigüedad anterior ni se vincula la operación
 * nueva con la cerrada: guardarla como entero invitaría a unir por ella, que es
 * justo lo que la política prohíbe.
 *
 * Las columnas de cierre viven en det_corte porque el estado del corte es del
 * corte. cerrado_con_salvedad distingue un cierre limpio de uno forzado, y
 * foto_salvedad congela en JSON qué estaba mal al cerrar —qué controles en
 * falla, con qué cifras, cuántas partidas y cuántas bajas—: dentro de un año el
 * texto del motivo solo no permite reconstruir por qué se cerró así, y las
 * cifras ya no se pueden recalcular porque habrán cambiado.
 */
class CreateDetCierreTables extends Migration
{
    public function up()
    {
        // Causales de la salida (C-2, D-09), con vigencia igual que las demás
        // paramétricas del módulo.
        Schema::create('det_param_causal_salida', function (Blueprint $table) {
            $table->increments('id_param');
            $table->string('codigo', 24);
            $table->string('descripcion', 120);
            $table->boolean('activa');
            // El castigo cierra el deterioro fiscal acumulado en el mismo
            // movimiento; el recaudo total no tiene nada que cerrar.
            $table->boolean('cierra_fiscal');
            // La captura pregunta por la operación nueva (cierre con apertura).
            $table->boolean('pide_referencia');
            $table->unsignedTinyInteger('orden');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');
            $table->index(['vigente_desde', 'vigente_hasta'], 'ix_param_causal_salida_vigencia');
        });

        // Copia congelada dentro del corte, mismo patrón que
        // det_corte_param_causal_suspension.
        Schema::create('det_corte_param_causal_salida', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->string('codigo', 24);
            $table->string('descripcion', 120);
            $table->boolean('activa');
            $table->boolean('cierra_fiscal');
            $table->boolean('pide_referencia');
            $table->unsignedTinyInteger('orden');
            $table->primary(['id_corte', 'codigo'], 'pk_det_corte_param_causal_salida');
        });

        // C-2 · Salidas de la base entre cortes (D-09, §15).
        Schema::create('det_salida_operacion', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->integer('id_operacion');
            $table->string('clasificacion', 24);
            $table->string('observacion', 500);
            // Informativa. D-07: no se construye ningún cruce sobre ella.
            $table->string('referencia_operacion_nueva', 30)->nullable();
            // Congelados al clasificar, tomados del corte anterior.
            $table->decimal('base_cerrada', 19, 4);
            $table->decimal('deterioro_cerrado', 19, 4);
            // Nulo cuando la causal no cierra fiscal.
            $table->decimal('fiscal_acumulado_cerrado', 19, 4)->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha');
            $table->primary(['id_corte', 'id_operacion'], 'pk_det_salida_operacion');
        });

        Schema::table('det_corte', function (Blueprint $table) {
            $table->dateTime('fecha_cierre')->nullable();
            $table->integer('id_usuario_cierre')->nullable();
            // Un cierre forzado sobre condiciones que bloquean. Sin esta
            // distinción el módulo mentiría sobre su propio estado.
            $table->boolean('cerrado_con_salvedad')->default(false);
            $table->string('motivo_salvedad', 500)->nullable();
            $table->text('foto_salvedad')->nullable();
            $table->dateTime('fecha_reapertura')->nullable();
            $table->integer('id_usuario_reapertura')->nullable();
            $table->string('motivo_reapertura', 500)->nullable();
        });
    }

    public function down()
    {
        // El acumulado fiscal escrito por el cierre de diciembre se revierte con
        // el esquema: lo produce el código de esta fase y sin ella nadie
        // volvería a escribirlo ni a saber de dónde salió. Se acota por origen
        // para no tocar el 1399 histórico, que entró por el cargue de la fase 2.
        DB::table('det_fiscal_acumulado')->where('origen', 'CIERRE_DICIEMBRE')->delete();

        DB::table('det_corte_cuadre')
            ->whereIn('codigo', ['C-PRORROGA', 'C-SALIDAS', 'C-CIERRE-FISCAL'])->delete();

        Schema::table('det_corte', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_cierre', 'id_usuario_cierre', 'cerrado_con_salvedad',
                'motivo_salvedad', 'foto_salvedad',
                'fecha_reapertura', 'id_usuario_reapertura', 'motivo_reapertura',
            ]);
        });

        Schema::dropIfExists('det_salida_operacion');
        Schema::dropIfExists('det_corte_param_causal_salida');
        Schema::dropIfExists('det_param_causal_salida');
    }
}
