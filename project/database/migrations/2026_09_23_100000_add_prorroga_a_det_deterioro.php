<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La prórroga vencida de SIESA entra en la base de deterioro.
 *
 * Contabilidad definió que el saldo de prórroga ya vencido es interés de la
 * obligación y no un accesorio fuera de la base: son 82.170.273 en 56 filas del
 * corte de agosto de 2026 que hasta hoy sólo sumaban al valor nominal. Sigue sin
 * ser capital —`saldo_siesa` no lo toca y la conciliación C-3 no cambia—, y por
 * eso viaja en columna propia: la base lo suma, el capital no.
 *
 * Aplica de agosto de 2026 en adelante. No hay compuerta por fecha en el código
 * porque no hace falta: el corte de julio está cerrado y no se recalcula.
 */
class AddProrrogaADetDeterioro extends Migration
{
    public function up()
    {
        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            // Prórroga vencida que el snapshot atribuyó a la operación por sus
            // notas. Se guarda aparte de saldo_siesa, que sigue significando
            // capital, y es lo que la base de deterioro suma al interés vencido.
            //
            // Nulable, y el nulo significa una sola cosa: que el corte se
            // calculó antes de esta política. Un corte calculado con ella deja
            // cero en las operaciones sin prórroga —el enlace las recorre todas
            // y escribe ISNULL(…, 0)—, de modo que las pantallas distinguen
            // «medido y no hay» de «no evaluado», igual que con la marca de
            // cuotas repetidas. Por eso tampoco se rellena con cero lo ya
            // calculado: el nulo del corte de julio es correcto.
            $table->decimal('interes_prorroga_siesa', 19, 4)->nullable();
        });
    }

    public function down()
    {
        DB::table('det_corte_cuadre')->where('codigo', 'C-SIESA-PRORROGA')->delete();

        Schema::table('det_deterioro_operacion', function (Blueprint $table) {
            $table->dropColumn('interes_prorroga_siesa');
        });
    }
}
