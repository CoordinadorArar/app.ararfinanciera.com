<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7b · Cuentas contables del asiento (§17) — esquema.
 *
 * §17 pide el ajuste del deterioro del período contra la 1399 «con el gasto
 * contra la cuenta que defina Contabilidad», y esa definición todavía no existe.
 * El exportable se construye completo contra esta paramétrica, que se siembra el
 * día que las cuentas lleguen, sin tocar código. El esquema NO trae semillas a
 * propósito: inventar un PUC sería peor que no tener ninguno, y mientras la tabla
 * esté vacía el archivo del asiento se rechaza con la lista de conceptos sin
 * cuenta en vez de salir con cuentas en blanco.
 *
 * La llave conceptual no es "una cuenta de gasto". El asiento del mes cambia de
 * forma según el signo: cuando el deterioro aumenta se debita gasto y se acredita
 * la 1399; cuando disminuye se debita la 1399 y se acredita una recuperación, que
 * normalmente es de ingreso y no la misma cuenta de gasto. Por eso la paramétrica
 * es por CONCEPTO y lleva las dos cuentas del movimiento: ningún lado del asiento
 * queda escrito en el código, ni siquiera la 1399.
 *
 * REGLA DE RESOLUCIÓN. `producto` es opcional: una fila con producto resuelve
 * sólo ese producto y una fila con producto nulo es la general. Al armar el
 * asiento se busca primero (concepto, producto) y, si no existe, (concepto,
 * NULL). Así Contabilidad puede abrir cuentas distintas por producto para el
 * mayor auxiliar sin tener que enumerar los que comparten cuenta. La copia
 * congelada lleva índice único sobre (id_corte, concepto, producto) y no clave
 * primaria porque `producto` es nulable: SQL Server no admite nulos en una PK,
 * y un índice único sí, tratando los nulos como iguales, que es exactamente lo
 * que hace falta —una sola fila general por concepto y corte—.
 *
 * Los conceptos los enumera Deterioro::CONCEPTOS_ASIENTO y no una tabla de
 * catálogo: son el vocabulario del asiento que arma el módulo, no política
 * contable. Lo que es política —qué cuenta lleva cada uno— vive aquí.
 *
 * DEFINICIÓN PENDIENTE que el asiento no supone: el castigo. C-2 dice que el
 * deterioro acumulado de la operación castigada se cierra en el mismo
 * movimiento, pero contra qué cuenta de cartera y si va en el mismo comprobante
 * que el ajuste del período no está definido. El concepto existe en la
 * paramétrica y el exportable emite su bloque por separado y rotulado como
 * informativo, porque ese deterioro ya está dentro del ajuste del período por
 * producto y sumarlo dos veces descuadraría el comprobante.
 *
 * ADVERTIR A CONTABILIDAD: el concepto CASTIGO se resuelve siempre contra la
 * fila general, la de producto nulo, porque las bajas del período se agrupan por
 * causal y no por producto. Una fila de CASTIGO con producto quedaría sembrada y
 * sin efecto.
 */
class CreateDetCuentaContableTables extends Migration
{
    public function up()
    {
        // Paramétrica viva, con vigencias igual que las demás del módulo.
        Schema::create('det_param_cuenta_contable', function (Blueprint $table) {
            $table->increments('id_param');
            $table->string('concepto', 24);
            // Nulo = fila general, la que resuelve los productos sin fila propia.
            $table->string('producto', 20)->nullable();
            $table->string('cuenta_debito', 20);
            $table->string('cuenta_credito', 20);
            $table->string('descripcion', 120);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha_registro');
            $table->index(['vigente_desde', 'vigente_hasta'], 'ix_param_cuenta_contable_vigencia');
        });

        // Copia congelada dentro del corte: un corte cerrado tiene que exportar
        // el mismo asiento dentro de seis meses aunque las cuentas cambien.
        Schema::create('det_corte_param_cuenta_contable', function (Blueprint $table) {
            $table->integer('id_corte');
            $table->string('concepto', 24);
            $table->string('producto', 20)->nullable();
            $table->string('cuenta_debito', 20);
            $table->string('cuenta_credito', 20);
            $table->string('descripcion', 120);
            $table->unique(['id_corte', 'concepto', 'producto'], 'ux_det_corte_param_cuenta_contable');
        });

        // Mientras el PUC esté vacío, «el corte congeló sus cuentas y no había
        // ninguna» y «el corte es anterior a esta fase y nunca las congeló» son
        // la misma tabla vacía, y el tratamiento tiene que ser el contrario: el
        // primero no puede volver a leer la paramétrica viva y el segundo tiene
        // que hacerlo. Esta bandera es lo único que los distingue, y de ella
        // depende que un corte cerrado siga exportando lo mismo el día que
        // Contabilidad siembre las cuentas.
        Schema::table('det_corte', function (Blueprint $table) {
            $table->boolean('cuentas_congeladas')->default(false);
        });
    }

    public function down()
    {
        Schema::table('det_corte', function (Blueprint $table) {
            $table->dropColumn('cuentas_congeladas');
        });

        Schema::dropIfExists('det_corte_param_cuenta_contable');
        Schema::dropIfExists('det_param_cuenta_contable');
    }
}
