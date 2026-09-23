<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5a · Soporte de la marca de suspensión como adjunto real.
 *
 * `soporte` deja de ser texto libre y pasa a guardar la REFERENCIA al archivo:
 * la ruta autenticada (route('deterioro-ver-soporte')), nunca la ruta física,
 * igual que hace Gestión Documental con los folios. El archivo vive en
 * project/soportes-suspension/, fuera de public/ y bloqueada por .htaccess.
 *
 * No se migra ni se reinterpreta nada de lo existente: las 259 marcas del
 * cargue inicial tienen la columna vacía. Y el cargue inicial (D-14) sigue
 * escribiendo texto en `soporte` sin cambio alguno; lo que distingue «esto es
 * un adjunto» de «esto es una referencia escrita a mano» es soporte_nombre,
 * que sólo puebla la marcación manual con archivo.
 *
 * soporte_nombre es el nombre original con que el usuario lo subió, y existe
 * porque el nombre en disco se deriva del id de la marca —entrada del cliente
 * no decide dónde se escribe—: sin él, dentro de un año nadie sabría qué es
 * 12.pdf. soporte_tipo guarda el tipo MIME, que sirve para dos cosas: el
 * Content-Type con que se sirve y reconstruir la extensión del archivo en
 * disco sin tener que buscarlo en la carpeta.
 *
 * Lleva guarda hasTable/hasColumn, a diferencia del resto de la serie: la
 * divergencia entre bases ya nos costó una vez.
 */
class AddSoporteAdjuntoToDetSuspension extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('det_suspension_interes')
            || Schema::hasColumn('det_suspension_interes', 'soporte_nombre')) {
            return;
        }

        Schema::table('det_suspension_interes', function (Blueprint $table) {
            $table->string('soporte_nombre', 255)->nullable();
            $table->string('soporte_tipo', 100)->nullable();
        });
    }

    public function down()
    {
        if (!Schema::hasTable('det_suspension_interes')
            || !Schema::hasColumn('det_suspension_interes', 'soporte_nombre')) {
            return;
        }

        // Los archivos de project/soportes-suspension/ NO se borran: son
        // evidencia contable y esta reversión sólo suelta las columnas.
        Schema::table('det_suspension_interes', function (Blueprint $table) {
            $table->dropColumn(['soporte_nombre', 'soporte_tipo']);
        });
    }
}
