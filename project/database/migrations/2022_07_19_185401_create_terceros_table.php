<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTercerosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('terceros', function (Blueprint $table) {
            $table->bigIncrements('idTercero');
            $table->string('nombreTercero',40);
            $table->string('apellidosTercero',40);
            $table->bigInteger('tipoDocumento')->unsigned();
            $table->foreign('tipoDocumento')->references('idTiposDocumentos')->on('tipos_documentos');
            $table->integer('documentoTercero');
            $table->date('fechaExpedicionDocumento');
            $table->string('lugarExpedicionDocumento',70);
            $table->date('fechaNacimiento');
            $table->string('telefonoTercero',15);
            $table->string('emailTercero',100);
            $table->string('direccionDomicilio',70);
            $table->string('ciudadDomicilio',50);
            $table->string('departamentoDomicilio',50);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('terceros');
    }
}
